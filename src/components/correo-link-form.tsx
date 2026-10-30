"use client";

import { useActionState } from "react";
import {
  linkMailboxAction,
  type CorreoActionState,
} from "@/app/actions/correo";
import { btnPrimary, Field, inputClass } from "@/components/ui";

const initial: CorreoActionState = {};

export function CorreoLinkForm() {
  const [state, action, pending] = useActionState(linkMailboxAction, initial);

  return (
    <form action={action} className="max-w-lg space-y-3">
      <Field label="Correo">
        <input
          className={inputClass}
          name="email"
          type="email"
          required
          placeholder="ana@empresa.cl"
        />
      </Field>
      <Field label="Nombre para mostrar">
        <input className={inputClass} name="displayName" placeholder="Ana Pérez" />
      </Field>
      <Field label="Proveedor">
        <select className={inputClass} name="provider" defaultValue="IMAP">
          <option value="IMAP">IMAP</option>
          <option value="GMAIL">Gmail (OAuth en imap-mail)</option>
        </select>
      </Field>
      <Field label="Host IMAP">
        <input
          className={inputClass}
          name="imapHost"
          placeholder="imap.empresa.cl"
        />
      </Field>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Puerto">
          <input
            className={inputClass}
            name="imapPort"
            type="number"
            defaultValue={993}
          />
        </Field>
        <Field label="Usuario IMAP">
          <input
            className={inputClass}
            name="imapUser"
            placeholder="igual al correo"
          />
        </Field>
      </div>
      <Field label="Contraseña / app password">
        <input className={inputClass} name="imapPassword" type="password" />
      </Field>
      {state.error ? (
        <p className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {state.error}
        </p>
      ) : null}
      {state.ok ? (
        <p className="rounded border border-[var(--border)] bg-[var(--accent-soft)] px-3 py-2 text-sm text-[var(--ink)]">
          Casilla vinculada
          {state.mode === "mock" ? " (modo mock — imap-mail no activo)" : ""}.
        </p>
      ) : null}
      <button type="submit" className={btnPrimary} disabled={pending}>
        {pending ? "Vinculando…" : "Vincular casilla"}
      </button>
    </form>
  );
}
