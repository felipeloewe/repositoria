import Link from "next/link";
import { EmptyState, PageHeader, Panel } from "@/components/ui";
import { imapMailHealth, listMailAccounts, listMailThreads } from "@/lib/imap-mail";
import { getSession } from "@/lib/session";

export const instant = false;

export const metadata = {
  title: "Correo",
};

function formatWhen(iso: string | null): string {
  if (!iso) return "—";
  try {
    return new Date(iso).toLocaleString("es-CL", {
      dateStyle: "medium",
      timeStyle: "short",
    });
  } catch {
    return iso;
  }
}

export default async function CorreoPage() {
  const session = (await getSession())!;
  const health = await imapMailHealth();
  const { data: accounts, mode: accountsMode } = await listMailAccounts(
    session.userId,
  );
  const { data: threads, mode: threadsMode } = await listMailThreads(
    session.userId,
  );
  const mode = accountsMode === "mock" || threadsMode === "mock" ? "mock" : "live";

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <PageHeader
        title="Correo"
        description={`Hilos de tu casilla (usuario ${session.userId.slice(0, 8)}…) en el perfil ${session.empresaNombre}${session.departamentoNombre ? ` · ${session.departamentoNombre}` : ""}${session.sucursalNombre ? ` · ${session.sucursalNombre}` : ""}.`}
        actions={
          <Link
            href="/correo/configuracion"
            className="rounded-lg border border-[var(--border)] px-3 py-2 text-sm text-[var(--ink)] hover:border-[var(--accent)]"
          >
            Vincular casilla
          </Link>
        }
      />

      <Panel className="rounded-2xl px-4 py-3 text-sm text-[var(--muted)] shadow-sm">
        {mode === "mock" ? (
          <>
            Modo <strong className="text-[var(--ink)]">mock</strong>
            {!health.ok || health.mode === "mock"
              ? " — imap-mail no configurado o inaccesible. "
              : ". "}
            Arranca el servicio en el puerto 3001 y define{" "}
            <code className="text-[var(--ink)]">IMAP_MAIL_BASE_URL</code> (ver
            README).
          </>
        ) : (
          <>
            Conectado a imap-mail ({health.detail}). Casillas: {accounts.length}.
          </>
        )}
      </Panel>

      {accounts.length === 0 ? (
        <EmptyState
          title="Sin casilla vinculada"
          detail="Vincula IMAP o Gmail con tu usuarioId de membresía."
        />
      ) : (
        <Panel className="rounded-2xl p-4 text-sm shadow-sm">
          <p className="font-medium text-[var(--ink)]">Casillas</p>
          <ul className="mt-2 divide-y divide-[var(--border)]">
            {accounts.map((a) => (
              <li
                key={a.id}
                className="flex flex-wrap items-center justify-between gap-2 py-2"
              >
                <span className="text-[var(--ink)]">
                  {a.email}
                  {a.displayName ? ` · ${a.displayName}` : ""}
                </span>
                <span className="text-xs text-[var(--muted)]">
                  {a.provider} · {a.syncStatus}
                </span>
              </li>
            ))}
          </ul>
        </Panel>
      )}

      {threads.length === 0 ? (
        <EmptyState
          title="Sin hilos"
          detail="Tras sincronizar INBOX/Sent en imap-mail aparecerán aquí."
        />
      ) : (
        <ul className="divide-y divide-[var(--border)] overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface-elevated)] shadow-sm">
          {threads.map((t) => (
            <li key={t.id}>
              <Link
                href={`/correo/${t.id}`}
                className="flex flex-col gap-1 px-5 py-4 transition-colors hover:bg-[var(--surface)] sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0">
                  <p className="font-medium text-[var(--ink)]">{t.subject}</p>
                  <p className="mt-0.5 text-sm text-[var(--muted)]">
                    {t.account?.email ?? "casilla"} · {t.messageCount} mensajes
                  </p>
                </div>
                <span className="shrink-0 text-sm text-[var(--muted)]">
                  {formatWhen(t.lastMessageAt)}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
