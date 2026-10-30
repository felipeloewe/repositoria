import { createHash, timingSafeEqual } from "crypto";

/** Lightweight hash for local stub auth. Replace with bcrypt/argon2 later. */
export function hashPassword(password: string): string {
  return createHash("sha256")
    .update(`repositoria:v1:${password}`)
    .digest("hex");
}

export function verifyPassword(
  password: string,
  passwordHash: string | null | undefined,
): boolean {
  if (!passwordHash) return false;
  const incoming = Buffer.from(hashPassword(password), "utf8");
  const stored = Buffer.from(passwordHash, "utf8");
  if (incoming.length !== stored.length) return false;
  return timingSafeEqual(incoming, stored);
}
