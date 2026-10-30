import { redirect } from "next/navigation";
import { getAuthenticatedUserId, getSession } from "@/lib/session";

export const instant = false;

export default async function HomePage() {
  const session = await getSession();
  if (session) redirect("/dashboard");

  const userId = await getAuthenticatedUserId();
  redirect(userId ? "/seleccionar-empresa" : "/login");
}
