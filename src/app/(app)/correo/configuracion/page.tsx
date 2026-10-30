import Link from "next/link";
import { CorreoLinkForm } from "@/components/correo-link-form";
import { PageHeader, Panel } from "@/components/ui";
import { imapMailHealth, imapMailMode, listMailAccounts } from "@/lib/imap-mail";
import { getSession } from "@/lib/session";

export const instant = false;

export const metadata = {
  title: "Vincular casilla",
};

export default async function CorreoConfigPage() {
  const session = (await getSession())!;
  const health = await imapMailHealth();
  const mode = imapMailMode();
  const { data: accounts } = await listMailAccounts(session.userId);

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <PageHeader
        title="Vincular casilla"
        description={`La casilla se asocia a tu usuarioId estable (${session.userId}). El alcance documental al guardar sigue tu membresía en ${session.empresaNombre}.`}
        actions={
          <Link
            href="/correo"
            className="rounded-lg border border-[var(--border)] px-3 py-2 text-sm text-[var(--ink)]"
          >
            Volver a hilos
          </Link>
        }
      />

      <Panel className="rounded-2xl p-4 text-sm text-[var(--muted)] shadow-sm">
        Servicio:{" "}
        <strong className="text-[var(--ink)]">
          {mode === "mock" ? "mock local" : "imap-mail"}
        </strong>
        {health.detail ? ` · ${health.detail}` : ""}. Gmail OAuth se inicia en
        imap-mail (`/api/oauth/gmail/start?usuarioId=…`) cuando{" "}
        <code>GMAIL_CLIENT_*</code> está configurado allí.
      </Panel>

      {accounts.length > 0 ? (
        <Panel className="rounded-2xl p-4 shadow-sm">
          <h2 className="font-medium text-[var(--ink)]">Ya vinculadas</h2>
          <ul className="mt-2 space-y-1 text-sm text-[var(--muted)]">
            {accounts.map((a) => (
              <li key={a.id}>
                {a.email} · {a.provider} · {a.syncStatus}
              </li>
            ))}
          </ul>
        </Panel>
      ) : null}

      <Panel className="rounded-2xl p-5 shadow-sm">
        <h2 className="font-medium text-[var(--ink)]">Nueva casilla IMAP</h2>
        <p className="mt-1 text-sm text-[var(--muted)]">
          Repositoria llama a <code>POST /api/accounts</code> en imap-mail con tu{" "}
          <code>usuarioId</code>. Sin servicio activo, el alta queda en mock.
        </p>
        <div className="mt-4">
          <CorreoLinkForm />
        </div>
      </Panel>
    </div>
  );
}
