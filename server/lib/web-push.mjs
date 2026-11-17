import crypto from 'node:crypto';

const PUSH_HOSTS = new Set([
  'fcm.googleapis.com',
  'fcmregistrations.googleapis.com',
  'android.googleapis.com',
  'web.push.apple.com',
  'push.services.mozilla.com',
  'updates.push.services.mozilla.com'
]);

let cachedConfig;

function decodeBase64Url(value) {
  const text = String(value || '').trim();
  if (!/^[A-Za-z0-9_-]+$/.test(text)) return null;
  return Buffer.from(text, 'base64url');
}

function encodeBase64Url(value) {
  return Buffer.from(value).toString('base64url');
}

export function isTrustedPushEndpoint(value) {
  try {
    const endpoint = new URL(String(value || ''));
    const host = endpoint.hostname.toLowerCase();
    const trustedHost = PUSH_HOSTS.has(host) || host.endsWith('.push.services.mozilla.com');
    return endpoint.protocol === 'https:' && !endpoint.username && !endpoint.password &&
      (!endpoint.port || endpoint.port === '443') && trustedHost;
  } catch {
    return false;
  }
}

export function normalizeWebPushSubscription(value) {
  if (!value || typeof value !== 'object') return null;
  const endpoint = String(value.endpoint || '').trim();
  const keys = value.keys && typeof value.keys === 'object' ? value.keys : {};
  const p256dh = String(keys.p256dh || '').trim();
  const auth = String(keys.auth || '').trim();
  if (!isTrustedPushEndpoint(endpoint)) return null;
  const publicKey = decodeBase64Url(p256dh);
  const authSecret = decodeBase64Url(auth);
  if (!publicKey || publicKey.length !== 65 || publicKey[0] !== 4 ||
      !authSecret || authSecret.length < 16 || authSecret.length > 64) return null;
  return { endpoint, keys: { p256dh, auth } };
}

function getConfig() {
  if (cachedConfig !== undefined) return cachedConfig;
  const publicKey = String(process.env.VAPID_PUBLIC_KEY || '').trim();
  const privateKey = String(process.env.VAPID_PRIVATE_KEY || '').trim();
  const publicBytes = decodeBase64Url(publicKey);
  const privateBytes = decodeBase64Url(privateKey);
  if (!publicBytes || publicBytes.length !== 65 || publicBytes[0] !== 4 ||
      !privateBytes || privateBytes.length !== 32) {
    cachedConfig = null;
    return cachedConfig;
  }
  try {
    const jwk = {
      kty: 'EC',
      crv: 'P-256',
      x: encodeBase64Url(publicBytes.subarray(1, 33)),
      y: encodeBase64Url(publicBytes.subarray(33, 65)),
      d: privateKey
    };
    cachedConfig = {
      publicKey,
      privateKey: crypto.createPrivateKey({ key: jwk, format: 'jwk' }),
      subject: String(process.env.VAPID_SUBJECT || 'mailto:support@taxsaathi.in').trim()
    };
  } catch {
    cachedConfig = null;
  }
  return cachedConfig;
}

export function getVapidPublicKey() {
  return getConfig()?.publicKey || '';
}

export function isWebPushConfigured() {
  return Boolean(getConfig());
}

function createVapidToken(endpoint, config) {
  const audience = new URL(endpoint).origin;
  const header = encodeBase64Url(JSON.stringify({ typ: 'JWT', alg: 'ES256' }));
  const claims = encodeBase64Url(JSON.stringify({
    aud: audience,
    exp: Math.floor(Date.now() / 1000) + 12 * 60 * 60,
    sub: config.subject
  }));
  const unsigned = header + '.' + claims;
  const signature = crypto.sign('sha256', Buffer.from(unsigned), {
    key: config.privateKey,
    dsaEncoding: 'ieee-p1363'
  });
  return unsigned + '.' + encodeBase64Url(signature);
}

function encryptPayload(subscription, payload) {
  const clientPublicKey = decodeBase64Url(subscription.keys.p256dh);
  const authSecret = decodeBase64Url(subscription.keys.auth);
  const serverKey = crypto.createECDH('prime256v1');
  serverKey.generateKeys();
  const serverPublicKey = serverKey.getPublicKey(null, 'uncompressed');
  const sharedSecret = serverKey.computeSecret(clientPublicKey);
  const keyInfo = Buffer.concat([
    Buffer.from('WebPush: info\0', 'utf8'),
    clientPublicKey,
    serverPublicKey
  ]);
  const inputKeyMaterial = Buffer.from(crypto.hkdfSync('sha256', sharedSecret, authSecret, keyInfo, 32));
  const salt = crypto.randomBytes(16);
  const contentKey = Buffer.from(crypto.hkdfSync(
    'sha256',
    inputKeyMaterial,
    salt,
    Buffer.from('Content-Encoding: aes128gcm\0', 'utf8'),
    16
  ));
  const nonce = Buffer.from(crypto.hkdfSync(
    'sha256',
    inputKeyMaterial,
    salt,
    Buffer.from('Content-Encoding: nonce\0', 'utf8'),
    12
  ));
  const plaintext = Buffer.concat([Buffer.from(JSON.stringify(payload), 'utf8'), Buffer.from([2])]);
  const recordSize = 4096;
  const headerSize = 16 + 4 + 1 + serverPublicKey.length;
  if (plaintext.length + 16 + headerSize > recordSize) throw new Error('Push payload is too large.');
  const cipher = crypto.createCipheriv('aes-128-gcm', contentKey, nonce);
  const ciphertext = Buffer.concat([cipher.update(plaintext), cipher.final(), cipher.getAuthTag()]);
  const size = Buffer.alloc(4);
  size.writeUInt32BE(recordSize, 0);
  return Buffer.concat([salt, size, Buffer.from([serverPublicKey.length]), serverPublicKey, ciphertext]);
}

async function sendOne(subscription, payload, config) {
  const body = encryptPayload(subscription, payload);
  const response = await fetch(subscription.endpoint, {
    method: 'POST',
    headers: {
      TTL: '86400',
      Urgency: 'normal',
      'Content-Type': 'application/octet-stream',
      'Content-Encoding': 'aes128gcm',
      Authorization: 'vapid t=' + createVapidToken(subscription.endpoint, config) + ', k=' + config.publicKey
    },
    body
  });
  return { expired: response.status === 404 || response.status === 410, ok: response.ok };
}

async function targetUserIds(store, userId) {
  if (Number(userId) > 0) return [Number(userId)];
  const roles = await store.find('roles', {});
  const roleIds = roles
    .filter((role) => ['admin', 'manager'].includes(String(role.slug || '').toLowerCase()))
    .map((role) => Number(role.id))
    .filter((id) => Number.isFinite(id) && id > 0);
  if (!roleIds.length) return [];
  const [users, assignments] = await Promise.all([
    store.find('users', { role_id: { $in: roleIds } }),
    store.find('user_roles', { role_id: { $in: roleIds } })
  ]);
  return [...new Set([
    ...users.map((user) => Number(user.id)),
    ...assignments.map((row) => Number(row.user_id))
  ].filter((id) => Number.isFinite(id) && id > 0))];
}

export async function dispatchWebPushNotifications(store, notification) {
  const config = getConfig();
  if (!config) return;
  const userIds = await targetUserIds(store, notification.userId);
  if (!userIds.length) return;
  const subscriptions = await store.find('user_push_subscriptions', { user_id: { $in: userIds } });
  const url = String(notification.url || '').startsWith('/') && !String(notification.url || '').startsWith('//')
    ? String(notification.url)
    : '/notifications';
  const payload = {
    title: String(notification.title || 'TaxSaathi').slice(0, 120),
    body: String(notification.message || 'You have a new update.').slice(0, 280),
    url,
    severity: String(notification.severity || 'info'),
    order_id: notification.orderId || null,
    tag: 'taxsaathi-' + (notification.orderId || Date.now()) + '-' + Math.random().toString(36).slice(2, 7)
  };
  await Promise.all(subscriptions.map(async (subscription) => {
    try {
      const result = await sendOne(subscription, payload, config);
      if (result.expired && subscription.id) {
        await store.remove('user_push_subscriptions', { id: subscription.id });
      }
    } catch {
      // Push delivery must not interrupt the task or payment action that created it.
    }
  }));
}
