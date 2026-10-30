import { redirect } from "next/navigation";
import { AppShell } from "@/components/app-shell";
import {
  countUserEmpresas,
  getAuthenticatedUserId,
  getSession,
} from "@/lib/session";

export const instant = false;

export default async function TenantAppLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const session = await getSession();

  if (!session) {
    const userId = await getAuthenticatedUserId();
    redirect(userId ? "/seleccionar-empresa" : "/login");
  }

  const multiEmpresa = (await countUserEmpresas(session.userId)) > 1;

  return (
    <AppShell session={session} multiEmpresa={multiEmpresa}>
      {children}
    </AppShell>
  );
}
