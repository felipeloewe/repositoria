"use client";

import { useActionState, useEffect, useRef } from "react";
import {
  uploadDocumento,
  type UploadState,
} from "@/app/actions/documentos";
import { btnPrimary, Field, inputClass } from "@/components/ui";

const initial: UploadState = {};

type SeccionOption = {
  id: string;
  label: string;
};

export function DocumentoUploadForm({
  secciones,
}: {
  secciones: SeccionOption[];
}) {
  const [state, action, pending] = useActionState(uploadDocumento, initial);
  const formRef = useRef<HTMLFormElement>(null);

  useEffect(() => {
    if (state.ok) formRef.current?.reset();
  }, [state.ok]);

  if (secciones.length === 0) {
    return (
      <p className="text-sm text-[var(--muted)]">
        No hay secciones donde puedas subir con tu perfil.
      </p>
    );
  }

  return (
    <form ref={formRef} action={action} className="space-y-3">
      <Field label="Título">
        <input className={inputClass} name="titulo" required />
      </Field>
      <Field label="Sección">
        <select className={inputClass} name="seccionId" required defaultValue="">
          <option value="" disabled>
            Elegir…
          </option>
          {secciones.map((s) => (
            <option key={s.id} value={s.id}>
              {s.label}
            </option>
          ))}
        </select>
      </Field>
      <Field label="Archivo">
        <input
          className={inputClass}
          type="file"
          name="file"
          required
          accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.png,.jpg,.jpeg"
        />
      </Field>
      {state.error ? (
        <p className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {state.error}
        </p>
      ) : null}
      {state.ok ? (
        <p className="rounded border border-[var(--border)] bg-[var(--accent-soft)] px-3 py-2 text-sm text-[var(--ink)]">
          Documento subido.
        </p>
      ) : null}
      <button type="submit" className={btnPrimary} disabled={pending}>
        {pending ? "Subiendo…" : "Subir documento"}
      </button>
    </form>
  );
}
