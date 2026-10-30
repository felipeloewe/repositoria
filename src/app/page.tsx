import { redirect } from "next/navigation";
import { getStubSession } from "@/lib/auth-stub";

export default function HomePage() {
  const session = getStubSession();
  redirect(session ? "/dashboard" : "/login");
}
