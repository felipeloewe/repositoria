import { NextRequest, NextResponse } from "next/server";
import {
  SESSION_COOKIE,
  USER_COOKIE,
  loadSessionByMembresiaId,
} from "@/lib/session";

/**
 * Optional DEV-ONLY impersonation.
 * Enabled only when DEV_AUTH_SWITCHER=1 — not part of the product login flow.
 */
export async function GET(request: NextRequest) {
  if (process.env.DEV_AUTH_SWITCHER !== "1") {
    return NextResponse.json(
      { error: "Demo switcher disabled. Set DEV_AUTH_SWITCHER=1 for local debug." },
      { status: 404 },
    );
  }

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
  response.cookies.set(USER_COOKIE, session.userId, {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
  });
  response.cookies.set(SESSION_COOKIE, membresiaId, {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
  });
  return response;
}
