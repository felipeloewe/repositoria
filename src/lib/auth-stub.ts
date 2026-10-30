/**
 * Auth stub for Fase 1 scaffold.
 * Replace with real session/auth (credentials, OAuth, etc.) before production.
 */

export type StubSession = {
  userId: string;
  email: string;
  nombre: string;
  empresaId: string;
  empresaNombre: string;
  rol: string;
  sucursalId: string | null;
};

/** Demo session used when no real auth is wired yet. */
export const DEMO_SESSION: StubSession = {
  userId: "demo-user",
  email: "admin@repositoria.local",
  nombre: "Administrador Demo",
  empresaId: "demo-empresa",
  empresaNombre: "Empresa Demo",
  rol: "ADMINISTRADOR_MASTER",
  sucursalId: null,
};

export function getStubSession(): StubSession | null {
  // Always return demo session in scaffold so the app shell is browsable.
  // Set AUTH_STUB_DISABLED=1 to simulate logged-out state.
  if (process.env.AUTH_STUB_DISABLED === "1") {
    return null;
  }
  return DEMO_SESSION;
}

export function isAuthenticated(): boolean {
  return getStubSession() !== null;
}
