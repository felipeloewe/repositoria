import { NextRequest, NextResponse } from "next/server";

export const instant = false;

/**
 * Inbound stub for imap-mail push events
 * (`thread.certificate.ready`, `attachment.exported`).
 *
 * Primary ingest path in MVP is UI-driven (Correo → Guardar en repositorio).
 * This endpoint acknowledges connectivity / webhook secret checks.
 *
 * Set REPOSITORIA_WEBHOOK_SECRET to require header `x-repositoria-webhook-secret`.
 */
export async function POST(request: NextRequest) {
  const expected = process.env.REPOSITORIA_WEBHOOK_SECRET?.trim();
  if (expected) {
    const got = request.headers.get("x-repositoria-webhook-secret");
    if (got !== expected) {
      return NextResponse.json(
        { ok: false, error: "Invalid webhook secret" },
        { status: 401 },
      );
    }
  }

  let payload: unknown;
  try {
    payload = await request.json();
  } catch {
    return NextResponse.json({ ok: false, error: "Invalid JSON" }, { status: 400 });
  }

  const event =
    payload &&
    typeof payload === "object" &&
    "event" in payload &&
    typeof (payload as { event: unknown }).event === "string"
      ? (payload as { event: string }).event
      : "unknown";

  console.info("[imap-mail webhook]", event, JSON.stringify(payload).slice(0, 500));

  return NextResponse.json({
    ok: true,
    data: {
      received: true,
      event,
      note: "Stub — use Correo UI to save adjuntos/certificado into Documento with ACL.",
    },
  });
}

export async function GET() {
  return NextResponse.json({
    ok: true,
    data: {
      service: "repositoria",
      webhook: "/api/webhooks/imap-mail",
      accepts: ["thread.certificate.ready", "attachment.exported"],
    },
  });
}
