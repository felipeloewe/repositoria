import { createWriteStream, existsSync, mkdirSync } from "fs";
import { unlink, readFile, writeFile } from "fs/promises";
import path from "path";
import { pipeline } from "stream/promises";
import { Readable } from "stream";

/** Local disk root for MVP document blobs. Override with STORAGE_ROOT. */
export function storageRoot(): string {
  const configured = process.env.STORAGE_ROOT?.trim();
  if (configured) return configured;
  return path.join(/* turbopackIgnore: true */ process.cwd(), "storage", "documents");
}

export function absoluteStoragePath(storageKey: string): string {
  const root = path.resolve(/* turbopackIgnore: true */ storageRoot());
  const full = path.resolve(/* turbopackIgnore: true */ root, storageKey);
  if (!full.startsWith(root + path.sep) && full !== root) {
    throw new Error("storageKey fuera de STORAGE_ROOT");
  }
  return full;
}

export async function saveDocumentFile(opts: {
  empresaId: string;
  documentoId: string;
  nombreArchivo: string;
  data: Buffer;
}): Promise<{ storageKey: string; tamanioBytes: number }> {
  const safeName = opts.nombreArchivo.replace(/[^a-zA-Z0-9._-]+/g, "_");
  const storageKey = path.join(opts.empresaId, opts.documentoId, safeName);
  const full = absoluteStoragePath(storageKey);
  mkdirSync(path.dirname(full), { recursive: true });
  await writeFile(full, opts.data);
  return { storageKey, tamanioBytes: opts.data.length };
}

export async function readDocumentFile(storageKey: string): Promise<Buffer> {
  const full = absoluteStoragePath(storageKey);
  if (!existsSync(full)) {
    throw new Error("Archivo no encontrado en storage");
  }
  return readFile(full);
}

export async function deleteDocumentFile(storageKey: string): Promise<void> {
  const full = absoluteStoragePath(storageKey);
  if (existsSync(full)) {
    await unlink(full);
  }
}

/** Stream helper if needed later (S3-compatible stub can replace this module). */
export async function writeStreamToStorage(
  storageKey: string,
  stream: Readable,
): Promise<void> {
  const full = absoluteStoragePath(storageKey);
  mkdirSync(path.dirname(full), { recursive: true });
  await pipeline(stream, createWriteStream(full));
}
