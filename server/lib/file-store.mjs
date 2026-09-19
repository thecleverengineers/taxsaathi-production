import crypto from 'node:crypto';
import fs from 'node:fs';
import fsPromises from 'node:fs/promises';
import path from 'node:path';
import { Readable } from 'node:stream';
import { GridFSBucket, ObjectId } from 'mongodb';

const projectRoot = path.resolve(process.cwd());
const localRoots = {
  documents: path.resolve(projectRoot, 'server/storage/documents'),
  payment: path.resolve(projectRoot, 'server/storage/payment')
};
const legacyRoot = path.resolve(projectRoot, 'legacy/taxsaathi');

function safeFilename(value) {
  const cleaned = String(value || 'upload.bin').replace(/[^a-z0-9._-]/gi, '_').slice(-180);
  return cleaned || 'upload.bin';
}

function bucketFor(store) {
  if (!store?.isMongo || !store.database) return null;
  return new GridFSBucket(store.database, { bucketName: 'taxsaathi_files' });
}

function asObjectId(value) {
  const raw = String(value || '').replace(/^gridfs:/, '');
  return /^[a-f\d]{24}$/i.test(raw) ? new ObjectId(raw) : null;
}

export function isGridFsReference(value) {
  return Boolean(asObjectId(value));
}

export async function saveUpload(store, file, { folder = 'documents', metadata = {} } = {}) {
  if (!file?.buffer) throw new Error('Upload data is missing.');
  const originalName = safeFilename(file.originalname);
  const mimeType = String(file.mimetype || 'application/octet-stream');
  const sizeBytes = Number(file.size || file.buffer.length || 0);
  const bucket = bucketFor(store);

  if (bucket) {
    const uploadStream = bucket.openUploadStream(originalName, {
      contentType: mimeType,
      metadata: { ...metadata, original_name: originalName, folder }
    });
    await new Promise((resolve, reject) => {
      Readable.from(file.buffer).pipe(uploadStream).once('finish', resolve).once('error', reject);
    });
    const fileId = uploadStream.id.toString();
    return {
      storage: 'gridfs',
      file_id: fileId,
      stored_name: `gridfs:${fileId}`,
      original_name: originalName,
      mime_type: mimeType,
      size_bytes: sizeBytes
    };
  }

  const root = localRoots[folder] || localRoots.documents;
  await fsPromises.mkdir(root, { recursive: true });
  const filename = `${Date.now()}_${crypto.randomUUID()}_${originalName}`;
  await fsPromises.writeFile(path.join(root, filename), file.buffer, { flag: 'wx' });
  return {
    storage: 'local',
    file_id: null,
    stored_name: filename,
    original_name: originalName,
    mime_type: mimeType,
    size_bytes: sizeBytes
  };
}

export async function streamStoredFile(store, reference, response, {
  localFolder = 'documents',
  downloadName = '',
  fallbackRoot = null
} = {}) {
  const objectId = asObjectId(reference);
  const bucket = bucketFor(store);
  if (objectId && bucket) {
    const metadata = await bucket.find({ _id: objectId }).next();
    if (!metadata) return false;
    response.setHeader('Content-Type', metadata.contentType || 'application/octet-stream');
    response.setHeader('Content-Length', String(metadata.length || 0));
    response.setHeader('Content-Disposition', `inline; filename="${safeFilename(downloadName || metadata.filename)}"`);
    await new Promise((resolve, reject) => bucket.openDownloadStream(objectId).once('end', resolve).once('error', reject).pipe(response));
    return true;
  }

  const rawReference = String(reference || '').replace(/^\/+/, '');
  const localRoot = path.resolve(fallbackRoot || localRoots[localFolder] || localRoots.documents);
  const candidates = [path.resolve(localRoot, path.basename(rawReference))];
  if (!fallbackRoot && rawReference.startsWith('storage/')) candidates.push(path.resolve(legacyRoot, rawReference));
  if (!fallbackRoot && rawReference.startsWith('uploads/')) candidates.push(path.resolve(legacyRoot, 'public', rawReference));
  if (!fallbackRoot && rawReference.includes('/')) candidates.push(path.resolve(legacyRoot, 'storage', rawReference));
  for (const target of candidates) {
    const allowedRoot = target.startsWith(`${localRoot}${path.sep}`) || target.startsWith(`${legacyRoot}${path.sep}`);
    if (!allowedRoot) continue;
    try {
      const stat = await fsPromises.stat(target);
      const filename = path.basename(target);
      response.setHeader('Content-Type', 'application/octet-stream');
      response.setHeader('Content-Length', String(stat.size));
      response.setHeader('Content-Disposition', `inline; filename="${safeFilename(downloadName || filename)}"`);
      await new Promise((resolve, reject) => fs.createReadStream(target).once('end', resolve).once('error', reject).pipe(response));
      return true;
    } catch {
      // Continue through legacy path conventions.
    }
  }
  return false;
}

export async function deleteStoredFile(store, reference) {
  const objectId = asObjectId(reference);
  const bucket = bucketFor(store);
  if (objectId && bucket) {
    await bucket.delete(objectId).catch(() => {});
    return true;
  }
  return false;
}

export { localRoots };
