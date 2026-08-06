/* Service Worker สำหรับ BP Record PWA
 * - หน้า PHP (มีข้อมูลจาก DB): network-first แล้ว fallback เป็น cache เมื่อออฟไลน์
 * - ไฟล์สแตติก (css/js/รูป/ฟอนต์/CDN): cache-first เพื่อความเร็ว
 */
const CACHE = 'bp-record-v1';
const APP_SHELL = [
  'dashboard.php',
  'index.php',
  'chart.php',
  'phases.php',
  'health.php',
  'assets/style.css',
  'assets/app.js',
  'assets/icon-192.png',
  'assets/icon-512.png',
  'manifest.json',
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
  if (req.method !== 'GET') return; // ไม่แคชการบันทึกข้อมูล (POST)

  const url = new URL(req.url);
  const isPage = req.mode === 'navigate' || url.pathname.endsWith('.php');

  if (isPage) {
    // network-first: ได้ข้อมูลล่าสุดเสมอ, ออฟไลน์ค่อยใช้ cache
    e.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
          return res;
        })
        .catch(() => caches.match(req).then((m) => m || caches.match('dashboard.php')))
    );
  } else {
    // cache-first สำหรับไฟล์สแตติกและ CDN
    e.respondWith(
      caches.match(req).then((m) => m || fetch(req).then((res) => {
        if (res.ok && (url.origin === location.origin || url.protocol === 'https:')) {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
        }
        return res;
      }).catch(() => m))
    );
  }
});
