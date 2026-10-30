"use client";

import { useActionState } from "react";
import {
  saveAttachmentToRepoAction,
  saveCertificateToRepoAction,
  type CorreoActionState,
} from "@/app/actions/correo";
import { btnPrimary, Field, inputClass } from "@/components/ui";

const initial: CorreoActionState = {};

type SeccionOption = { id: string; label: string };

export function SaveAttachmentForm({
  attachmentId,
  threadId,
  defaultTitle,
  secciones,
}: {
  attachmentId: string;
  threadId: string;
  defaultTitle: string;
  secciones: SeccionOption[];
}) {
  const [state, action, pending] = useActionState(
    saveAttachmentToRepoAction,
    initial,
  );

  if (secciones.length === 0) {
    return (
      <p className="text-xs text-[var(--muted)]">
        Tu perfil no puede subir al repositorio en ninguna sección.
      </p>
    );
  }

  return (
    <form
      action={action}
      className="mt-2 space-y-2 rounded-[1rem] border border-[var(--border)] bg-[var(--surface)] p-3"
    >
      <input type="hidden" name="attachmentId" value={attachmentId} />
      <input type="hidden" name="threadId" value={threadId} />
      <p className="text-xs font-semibold text-[var(--ink)]">
        Guardar adjunto en el repositorio
      </p>
      <Field label="Título">
        <input
          className={inputClass}
          name="titulo"
          defaultValue={defaultTitle}
          required
        />
      </Field>
      <Field label="Sección">
        <select
          className={inputClass}
          name="seccionId"
          required
          defaultValue={secciones[0].id}
        >
          {secciones.map((s) => (
            <option key={s.id} value={s.id}>
              {s.label}
            </option>
          ))}
        </select>
      </Field>
      {state.error ? (
        <p className="text-xs text-rose-700">{state.error}</p>
      ) : null}
      {state.ok ? (
        <p className="text-xs font-medium text-[var(--accent-strong)]">
          Guardado
          {state.documentoId ? (
            <>
              {" "}
              ·{" "}
              <a className="underline" href="/documentos">
                ver repositorio
              </a>
            </>
          ) : null}
          {state.mode === "mock" ? " (mock)" : ""}.
        </p>
      ) : null}
      <button type="submit" className={btnPrimary} disabled={pending}>
        {pending ? "Guardando…" : "Guardar adjunto"}
      </button>
    </form>
  );
}

export function SaveCertificateForm({
  threadId,
  defaultTitle,
  secciones,
}: {
  threadId: string;
  defaultTitle: string;
  secciones: SeccionOption[];
}) {
  const [state, action, pending] = useActionState(
    saveCertificateToRepoAction,
    initial,
  );

  if (secciones.length === 0) {
    return (
      <p className="text-sm text-[var(--muted)]">
        Tu perfil no puede guardar el certificado en el repositorio.
      </p>
    );
  }

  return (
    <form action={action} className="space-y-3">
      <input type="hidden" name="threadId" value={threadId} />
      <Field label="Título del certificado">
        <input
          className={inputClass}
          name="titulo"
          defaultValue={defaultTitle}
          required
        />
      </Field>
      <Field label="Sección destino">
        <select
          className={inputClass}
          name="seccionId"
          required
          defaultValue={secciones[0].id}
        >
          {secciones.map((s) => (
            <option key={s.id} value={s.id}>
              {s.label}
            </option>
          ))}
        </select>
      </Field>
      {state.error ? (
        <p className="rounded-[var(--radius-control)] border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
          {state.error}
        </p>
      ) : null}
      {state.ok ? (
        <p className="rounded-[var(--radius-control)] border border-[var(--border)] bg-[var(--accent-soft)] px-3 py-2 text-sm text-[var(--ink)]">
          Certificado PDF guardado en el repositorio
          {state.mode === "mock" ? " (mock)" : ""}.{" "}
          <a className="font-medium text-[var(--accent-strong)] underline" href="/documentos">
            Abrir documentos
          </a>
        </p>
      ) : null}
      <button type="submit" className={btnPrimary} disabled={pending}>
        {pending ? "Generando y guardando…" : "Generar y guardar certificado PDF"}
      </button>
    </form>
  );
}
