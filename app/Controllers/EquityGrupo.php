<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EquityAcl;
use App\Libraries\EquityOrg;
use App\Libraries\IdentityVault;
use App\Libraries\PiiCatalog;
use App\Libraries\MembershipAuth;
use App\Models\EmpresaModel;
use App\Models\EquityPersonaModel;
use App\Models\MembresiaModel;
use App\Models\UsuarioModel;

/**
 * Administración Equity: matriz, subsidiarias y alta de declarantes/usuarios.
 */
class EquityGrupo extends BaseController
{
    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireManage()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Sin permiso para administrar el grupo Equity.');
        }

        return $memb;
    }

    public function index()
    {
        $memb = $this->requireManage();
        if (! is_array($memb)) {
            return $memb;
        }

        $org     = new EquityOrg();
        $empresa = model(EmpresaModel::class)->find($memb['empresa_id']);
        $matrizId = is_array($empresa) ? $org->matrizIdDe($empresa) : $memb['empresa_id'];
        $matriz   = model(EmpresaModel::class)->find($matrizId);
        $subs     = $org->subsidiariasDe($matrizId);

        $grupoIds = $org->empresaIdsDelGrupo($memb['empresa_id']);
        // Incluye bajas (para scrub); las anonimizadas se listan al final.
        $personaQ = model(EquityPersonaModel::class)->whereIn('empresa_id', $grupoIds);
        $db = db_connect();
        if ($db->fieldExists('pii_scrubbed_at', 'equity_personas')) {
            $personaQ->orderBy('pii_scrubbed_at', 'ASC');
        }
        $personaQ->orderBy('activo', 'DESC')->orderBy('nombre', 'ASC');
        $personas = $personaQ->findAll();

        $usuariosGrupo = [];
        foreach (model(MembresiaModel::class)->whereIn('empresa_id', $grupoIds)->where('activa', 1)->findAll() as $m) {
            $u = model(UsuarioModel::class)->find($m['usuario_id']);
            if (is_array($u)) {
                $usuariosGrupo[$u['id']] = $u;
            }
        }
        // Incluir usuarios referenciados por personas (propia o representante) aunque falte membresía listada.
        foreach ($personas as $p) {
            foreach (['usuario_id', 'representado_por_usuario_id'] as $uk) {
                $uid = (string) ($p[$uk] ?? '');
                if ($uid !== '' && ! isset($usuariosGrupo[$uid])) {
                    $u = model(UsuarioModel::class)->find($uid);
                    if (is_array($u)) {
                        $usuariosGrupo[$uid] = $u;
                    }
                }
            }
        }

        $empresasMap = [];
        foreach (model(EmpresaModel::class)->whereIn('id', $grupoIds)->findAll() as $e) {
            $empresasMap[$e['id']] = $e;
        }

        return view('equity/grupo', [
            'title'         => 'Grupo Equity',
            'memb'          => $memb,
            'equityNav'     => 'grupo',
            'matriz'        => $matriz,
            'subsidiarias'  => $subs,
            'personas'      => $personas,
            'usuariosGrupo' => $usuariosGrupo,
            'empresasMap'   => $empresasMap,
            'grupoIds'      => $grupoIds,
            'success'       => session()->getFlashdata('success'),
            'error'         => session()->getFlashdata('error'),
            'inviteInfo'    => session()->getFlashdata('inviteInfo'),
        ]);
    }

    public function storeSubsidiaria()
    {
        $memb = $this->requireManage();
        if (! is_array($memb)) {
            return $memb;
        }

        $org = new EquityOrg();
        $empresa = model(EmpresaModel::class)->find($memb['empresa_id']);
        $matrizId = is_array($empresa) ? $org->matrizIdDe($empresa) : $memb['empresa_id'];

        // Solo la matriz (o quien esté en ella) registra subsidiarias
        if (is_array($empresa) && ! empty($empresa['equity_matriz_id'])) {
            return redirect()->to('/equity/grupo')->with('error', 'Solo la empresa matriz puede registrar subsidiarias.');
        }

        $result = $org->registrarSubsidiaria($matrizId, [
            'nombre' => $this->request->getPost('nombre'),
            'rut'    => $this->request->getPost('rut'),
        ]);
        if (! ($result['ok'] ?? false)) {
            return redirect()->to('/equity/grupo')->with('error', $result['error'] ?? 'No se pudo crear.');
        }

        // Marcar empresa actual como matriz
        model(EmpresaModel::class)->update($matrizId, [
            'equity_es_matriz' => 1,
            'equity_matriz_id' => null,
        ]);

        return redirect()->to('/equity/grupo')->with('success', 'Subsidiaria registrada. Queda apadrinada por la matriz.');
    }

    public function storePersonaUsuario()
    {
        $memb = $this->requireManage();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaDestino = trim((string) $this->request->getPost('empresa_id')) ?: $memb['empresa_id'];
        $org = new EquityOrg();
        $grupo = $org->empresaIdsDelGrupo($memb['empresa_id']);
        if (! in_array($empresaDestino, $grupo, true)) {
            return redirect()->to('/equity/grupo')->with('error', 'Empresa destino fuera del grupo.');
        }

        $result = $org->altaPersonaConUsuario($empresaDestino, [
            'nombre'                      => $this->request->getPost('nombre'),
            'apellido'                    => $this->request->getPost('apellido'),
            'email'                       => $this->request->getPost('email'),
            'rut'                         => $this->request->getPost('rut'),
            'tipo'                        => $this->request->getPost('tipo') ?: 'socio',
            'representado_por_usuario_id' => $this->request->getPost('representado_por_usuario_id'),
        ], $memb['usuario_id']);

        if (! ($result['ok'] ?? false)) {
            return redirect()->to('/equity/grupo')->with('error', $result['error'] ?? 'No se pudo crear.');
        }

        $info = 'Persona creada.';
        if (! empty($result['password'])) {
            $info .= ' Clave temporal: ' . $result['password'] . '.';
        }
        if (! empty($result['verify_url'])) {
            $info .= ' Enlace verificación: ' . $result['verify_url'];
        }

        return redirect()->to('/equity/grupo')
            ->with('success', 'Declarante habilitado. Se envió (o registró) verificación de correo.')
            ->with('inviteInfo', $info);
    }

    public function privacidad()
    {
        $memb = $this->requireManage();
        if (! is_array($memb)) {
            return $memb;
        }

        return view('equity/privacidad', [
            'title'     => 'Privacidad · Identidad',
            'memb'      => $memb,
            'equityNav' => 'grupo',
            'inventory' => PiiCatalog::inventory(),
        ]);
    }

    public function bajaPersona(string $id)
    {
        $memb = $this->requireManage();
        if (! is_array($memb)) {
            return $memb;
        }

        $persona = model(EquityPersonaModel::class)->find($id);
        if (! is_array($persona) || ! $this->personaEnGrupo($memb, $persona)) {
            return redirect()->to('/equity/grupo')->with('error', 'Persona fuera del grupo.');
        }

        $result = IdentityVault::deactivatePersona($id, (string) $memb['usuario_id']);
        if (! ($result['ok'] ?? false)) {
            return redirect()->to('/equity/grupo')->with('error', $result['error'] ?? 'No se pudo dar de baja.');
        }

        return redirect()->to('/equity/grupo')->with(
            'success',
            'Persona dada de baja. Los datos patrimoniales se conservan por ID; el PII sigue hasta que ejecutes “Anonimizar”.'
        );
    }

    public function scrubPersona(string $id)
    {
        $memb = $this->requireManage();
        if (! is_array($memb)) {
            return $memb;
        }

        $persona = model(EquityPersonaModel::class)->find($id);
        if (! is_array($persona) || ! $this->personaEnGrupo($memb, $persona)) {
            return redirect()->to('/equity/grupo')->with('error', 'Persona fuera del grupo.');
        }

        $result = IdentityVault::scrubPersona($id);
        if (! ($result['ok'] ?? false)) {
            return redirect()->to('/equity/grupo')->with('error', $result['error'] ?? 'No se pudo anonimizar.');
        }

        return redirect()->to('/equity/grupo')->with(
            'success',
            'PII anonimizado. El patrimonio queda solo con el ID; no se puede rearmar nombre/RUT/correo desde la base operativa.'
        );
    }

    /**
     * @param array<string, mixed> $memb
     * @param array<string, mixed> $persona
     */
    private function personaEnGrupo(array $memb, array $persona): bool
    {
        $grupo = (new EquityOrg())->empresaIdsDelGrupo((string) $memb['empresa_id']);

        return in_array((string) ($persona['empresa_id'] ?? ''), $grupo, true);
    }
}
