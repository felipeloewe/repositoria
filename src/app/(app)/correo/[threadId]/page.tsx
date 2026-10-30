import Link from "next/link";
import { notFound } from "next/navigation";
import { listUploadableSecciones } from "@/app/actions/correo";
import {
  SaveAttachmentForm,
  SaveCertificateForm,
} from "@/components/correo-save-forms";
import { EmptyState, PageHeader, Panel } from "@/components/ui";
import { getMailThread } from "@/lib/imap-mail";
import { getSession } from "@/lib/session";

export const instant = false;

type Params = { params: Promise<{ threadId: string }> };

function formatWhen(iso: string): string {
  try {
    return new Date(iso).toLocaleString("es-CL", {
      dateStyle: "medium",
      timeStyle: "short",
    });
  } catch {
    return iso;
  }
}

function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

export default async function CorreoThreadPage({ params }: Params) {
  const session = (await getSession())!;
  const { threadId } = await params;

  let thread;
  let mode: string = "live";
  try {
    const result = await getMailThread(threadId, session.userId);
    thread = result.data;
    mode = result.mode;
  } catch {
    notFound();
  }

  if (thread.account.usuarioId !== session.userId) {
    notFound();
  }

  const secciones = await listUploadableSecciones();
  const allAttachments = thread.messages.flatMap((m) =>
    m.attachments.map((a) => ({ ...a, messageSubject: m.subject })),
  );

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <PageHeader
        title={thread.subject}
        description={`${thread.account.email} · ${thread.messageCount} mensajes${mode === "mock" ? " · mock" : ""}`}
        actions={
          <Link
            href="/correo"
            className="rounded-lg border border-[var(--border)] px-3 py-2 text-sm text-[var(--ink)]"
          >
            Todos los hilos
          </Link>
        }
      />

      <Panel className="rounded-2xl p-5 shadow-sm">
        <h2 className="font-medium text-[var(--ink)]">
          Certificado PDF del hilo
        </h2>
        <p className="mt-1 text-sm text-[var(--muted)]">
          Genera el certificado en imap-mail y lo registra como Documento en un
          departamento/sección de tu empresa (ACL de tu membresía).
        </p>
        <div className="mt-4">
          <SaveCertificateForm
            threadId={thread.id}
            defaultTitle={`Certificado · ${thread.subject}`.slice(0, 160)}
            secciones={secciones}
          />
        </div>
      </Panel>

      <div className="space-y-4">
        <h2 className="font-medium text-[var(--ink)]">Mensajes</h2>
        {thread.messages.length === 0 ? (
          <EmptyState title="Sin mensajes" />
        ) : (
          thread.messages.map((m) => (
            <Panel key={m.id} className="rounded-2xl p-5 shadow-sm">
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="font-medium text-[var(--ink)]">{m.fromAddr}</p>
                <span className="text-xs text-[var(--muted)]">
                  {m.folder} · {formatWhen(m.date)}
                </span>
              </div>
              <p className="mt-1 text-sm text-[var(--muted)]">Para: {m.toAddrs}</p>
              <p className="mt-3 whitespace-pre-wrap text-sm text-[var(--ink)]">
                {m.bodyText || "(sin cuerpo de texto)"}
              </p>
              {m.attachments.length > 0 ? (
                <ul className="mt-4 space-y-3 border-t border-[var(--border)] pt-4">
                  {m.attachments.map((a) => (
                    <li key={a.id}>
                      <p className="text-sm text-[var(--ink)]">
                        Adjunto: {a.filename}{" "}
                        <span className="text-[var(--muted)]">
                          · {a.mimeType} · {formatBytes(a.size)}
                        </span>
                      </p>
                      <SaveAttachmentForm
                        attachmentId={a.id}
                        threadId={thread.id}
                        defaultTitle={`Adjunto · ${a.filename}`}
                        secciones={secciones}
                      />
                    </li>
                  ))}
                </ul>
              ) : null}
            </Panel>
          ))
        )}
      </div>

      {allAttachments.length === 0 ? (
        <p className="text-sm text-[var(--muted)]">
          Este hilo no tiene adjuntos exportables.
        </p>
      ) : null}
    </div>
  );
}
