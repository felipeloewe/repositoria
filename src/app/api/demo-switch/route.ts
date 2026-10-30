import { NextRequest, NextResponse } from "next/server";
import { SESSION_COOKIE, loadSessionByMembresiaId } from "@/lib/session";

/** Auth-stub helper: GET /api/demo-switch?membresiaId=…&next=/dashboard */
export async function GET(request: NextRequest) {
  const membresiaId = request.nextUrl.searchParams.get("membresiaId");
  const next = request.nextUrl.searchParams.get("next") || "/dashboard";

  if (!membresiaId) {
    return NextResponse.redirect(new URL("/login", request.url));
  }

  const session = await loadSessionByMembresiaId(membresiaId);
  if (!session) {
    return NextResponse.redirect(new URL("/login", request.url));
  }

  const safeNext = next.startsWith("/") ? next : "/dashboard";
  const response = NextResponse.redirect(new URL(safeNext, request.url));
  response.cookies.set(SESSION_COOKIE, membresiaId, {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
  });
  return response;
}
