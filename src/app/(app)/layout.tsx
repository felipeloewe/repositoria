import { redirect } from "next/navigation";
import { AppShell } from "@/components/app-shell";
import { getStubSession } from "@/lib/auth-stub";

export default function TenantAppLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const session = getStubSession();

  if (!session) {
    redirect("/login");
  }

  return <AppShell session={session}>{children}</AppShell>;
}
