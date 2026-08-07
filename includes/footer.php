  <footer class="app-foot text-center">
    <i class="bi bi-shield-plus"></i>
    เกณฑ์การแปลผลอ้างอิงสมาคมความดันโลหิตสูงแห่งประเทศไทย · ข้อมูลนี้ใช้เพื่อการติดตามเบื้องต้น ไม่ทดแทนคำวินิจฉัยของแพทย์
  </footer>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js"></script>
<script>
  // ลงทะเบียน Service Worker เพื่อรองรับ PWA / ใช้งานออฟไลน์
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('sw.js').catch(function () {});
    });
  }
</script>
</body>
</html>
