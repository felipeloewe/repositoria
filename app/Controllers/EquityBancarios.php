<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EquityAcl;
use App\Libraries\EquityBancarios as Bancarios;
use App\Libraries\EquityVault;
use App\Libraries\MembershipAuth;
use App\Models\EquityCuentaClaveModel;
use App\Models\EquityPersonaBancoModel;
use App\Models\EquityPersonaCuentaModel;
use App\Models\EquityPersonaModel;
use App\Models\EquityPersonaTarjetaModel;
use App\Models\EquityTarjetaPinModel;

/**
 * Antecedentes bancarios por persona (bancos → cuentas + tarjetas).
 */
class EquityBancarios extends BaseController
{
    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireAccess()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! EquityAcl::canAccess($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Sin acceso al módulo Equity.');
        }

        return $memb;
    }

    private function findPersona(string $empresaId, string $id): ?array
    {
        $row = model(EquityPersonaModel::class)
            ->where('id', $id)
            ->where('empresa_id', $empresaId)
            ->first();

        return is_array($row) ? $row : null;
    }

    private function findBanco(string $empresaId, string $personaId, string $bancoId): ?array
    {
        $row = model(EquityPersonaBancoModel::class)
            ->where('id', $bancoId)
            ->where('empresa_id', $empresaId)
            ->where('persona_id', $personaId)
            ->first();

        return is_array($row) ? $row : null;
    }

    public function index(string $personaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $persona = $this->findPersona($memb['empresa_id'], $personaId);
        if ($persona === null) {
            return redirect()->to('/equity')->with('error', 'Persona no encontrada.');
        }

        $uid = (string) ($memb['usuario_id'] ?? '');

        return view('equity/bancarios', [
            'title'       => 'Antecedentes bancarios',
            'memb'        => $memb,
            'persona'     => $persona,
            'arbol'       => Bancarios::arbolPersona($memb['empresa_id'], $personaId),
            'tablasOk'    => Bancarios::tablasListas(),
            'secretosOk'  => EquityVault::tablasListas(),
            'canManage'   => EquityAcl::canManage($memb),
            'marcas'      => Bancarios::MARCAS,
            'tiposCuenta' => Bancarios::TIPOS_CUENTA,
            'monedas'     => Bancarios::MONEDAS,
            'vaultPinOk'  => $uid !== '' && EquityVault::usuarioTienePin($uid),
            'vaultOpen'   => EquityVault::estaDesbloqueada(),
            'success'     => session()->getFlashdata('success'),
            'error'       => session()->getFlashdata('error'),
        ]);
    }

    public function storeBanco(string $personaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }
        if (! Bancarios::tablasListas()) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')
                ->with('error', 'Falta la migración de antecedentes bancarios (000040).');
        }

        $persona = $this->findPersona($memb['empresa_id'], $personaId);
        if ($persona === null) {
            return redirect()->to('/equity')->with('error', 'Persona no encontrada.');
        }

        $nombre = trim((string) $this->request->getPost('banco_nombre'));
        if ($nombre === '') {
            return redirect()->to('/equity/' . $personaId . '/bancarios')
                ->with('error', 'Indica el nombre del banco.');
        }

        helper('id');
        model(EquityPersonaBancoModel::class)->insert([
            'id'            => new_id(),
            'empresa_id'    => $memb['empresa_id'],
            'persona_id'    => $personaId,
            'banco_nombre'  => mb_substr($nombre, 0, 160),
            'notas'         => $this->nullableStr('notas', 500),
            'creado_por_id' => $memb['usuario_id'] ?? null,
        ]);

        return redirect()->to('/equity/' . $personaId . '/bancarios')
            ->with('success', 'Banco agregado.');
    }

    public function updateBanco(string $personaId, string $bancoId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }

        $banco = $this->findBanco($memb['empresa_id'], $personaId, $bancoId);
        if ($banco === null) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Banco no encontrado.');
        }

        $nombre = trim((string) $this->request->getPost('banco_nombre'));
        if ($nombre === '') {
            return redirect()->to('/equity/' . $personaId . '/bancarios')
                ->with('error', 'Indica el nombre del banco.');
        }

        model(EquityPersonaBancoModel::class)->update($bancoId, [
            'banco_nombre' => mb_substr($nombre, 0, 160),
            'notas'        => $this->nullableStr('notas', 500),
        ]);

        return redirect()->to('/equity/' . $personaId . '/bancarios#banco-' . $bancoId)
            ->with('success', 'Banco actualizado.');
    }

    public function deleteBanco(string $personaId, string $bancoId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }

        $banco = $this->findBanco($memb['empresa_id'], $personaId, $bancoId);
        if ($banco === null) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Banco no encontrado.');
        }

        $db = db_connect();
        $cuentaIds = array_column(
            model(EquityPersonaCuentaModel::class)->where('banco_id', $bancoId)->findAll(),
            'id'
        );
        $tarjetaIds = array_column(
            model(EquityPersonaTarjetaModel::class)->where('banco_id', $bancoId)->findAll(),
            'id'
        );
        if ($cuentaIds !== [] && $db->tableExists('equity_cuenta_claves')) {
            $db->table('equity_cuenta_claves')->whereIn('cuenta_id', $cuentaIds)->delete();
        }
        if ($tarjetaIds !== [] && $db->tableExists('equity_tarjeta_pines')) {
            $db->table('equity_tarjeta_pines')->whereIn('tarjeta_id', $tarjetaIds)->delete();
        }
        $db->table('equity_persona_tarjetas')->where('banco_id', $bancoId)->delete();
        $db->table('equity_persona_cuentas')->where('banco_id', $bancoId)->delete();
        model(EquityPersonaBancoModel::class)->delete($bancoId);

        return redirect()->to('/equity/' . $personaId . '/bancarios')
            ->with('success', 'Banco y productos asociados eliminados.');
    }

    public function storeCuenta(string $personaId, string $bancoId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }

        $banco = $this->findBanco($memb['empresa_id'], $personaId, $bancoId);
        if ($banco === null) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Banco no encontrado.');
        }

        $tipo = strtolower(trim((string) $this->request->getPost('tipo')));
        if (! isset(Bancarios::TIPOS_CUENTA[$tipo])) {
            $tipo = 'corriente';
        }
        $moneda = strtoupper(trim((string) $this->request->getPost('moneda')));
        if (! in_array($moneda, Bancarios::MONEDAS, true)) {
            $moneda = 'CLP';
        }

        // Número completo (referencial entre bancos); no truncar a últimos dígitos.
        $numero = trim((string) $this->request->getPost('numero_cuenta'));
        if ($numero === '') {
            $numero = trim((string) $this->request->getPost('ultimos_digitos'));
        }
        $numero = $numero !== '' ? mb_substr($numero, 0, 64) : null;

        helper('id');
        model(EquityPersonaCuentaModel::class)->insert([
            'id'               => new_id(),
            'banco_id'         => $bancoId,
            'empresa_id'       => $memb['empresa_id'],
            'persona_id'       => $personaId,
            'tipo'             => $tipo,
            'moneda'           => $moneda,
            'numero_cuenta'    => $numero,
            'en_google_wallet' => $this->request->getPost('en_google_wallet') ? 1 : 0,
            'en_gpay'          => $this->request->getPost('en_gpay') ? 1 : 0,
            'en_link'          => $this->request->getPost('en_link') ? 1 : 0,
            'notas'            => $this->nullableStr('notas', 255),
        ]);

        return redirect()->to('/equity/' . $personaId . '/bancarios#banco-' . $bancoId)
            ->with('success', 'Cuenta agregada.');
    }

    public function deleteCuenta(string $personaId, string $cuentaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }

        $row = model(EquityPersonaCuentaModel::class)
            ->where('id', $cuentaId)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('persona_id', $personaId)
            ->first();
        if (! is_array($row)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Cuenta no encontrada.');
        }

        $bancoId = (string) $row['banco_id'];
        $db = db_connect();
        if ($db->tableExists('equity_cuenta_claves')) {
            $db->table('equity_cuenta_claves')->where('cuenta_id', $cuentaId)->delete();
        }
        model(EquityPersonaCuentaModel::class)->delete($cuentaId);

        return redirect()->to('/equity/' . $personaId . '/bancarios#banco-' . $bancoId)
            ->with('success', 'Cuenta eliminada.');
    }

    public function storeTarjeta(string $personaId, string $bancoId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }

        $banco = $this->findBanco($memb['empresa_id'], $personaId, $bancoId);
        if ($banco === null) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Banco no encontrado.');
        }

        $marca = strtolower(trim((string) $this->request->getPost('marca')));
        if (! isset(Bancarios::MARCAS[$marca])) {
            return redirect()->to('/equity/' . $personaId . '/bancarios#banco-' . $bancoId)
                ->with('error', 'Selecciona el tipo de tarjeta (Visa, Mastercard, etc.).');
        }

        $venc = Bancarios::normalizarVencimiento((string) $this->request->getPost('fecha_vencimiento'));
        $ult4 = Bancarios::normalizarUltimos4((string) $this->request->getPost('ultimos_4'));
        $clp  = Bancarios::moneyOrNull($this->request->getPost('monto_aprobado_clp'));
        $usd  = Bancarios::marcaSoportaUsd($marca)
            ? Bancarios::moneyOrNull($this->request->getPost('monto_aprobado_usd'))
            : null;

        helper('id');
        model(EquityPersonaTarjetaModel::class)->insert([
            'id'                 => new_id(),
            'banco_id'           => $bancoId,
            'empresa_id'         => $memb['empresa_id'],
            'persona_id'         => $personaId,
            'marca'              => $marca,
            'tipo_producto'      => $this->nullableStr('tipo_producto', 64),
            'monto_aprobado_clp' => $clp,
            'monto_aprobado_usd' => $usd,
            'fecha_vencimiento'  => $venc,
            'ultimos_4'          => $ult4,
            'en_google_wallet'   => $this->request->getPost('en_google_wallet') ? 1 : 0,
            'en_gpay'            => $this->request->getPost('en_gpay') ? 1 : 0,
            'en_link'            => $this->request->getPost('en_link') ? 1 : 0,
            'notas'              => $this->nullableStr('notas', 255),
        ]);

        return redirect()->to('/equity/' . $personaId . '/bancarios#banco-' . $bancoId)
            ->with('success', 'Tarjeta agregada.');
    }

    public function deleteTarjeta(string $personaId, string $tarjetaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }

        $row = model(EquityPersonaTarjetaModel::class)
            ->where('id', $tarjetaId)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('persona_id', $personaId)
            ->first();
        if (! is_array($row)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Tarjeta no encontrada.');
        }

        $bancoId = (string) $row['banco_id'];
        $db = db_connect();
        if ($db->tableExists('equity_tarjeta_pines')) {
            $db->table('equity_tarjeta_pines')->where('tarjeta_id', $tarjetaId)->delete();
        }
        model(EquityPersonaTarjetaModel::class)->delete($tarjetaId);

        return redirect()->to('/equity/' . $personaId . '/bancarios#banco-' . $bancoId)
            ->with('success', 'Tarjeta eliminada.');
    }

    public function vaultPinForm()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        $uid = (string) ($memb['usuario_id'] ?? '');

        return view('equity/vault_pin', [
            'title'      => 'PIN de bóveda',
            'memb'       => $memb,
            'tienePin'   => $uid !== '' && EquityVault::usuarioTienePin($uid),
            'vaultOpen'  => EquityVault::estaDesbloqueada(),
            'secretosOk' => EquityVault::tablasListas(),
            'success'    => session()->getFlashdata('success'),
            'error'      => session()->getFlashdata('error'),
            'returnTo'   => (string) $this->request->getGet('return'),
        ]);
    }

    public function vaultPinSave()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityVault::tablasListas()) {
            return redirect()->to('/equity/vault-pin')->with('error', 'Falta la migración de bóveda (000043).');
        }
        $uid = (string) ($memb['usuario_id'] ?? '');
        $pin = (string) $this->request->getPost('pin');
        $pin2 = (string) $this->request->getPost('pin_confirm');
        $actual = (string) $this->request->getPost('pin_actual');
        $return = trim((string) $this->request->getPost('return'));
        if ($pin !== $pin2) {
            return redirect()->to('/equity/vault-pin')->with('error', 'Los PIN no coinciden.');
        }
        $res = EquityVault::configurarPin($uid, $pin, $actual !== '' ? $actual : null);
        $to = $return !== '' ? $return : '/equity/vault-pin';
        if (! ($res['ok'] ?? false)) {
            return redirect()->to('/equity/vault-pin' . ($return !== '' ? '?return=' . rawurlencode($return) : ''))
                ->with('error', $res['error'] ?? 'No se pudo guardar el PIN.');
        }

        return redirect()->to($to)->with('success', 'PIN de bóveda guardado. Secretos desbloqueados por 15 minutos.');
    }

    public function vaultUnlock(string $personaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        $uid = (string) ($memb['usuario_id'] ?? '');
        $pin = (string) $this->request->getPost('pin');
        $res = EquityVault::desbloquear($uid, $pin);
        if (! ($res['ok'] ?? false)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')
                ->with('error', $res['error'] ?? 'PIN incorrecto.');
        }

        return redirect()->to('/equity/' . $personaId . '/bancarios')
            ->with('success', 'Bóveda desbloqueada (15 minutos).');
    }

    public function vaultLock(string $personaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        EquityVault::bloquear();

        return redirect()->to('/equity/' . $personaId . '/bancarios')
            ->with('success', 'Bóveda bloqueada.');
    }

    public function storeCuentaClave(string $personaId, string $cuentaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }
        $cuenta = $this->findCuenta($memb['empresa_id'], $personaId, $cuentaId);
        if ($cuenta === null) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Cuenta no encontrada.');
        }
        if (! EquityVault::tablasListas()) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')
                ->with('error', 'Falta la migración de bóveda (000043).');
        }
        $uid = (string) ($memb['usuario_id'] ?? '');
        if (! EquityVault::usuarioTienePin($uid)) {
            return redirect()->to('/equity/vault-pin?return=' . rawurlencode('/equity/' . $personaId . '/bancarios'))
                ->with('error', 'Configura un PIN de bóveda antes de guardar claves.');
        }
        if (! EquityVault::estaDesbloqueada()) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')
                ->with('error', 'Desbloquea la bóveda con tu PIN para guardar claves.');
        }

        $clave = (string) $this->request->getPost('clave');
        if (trim($clave) === '') {
            return redirect()->to('/equity/' . $personaId . '/bancarios#cuenta-' . $cuentaId)
                ->with('error', 'La clave de internet es obligatoria.');
        }
        $cipher = EquityVault::cifrar($clave);
        if ($cipher === null) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')
                ->with('error', 'No se pudo cifrar la clave.');
        }

        $inicio = Bancarios::normalizarFecha((string) $this->request->getPost('fecha_inicio')) ?? date('Y-m-d');
        $hasta  = Bancarios::normalizarFecha((string) $this->request->getPost('vigencia_hasta'));
        $estado = Bancarios::normalizarEstado((string) $this->request->getPost('estado'));
        if ($hasta !== null && $hasta < date('Y-m-d')) {
            $estado = 'caducada';
        }

        if ((string) $this->request->getPost('caducar_anteriores') === '1') {
            model(EquityCuentaClaveModel::class)
                ->where('cuenta_id', $cuentaId)
                ->where('estado', 'vigente')
                ->set(['estado' => 'caducada'])
                ->update();
        }

        helper('id');
        model(EquityCuentaClaveModel::class)->insert([
            'id'             => new_id(),
            'cuenta_id'      => $cuentaId,
            'empresa_id'     => $memb['empresa_id'],
            'persona_id'     => $personaId,
            'fecha_inicio'   => $inicio,
            'vigencia_hasta' => $hasta,
            'estado'         => $estado,
            'secreto_cipher' => $cipher,
            'creado_por_id'  => $uid,
        ]);

        return redirect()->to('/equity/' . $personaId . '/bancarios#cuenta-' . $cuentaId)
            ->with('success', 'Clave de internet agregada (cifrada).');
    }

    public function deleteCuentaClave(string $personaId, string $claveId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }
        $row = model(EquityCuentaClaveModel::class)
            ->where('id', $claveId)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('persona_id', $personaId)
            ->first();
        if (! is_array($row)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Registro no encontrado.');
        }
        $cuentaId = (string) $row['cuenta_id'];
        model(EquityCuentaClaveModel::class)->delete($claveId);

        return redirect()->to('/equity/' . $personaId . '/bancarios#cuenta-' . $cuentaId)
            ->with('success', 'Clave eliminada.');
    }

    public function storeTarjetaPin(string $personaId, string $tarjetaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }
        $tarjeta = $this->findTarjeta($memb['empresa_id'], $personaId, $tarjetaId);
        if ($tarjeta === null) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Tarjeta no encontrada.');
        }
        if (! EquityVault::tablasListas()) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')
                ->with('error', 'Falta la migración de bóveda (000043).');
        }
        $uid = (string) ($memb['usuario_id'] ?? '');
        if (! EquityVault::usuarioTienePin($uid)) {
            return redirect()->to('/equity/vault-pin?return=' . rawurlencode('/equity/' . $personaId . '/bancarios'))
                ->with('error', 'Configura un PIN de bóveda antes de guardar PINes de cajero.');
        }
        if (! EquityVault::estaDesbloqueada()) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')
                ->with('error', 'Desbloquea la bóveda con tu PIN para guardar PINes.');
        }

        $pinCajero = preg_replace('/\D+/', '', (string) $this->request->getPost('pin_cajero')) ?? '';
        if (strlen($pinCajero) !== 4) {
            return redirect()->to('/equity/' . $personaId . '/bancarios#tarjeta-' . $tarjetaId)
                ->with('error', 'El PIN de cajero debe tener exactamente 4 dígitos.');
        }
        $cipher = EquityVault::cifrar($pinCajero);
        if ($cipher === null) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')
                ->with('error', 'No se pudo cifrar el PIN.');
        }

        $inicio = Bancarios::normalizarFecha((string) $this->request->getPost('fecha_inicio')) ?? date('Y-m-d');
        $hasta  = Bancarios::normalizarFecha((string) $this->request->getPost('vigencia_hasta'));
        $estado = Bancarios::normalizarEstado((string) $this->request->getPost('estado'));
        if ($hasta !== null && $hasta < date('Y-m-d')) {
            $estado = 'caducada';
        }

        if ((string) $this->request->getPost('caducar_anteriores') === '1') {
            model(EquityTarjetaPinModel::class)
                ->where('tarjeta_id', $tarjetaId)
                ->where('estado', 'vigente')
                ->set(['estado' => 'caducada'])
                ->update();
        }

        helper('id');
        model(EquityTarjetaPinModel::class)->insert([
            'id'             => new_id(),
            'tarjeta_id'     => $tarjetaId,
            'empresa_id'     => $memb['empresa_id'],
            'persona_id'     => $personaId,
            'fecha_inicio'   => $inicio,
            'vigencia_hasta' => $hasta,
            'estado'         => $estado,
            'secreto_cipher' => $cipher,
            'creado_por_id'  => $uid,
        ]);

        return redirect()->to('/equity/' . $personaId . '/bancarios#tarjeta-' . $tarjetaId)
            ->with('success', 'PIN de cajero agregado (cifrado).');
    }

    public function deleteTarjetaPin(string $personaId, string $pinId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Sin permiso.');
        }
        $row = model(EquityTarjetaPinModel::class)
            ->where('id', $pinId)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('persona_id', $personaId)
            ->first();
        if (! is_array($row)) {
            return redirect()->to('/equity/' . $personaId . '/bancarios')->with('error', 'Registro no encontrado.');
        }
        $tarjetaId = (string) $row['tarjeta_id'];
        model(EquityTarjetaPinModel::class)->delete($pinId);

        return redirect()->to('/equity/' . $personaId . '/bancarios#tarjeta-' . $tarjetaId)
            ->with('success', 'PIN eliminado.');
    }

    private function findCuenta(string $empresaId, string $personaId, string $cuentaId): ?array
    {
        $row = model(EquityPersonaCuentaModel::class)
            ->where('id', $cuentaId)
            ->where('empresa_id', $empresaId)
            ->where('persona_id', $personaId)
            ->first();

        return is_array($row) ? $row : null;
    }

    private function findTarjeta(string $empresaId, string $personaId, string $tarjetaId): ?array
    {
        $row = model(EquityPersonaTarjetaModel::class)
            ->where('id', $tarjetaId)
            ->where('empresa_id', $empresaId)
            ->where('persona_id', $personaId)
            ->first();

        return is_array($row) ? $row : null;
    }

    private function nullableStr(string $field, int $max): ?string
    {
        $v = trim((string) $this->request->getPost($field));
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, $max);
    }
}
