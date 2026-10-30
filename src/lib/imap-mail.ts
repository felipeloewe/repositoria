/**
 * HTTP client for the parallel imap-mail service (Repositoria Fase 3).
 * Env: IMAP_MAIL_BASE_URL (preferred) or IMAP_MAIL_URL.
 * When unset or unreachable, falls back to in-process mock fixtures so UI works offline.
 */

export type MailAccountSummary = {
  id: string;
  usuarioId: string;
  email: string;
  displayName: string | null;
  provider: "IMAP" | "GMAIL";
  imapHost?: string | null;
  imapPort?: number | null;
  syncStatus: string;
  syncError?: string | null;
  lastSyncedAt?: string | null;
  createdAt?: string;
};

export type MailThreadSummary = {
  id: string;
  accountId: string;
  rootMessageId: string;
  subject: string;
  participants: unknown;
  messageCount: number;
  lastMessageAt: string | null;
  certificatePath?: string | null;
  account?: { id: string; email: string; usuarioId: string };
  _count?: { messages: number };
};

export type MailAttachment = {
  id: string;
  filename: string;
  mimeType: string;
  size: number;
  contentId?: string | null;
};

export type MailMessage = {
  id: string;
  messageId: string;
  folder: string;
  fromAddr: string;
  toAddrs: string;
  ccAddrs?: string | null;
  subject: string;
  bodyText?: string | null;
  bodyHtml?: string | null;
  date: string;
  attachments: MailAttachment[];
};

export type MailThreadDetail = MailThreadSummary & {
  account: {
    id: string;
    email: string;
    usuarioId: string;
    provider: string;
  };
  messages: MailMessage[];
};

type JsonOk<T> = { ok: true; data: T };
type JsonErr = { ok: false; error: string };

function baseUrl(): string | null {
  const raw =
    process.env.IMAP_MAIL_BASE_URL?.trim() ||
    process.env.IMAP_MAIL_URL?.trim() ||
    "";
  if (!raw) return null;
  return raw.replace(/\/$/, "");
}

export function imapMailConfigured(): boolean {
  return !!baseUrl();
}

export type ImapMailMode = "live" | "mock";

export function imapMailMode(): ImapMailMode {
  return baseUrl() ? "live" : "mock";
}

async function mailFetch(
  path: string,
  init?: RequestInit,
): Promise<Response> {
  const base = baseUrl();
  if (!base) throw new Error("IMAP_MAIL_BASE_URL no configurado");
  const url = `${base}${path.startsWith("/") ? path : `/${path}`}`;
  return fetch(url, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(init?.body ? { "Content-Type": "application/json" } : {}),
      ...init?.headers,
    },
    cache: "no-store",
  });
}

async function parseJson<T>(res: Response): Promise<T> {
  const body = (await res.json()) as JsonOk<T> | JsonErr;
  if (!res.ok || !("ok" in body) || body.ok === false) {
    const err =
      body && typeof body === "object" && "error" in body
        ? String((body as JsonErr).error)
        : `imap-mail HTTP ${res.status}`;
    throw new Error(err);
  }
  return body.data;
}

/** Probe live service; false if unset or unhealthy. */
export async function imapMailHealth(): Promise<{
  ok: boolean;
  mode: ImapMailMode;
  detail?: string;
}> {
  const base = baseUrl();
  if (!base) {
    return {
      ok: true,
      mode: "mock",
      detail: "IMAP_MAIL_BASE_URL unset — using offline mock fixtures",
    };
  }
  try {
    const res = await fetch(`${base}/api/health`, {
      cache: "no-store",
      signal: AbortSignal.timeout(2500),
    });
    if (!res.ok) {
      return { ok: false, mode: "live", detail: `health ${res.status}` };
    }
    return { ok: true, mode: "live", detail: base };
  } catch (e) {
    return {
      ok: false,
      mode: "live",
      detail: e instanceof Error ? e.message : "unreachable",
    };
  }
}

// --- Mock fixtures (aligned with imap-mail seed:demo shape) ---

const MOCK_ACCOUNT_ID = "mac_demo_repositoria";
const MOCK_THREAD_ID = "thr_demo_liquidacion";
const MOCK_ATTACH_ID = "att_demo_liquidacion";

function mockAccount(usuarioId: string): MailAccountSummary {
  return {
    id: MOCK_ACCOUNT_ID,
    usuarioId,
    email: "demo@repositoria.local",
    displayName: "Demo Repositoria (mock)",
    provider: "IMAP",
    imapHost: "imap.example.local",
    imapPort: 993,
    syncStatus: "IDLE",
    lastSyncedAt: "2026-03-15T16:05:00.000Z",
    createdAt: "2026-03-01T00:00:00.000Z",
  };
}

function mockThreadSummary(usuarioId: string): MailThreadSummary {
  return {
    id: MOCK_THREAD_ID,
    accountId: MOCK_ACCOUNT_ID,
    rootMessageId: "root.demo@repositoria.local",
    subject: "Liquidación marzo — revisión",
    participants: [
      "Ana Pérez <ana@empresa.cl>",
      "Contabilidad <conta@empresa.cl>",
    ],
    messageCount: 2,
    lastMessageAt: "2026-03-15T16:00:00.000Z",
    certificatePath: null,
    account: {
      id: MOCK_ACCOUNT_ID,
      email: "demo@repositoria.local",
      usuarioId,
    },
    _count: { messages: 2 },
  };
}

function mockThreadDetail(usuarioId: string): MailThreadDetail {
  const summary = mockThreadSummary(usuarioId);
  return {
    ...summary,
    account: {
      id: MOCK_ACCOUNT_ID,
      email: "demo@repositoria.local",
      usuarioId,
      provider: "IMAP",
    },
    messages: [
      {
        id: "msg_demo_1",
        messageId: "root.demo@repositoria.local",
        folder: "INBOX",
        fromAddr: "Ana Pérez <ana@empresa.cl>",
        toAddrs: "Contabilidad <conta@empresa.cl>",
        subject: "Liquidación marzo — revisión",
        bodyText:
          "Hola, adjunto borrador de liquidación de marzo para revisión del departamento.",
        date: "2026-03-15T10:00:00.000Z",
        attachments: [
          {
            id: MOCK_ATTACH_ID,
            filename: "liquidacion-marzo.pdf",
            mimeType: "application/pdf",
            size: 48,
          },
        ],
      },
      {
        id: "msg_demo_2",
        messageId: "reply.demo@repositoria.local",
        folder: "SENT",
        fromAddr: "Contabilidad <conta@empresa.cl>",
        toAddrs: "Ana Pérez <ana@empresa.cl>",
        subject: "Re: Liquidación marzo — revisión",
        bodyText:
          "Recibido. Queda archivado en el repositorio del departamento.",
        date: "2026-03-15T16:00:00.000Z",
        attachments: [],
      },
    ],
  };
}

function mockAttachmentPdf(): Buffer {
  return Buffer.from(
    "%PDF-1.4\n% mock attachment Repositoria Fase 3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n",
  );
}

function mockCertificatePdf(subject: string): Buffer {
  const safe = subject.replace(/[^\w\s\-áéíóúñÁÉÍÓÚÑ]/gi, "").slice(0, 80);
  return Buffer.from(
    `%PDF-1.4\n% Certificado de hilo — ${safe}\n1 0 obj<< /Type /Catalog >>endobj\ntrailer<<>>\n%%EOF\n`,
  );
}

async function withFallback<T>(
  live: () => Promise<T>,
  mock: () => T | Promise<T>,
): Promise<{ data: T; mode: ImapMailMode }> {
  if (!baseUrl()) {
    return { data: await mock(), mode: "mock" };
  }
  try {
    return { data: await live(), mode: "live" };
  } catch {
    return { data: await mock(), mode: "mock" };
  }
}

export async function listMailAccounts(usuarioId: string) {
  return withFallback(
    async () => {
      const res = await mailFetch(
        `/api/accounts?usuarioId=${encodeURIComponent(usuarioId)}`,
      );
      return parseJson<MailAccountSummary[]>(res);
    },
    () => [mockAccount(usuarioId)],
  );
}

export async function createMailAccount(input: {
  usuarioId: string;
  email: string;
  displayName?: string;
  provider?: "IMAP" | "GMAIL";
  imapHost?: string;
  imapPort?: number;
  imapSecure?: boolean;
  imapUser?: string;
  imapPassword?: string;
}) {
  if (!baseUrl()) {
    // Mock create — echo back a linked account
    return {
      data: {
        ...mockAccount(input.usuarioId),
        email: input.email,
        displayName: input.displayName ?? null,
        provider: input.provider ?? "IMAP",
        imapHost: input.imapHost ?? null,
        imapPort: input.imapPort ?? 993,
      } satisfies MailAccountSummary,
      mode: "mock" as const,
    };
  }
  const res = await mailFetch("/api/accounts", {
    method: "POST",
    body: JSON.stringify(input),
  });
  const data = await parseJson<MailAccountSummary>(res);
  return { data, mode: "live" as const };
}

export async function listMailThreads(usuarioId: string) {
  return withFallback(
    async () => {
      const res = await mailFetch(
        `/api/threads?usuarioId=${encodeURIComponent(usuarioId)}`,
      );
      return parseJson<MailThreadSummary[]>(res);
    },
    () => [mockThreadSummary(usuarioId)],
  );
}

export async function getMailThread(threadId: string, usuarioId: string) {
  return withFallback(
    async () => {
      const res = await mailFetch(`/api/threads/${encodeURIComponent(threadId)}`);
      return parseJson<MailThreadDetail>(res);
    },
    () => {
      if (threadId !== MOCK_THREAD_ID) {
        throw new Error("Hilo no encontrado (mock)");
      }
      return mockThreadDetail(usuarioId);
    },
  );
}

export async function generateThreadCertificate(
  threadId: string,
  opts?: { pushToRepositoria?: boolean; suggestedDepartamentoId?: string },
) {
  if (!baseUrl()) {
    return {
      data: {
        threadId,
        certificatePath: `mock/${threadId}.pdf`,
        certificateGeneratedAt: new Date().toISOString(),
        downloadPath: `/api/threads/${threadId}/certificate`,
        webhook: null,
      },
      mode: "mock" as const,
      bytes: mockCertificatePdf("Liquidación marzo — revisión"),
    };
  }
  const res = await mailFetch(`/api/threads/${encodeURIComponent(threadId)}/certificate`, {
    method: "POST",
    body: JSON.stringify(opts ?? {}),
  });
  const data = await parseJson<{
    threadId: string;
    certificatePath: string | null;
    certificateGeneratedAt: string | null;
    downloadPath: string;
    webhook?: unknown;
  }>(res);
  return { data, mode: "live" as const, bytes: null as Buffer | null };
}

export async function downloadThreadCertificate(
  threadId: string,
): Promise<{ bytes: Buffer; filename: string; mode: ImapMailMode }> {
  if (!baseUrl()) {
    return {
      bytes: mockCertificatePdf("Liquidación marzo — revisión"),
      filename: `certificado-hilo-${threadId}.pdf`,
      mode: "mock",
    };
  }
  const res = await mailFetch(
    `/api/threads/${encodeURIComponent(threadId)}/certificate`,
  );
  if (!res.ok) {
    // try generate then download
    await generateThreadCertificate(threadId);
    const retry = await mailFetch(
      `/api/threads/${encodeURIComponent(threadId)}/certificate`,
    );
    if (!retry.ok) throw new Error(`Certificate download failed (${retry.status})`);
    const bytes = Buffer.from(await retry.arrayBuffer());
    return { bytes, filename: `certificado-hilo-${threadId}.pdf`, mode: "live" };
  }
  const bytes = Buffer.from(await res.arrayBuffer());
  return { bytes, filename: `certificado-hilo-${threadId}.pdf`, mode: "live" };
}

export async function downloadMailAttachment(
  attachmentId: string,
): Promise<{ bytes: Buffer; filename: string; mimeType: string; mode: ImapMailMode }> {
  if (!baseUrl()) {
    if (attachmentId !== MOCK_ATTACH_ID) {
      throw new Error("Adjunto no encontrado (mock)");
    }
    return {
      bytes: mockAttachmentPdf(),
      filename: "liquidacion-marzo.pdf",
      mimeType: "application/pdf",
      mode: "mock",
    };
  }
  const res = await mailFetch(
    `/api/attachments/${encodeURIComponent(attachmentId)}/export`,
  );
  if (!res.ok) throw new Error(`Attachment export failed (${res.status})`);
  const cd = res.headers.get("Content-Disposition") ?? "";
  const match = /filename="?([^";]+)"?/i.exec(cd);
  const filename = match?.[1] ?? `adjunto-${attachmentId}`;
  const mimeType = res.headers.get("Content-Type") ?? "application/octet-stream";
  const bytes = Buffer.from(await res.arrayBuffer());
  return { bytes, filename, mimeType, mode: "live" };
}

export { MOCK_THREAD_ID, MOCK_ATTACH_ID, MOCK_ACCOUNT_ID };
