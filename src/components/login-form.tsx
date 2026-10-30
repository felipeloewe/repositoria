"use client";

import { useActionState } from "react";
import {
  loginWithPassword,
  type LoginState,
} from "@/app/actions/auth";
import { btnPrimary, Field, inputClass } from "@/components/ui";

const initial: LoginState = {};

export function LoginForm({ initialError }: { initialError?: string }) {
  const [state, action, pending] = useActionState(loginWithPassword, initial);
  const error = state.error ?? initialError;

  return (
    <form action={action} className="space-y-4">
      <Field label="Correo">
        <input
          className={inputClass}
          type="email"
          name="email"
          autoComplete="username"
          required
          placeholder="admin@repositoria.local"
        />
      </Field>
      <Field label="Contraseña">
        <input
          className={inputClass}
          type="password"
          name="password"
          autoComplete="current-password"
          required
        />
      </Field>
      {error ? (
        <p className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {error}
        </p>
      ) : null}
      <button type="submit" className={`${btnPrimary} w-full`} disabled={pending}>
        {pending ? "Ingresando…" : "Ingresar"}
      </button>
    </form>
  );
}
