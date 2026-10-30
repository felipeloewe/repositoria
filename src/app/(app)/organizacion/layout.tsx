import { redirect } from "next/navigation";
import { canBrowseOrganization } from "@/lib/acl";
import { getSession } from "@/lib/session";

export const instant = false;

export default async function OrganizacionLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const session = await getSession();
  if (!session) {
    redirect("/login");
  }
  if (!canBrowseOrganization(session)) {
    redirect("/expedientes");
  }
  return children;
}
