import { NextResponse } from "next/server";
import { PROFILE_NOTICE_COOKIE } from "@/lib/session";

/** Clears the one-shot profile-switch banner cookie without an RSC refresh. */
export async function POST() {
  const res = NextResponse.json({ ok: true });
  res.cookies.set(PROFILE_NOTICE_COOKIE, "", {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
    maxAge: 0,
  });
  return res;
}
