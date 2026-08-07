/* Service Worker สำหรับ BP Record PWA
 * - หน้า PHP + ไฟล์ CSS/JS ในเว็บ: network-first (ได้ของใหม่เสมอ, ออฟไลน์ค่อยใช้ cache)
 * - รูป/ฟอนต์/CDN: cache-first เพื่อความเร็ว
 * บั๊มเวอร์ชันแคชทุกครั้งที่แก้ไฟล์นี้ เพื่อล้างของเก่า
 */
const CACHE = 'bp-record-v3';
const APP_SHELL = [
  'dashboard.php', 'index.php', 'chart.php', 'phases.php', 'health.php',
  'assets/icon-192.png', 'assets/icon-512.png', 'manifest.json',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(APP_SHELL).catch(() => {})).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return; // ไม่แคช POST

  const url = new URL(req.url);
  const sameOrigin = url.origin === location.origin;
  const isPage = req.mode === 'navigate' || url.pathname.endsWith('.php');
  const isAsset = sameOrigin && /\.(css|js)$/i.test(url.pathname);

  if (isPage || isAsset) {
    // network-first: ได้ของล่าสุดเสมอ (แก้ปัญหา CSS/JS ค้างของเก่า)
    e.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
          return res;
        })
        .catch(() => caches.match(req).then((m) => m || (isPage ? caches.match('dashboard.php') : undefined)))
    );
  } else {
    // cache-first สำหรับรูป/ฟอนต์/CDN
    e.respondWith(
      caches.match(req).then((m) => m || fetch(req).then((res) => {
        if (res.ok && (sameOrigin || url.protocol === 'https:')) {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
        }
        return res;
      }).catch(() => m))
    );
  }
});
