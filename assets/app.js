// ===== ฟอร์มเพิ่ม/แก้ไข (Bootstrap modal) =====
const FIELDS = ['m1_sys','m1_dia','m1_hr','m2_sys','m2_dia','m2_hr',
                'n1_sys','n1_dia','n1_hr','n2_sys','n2_dia','n2_hr','weight','height'];

function resetForm() {
  const form = document.getElementById('bpForm');
  if (form) form.reset();
  const id = document.getElementById('f-id');
  if (id) id.value = '';
  const title = document.getElementById('formTitle');
  if (title) title.innerHTML = '<i class="bi bi-plus-circle"></i> เพิ่มบันทึกใหม่';
}

function editRow(r) {
  document.getElementById('f-id').value = r.id || '';
  document.getElementById('f-date').value = r.record_date || '';
  document.getElementById('f-note').value = r.note || '';
  FIELDS.forEach(f => {
    const el = document.getElementById('f-' + f);
    if (el) el.value = (r[f] === null || r[f] === undefined) ? '' : r[f];
  });
  document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil-square"></i> แก้ไขบันทึก';
  const modal = new bootstrap.Modal(document.getElementById('formModal'));
  modal.show();
}

// ===== ช่วงการรักษา (phases) =====
function resetPhase() {
  const f = document.querySelector('#phaseModal form');
  if (f) f.reset();
  const id = document.getElementById('p-id'); if (id) id.value = '';
  const t = document.getElementById('phaseTitle'); if (t) t.innerHTML = '<i class="bi bi-plus-circle"></i> เพิ่มช่วง';
}
function editPhase(p) {
  document.getElementById('p-id').value = p.id || '';
  document.getElementById('p-name').value = p.name || '';
  document.getElementById('p-start').value = p.start_date || '';
  document.getElementById('p-note').value = p.note || '';
  document.querySelectorAll('#p-colors input[name=color]').forEach(r => { r.checked = (r.value === p.color); });
  document.getElementById('phaseTitle').innerHTML = '<i class="bi bi-pencil-square"></i> แก้ไขช่วง';
  new bootstrap.Modal(document.getElementById('phaseModal')).show();
}

// ===== ไฮไลต์จุดตามช่วง (หน้ากราฟ scatter) =====
function highlightPhase(id, el) {
  document.querySelectorAll('.phase-chip').forEach(c => c.classList.remove('active'));
  if (el) el.classList.add('active');
  document.querySelectorAll('.scatter-pt').forEach(c => {
    if (id === 'all') {
      c.style.opacity = '1';
      c.setAttribute('fill', c.dataset.base || '#2c5c7a');
    } else if (c.dataset.phase === String(id)) {
      c.style.opacity = '1';
      c.setAttribute('fill', c.dataset.color || '#57b894');
    } else {
      c.style.opacity = '0.12';
      c.setAttribute('fill', c.dataset.base || '#2c5c7a');
    }
  });
}

// ===== ค้นหาในตาราง =====
document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('tableSearch');
  if (search) {
    search.addEventListener('input', () => {
      const q = search.value.trim().toLowerCase();
      document.querySelectorAll('#bpTable tbody tr[data-date]').forEach(tr => {
        const d = (tr.getAttribute('data-date') || '').toLowerCase();
        tr.style.display = d.includes(q) ? '' : 'none';
      });
    });
  }

  // ===== สลับโหมดสว่าง/มืด =====
  const toggle = document.getElementById('themeToggle');
  const saved = localStorage.getItem('bp-theme');
  if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
  updateThemeIcon();
  if (toggle) {
    toggle.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-bs-theme');
      const next = cur === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', next);
      localStorage.setItem('bp-theme', next);
      updateThemeIcon();
    });
  }
});

function updateThemeIcon() {
  const toggle = document.getElementById('themeToggle');
  if (!toggle) return;
  const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  toggle.innerHTML = dark ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-stars-fill"></i>';
}
