// ===== ฟอร์มเพิ่ม/แก้ไข การวัดรายครั้ง (Bootstrap modal) =====
const FIELDS = ['sys', 'dia', 'hr'];

function resetForm() {
  const form = document.getElementById('bpForm');
  if (form) form.reset();
  const id = document.getElementById('f-id');
  if (id) id.value = '';
  const title = document.getElementById('formTitle');
  if (title) title.innerHTML = '<i class="bi bi-plus-circle"></i> เพิ่มบันทึก';
  const del = document.getElementById('btnDelete');
  if (del) del.style.display = 'none';
}

function addForDay(date, period) {
  resetForm();
  const dt = document.getElementById('f-date');
  if (dt && date) dt.value = date;
  const per = document.getElementById('f-period');
  if (per && period) per.value = period;
  new bootstrap.Modal(document.getElementById('formModal')).show();
}

function editRow(r) {
  document.getElementById('f-id').value = r.id || '';
  document.getElementById('f-date').value = r.record_date || '';
  document.getElementById('f-note').value = r.note || '';
  const per = document.getElementById('f-period');
  if (per && r.period) per.value = r.period;
  FIELDS.forEach(f => {
    const el = document.getElementById('f-' + f);
    if (el) el.value = (r[f] === null || r[f] === undefined) ? '' : r[f];
  });
  document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil-square"></i> แก้ไขบันทึก';
  const del = document.getElementById('btnDelete');
  if (del) del.style.display = '';
  new bootstrap.Modal(document.getElementById('formModal')).show();
}

function deleteCurrent() {
  const id = document.getElementById('f-id').value;
  if (!id) return;
  if (!confirm('ต้องการลบการวัดนี้?')) return;
  document.getElementById('del-id').value = id;
  document.getElementById('deleteForm').submit();
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

// ===== ไฮไลต์จุดตามช่วง (เลือกได้หลายช่วง) =====
const activePhases = new Set();
function renderScatter() {
  const none = activePhases.size === 0;
  const chipAll = document.getElementById('chip-all');
  if (chipAll) chipAll.classList.toggle('active', none);
  document.querySelectorAll('.phase-chip[data-phase]').forEach(c => {
    c.classList.toggle('active', activePhases.has(c.dataset.phase));
  });
  document.querySelectorAll('.scatter-pt').forEach(c => {
    if (none) {
      c.style.opacity = '1';
      c.setAttribute('fill', c.dataset.base || '#2c5c7a');
    } else if (activePhases.has(c.dataset.phase)) {
      c.style.opacity = '1';
      c.setAttribute('fill', c.dataset.color || '#57b894');
    } else {
      c.style.opacity = '0.1';
      c.setAttribute('fill', c.dataset.base || '#2c5c7a');
    }
  });
}
function togglePhase(id, el) {
  id = String(id);
  if (activePhases.has(id)) activePhases.delete(id); else activePhases.add(id);
  renderScatter();
}
function clearPhases() { activePhases.clear(); renderScatter(); }

// ===== เลือกจอมอนิเตอร์ที่จะแสดง =====
function toggleMon(i, el) {
  const mon = document.querySelector('.bp-monitor[data-mon="' + i + '"]');
  if (!mon) return;
  const show = mon.classList.toggle('d-none') === false;
  el.classList.toggle('active', show);
}

// tooltip แสดงค่าเมื่อชี้/แตะจุด scatter
document.addEventListener('DOMContentLoaded', () => {
  const tip = document.getElementById('scatterTip');
  if (!tip) return;
  const box = tip.parentElement;
  const show = (c) => {
    const hr = c.dataset.hr ? ' · ♥ ' + c.dataset.hr : '';
    const ph = c.dataset.phasename ? '<br><span class="st-ph">' + c.dataset.phasename + '</span>' : '';
    tip.innerHTML = '<b>' + c.dataset.sys + '/' + c.dataset.dia + '</b> mmHg' + hr + '<br><span class="st-date">' + c.dataset.date + '</span>' + ph;
    const br = box.getBoundingClientRect();
    const cr = c.getBoundingClientRect();
    tip.style.left = (cr.left - br.left + cr.width / 2) + 'px';
    tip.style.top  = (cr.top - br.top - 8) + 'px';
    tip.classList.add('show');
    c.setAttribute('r', '7');
  };
  const hide = (c) => { tip.classList.remove('show'); if (c) c.setAttribute('r', '4.5'); };
  document.querySelectorAll('.scatter-pt').forEach(c => {
    c.addEventListener('mouseenter', () => show(c));
    c.addEventListener('mouseleave', () => hide(c));
    c.addEventListener('click', (e) => { e.stopPropagation(); show(c); });
  });
  box.addEventListener('click', () => hide(null));
});

// ===== Tooltip กลางสำหรับทุกกราฟ (hover/แตะ แล้วเห็นค่า) =====
(function () {
  let tip = null;
  const ensure = () => {
    if (!tip) { tip = document.createElement('div'); tip.className = 'g-tip'; document.body.appendChild(tip); }
    return tip;
  };
  const textOf = (el) => {
    if (!el || !el.getAttribute) return null;
    const d = el.getAttribute('data-tip');
    if (d) return d;
    const tag = (el.tagName || '').toLowerCase();
    if (['circle', 'rect', 'path', 'polyline', 'line'].includes(tag) && el.querySelector) {
      const t = el.querySelector('title');
      if (t) return t.textContent;
    }
    return null;
  };
  const move = (e) => {
    let el = e.target, txt = null, hop = 0;
    while (el && hop < 3) { txt = textOf(el); if (txt) break; el = el.parentElement; hop++; }
    const t = ensure();
    if (txt) {
      t.textContent = txt;
      t.style.left = e.clientX + 'px';
      t.style.top = (e.clientY - 14) + 'px';
      t.classList.add('show');
    } else {
      t.classList.remove('show');
    }
  };
  document.addEventListener('pointermove', move, { passive: true });
  document.addEventListener('pointerdown', move, { passive: true });
  document.addEventListener('pointerleave', () => { if (tip) tip.classList.remove('show'); });
  document.addEventListener('scroll', () => { if (tip) tip.classList.remove('show'); }, { passive: true });
})();

// ===== หน้าสุขภาพ: กรองตารางบันทึกตามชนิดค่า =====
function healthTab(metric, el) {
  document.querySelectorAll('.hl-table').forEach(() => {});
  document.querySelectorAll('.rec-tab').forEach(t => t.classList.remove('active'));
  if (el) el.classList.add('active');
  document.querySelectorAll('.hl-table tbody tr[data-metric]').forEach(tr => {
    tr.style.display = (metric === 'all' || tr.dataset.metric === metric) ? '' : 'none';
  });
}

// ===== แดชบอร์ด: กรองตารางการวัดล่าสุดตามช่วง =====
function recFilter(period, el) {
  document.querySelectorAll('.rec-tab').forEach(t => t.classList.remove('active'));
  if (el) el.classList.add('active');
  document.querySelectorAll('.rec-table tbody tr[data-period]').forEach(tr => {
    tr.style.display = (period === 'all' || tr.dataset.period === period) ? '' : 'none';
  });
}

// ===== Pixel chart: สลับปี =====
function showYear(y, el) {
  document.querySelectorAll('.year-chip').forEach(c => c.classList.remove('active'));
  if (el) el.classList.add('active');
  document.querySelectorAll('.pixel-wrap').forEach(w => w.classList.toggle('d-none', w.dataset.year !== y));
}

// ===== Widget แสดง/ซ่อน (เก็บใน localStorage) =====
function applyWidgets() {
  let hidden = [];
  try { hidden = JSON.parse(localStorage.getItem('bp-hidden-widgets') || '[]'); } catch (e) {}
  document.querySelectorAll('.widget[data-widget]').forEach(w => {
    w.style.display = hidden.includes(w.dataset.widget) ? 'none' : '';
  });
  document.querySelectorAll('[data-widget-toggle]').forEach(cb => {
    cb.checked = !hidden.includes(cb.dataset.widgetToggle);
  });
}
document.addEventListener('DOMContentLoaded', () => {
  applyWidgets();
  document.querySelectorAll('[data-widget-toggle]').forEach(cb => {
    cb.addEventListener('change', () => {
      let hidden = [];
      try { hidden = JSON.parse(localStorage.getItem('bp-hidden-widgets') || '[]'); } catch (e) {}
      const id = cb.dataset.widgetToggle;
      hidden = hidden.filter(x => x !== id);
      if (!cb.checked) hidden.push(id);
      localStorage.setItem('bp-hidden-widgets', JSON.stringify(hidden));
      applyWidgets();
    });
  });
});

// ===== ตรวจสุขภาพ (checkups) =====
const CK_FIELDS = ['checkup_date','hospital','doctor','weight','height','sbp','dbp','pulse','xray','ekg','hbtyping','summary','note'];
function resetCheckup() {
  const f = document.getElementById('checkupForm');
  if (f) f.reset();
  const id = document.getElementById('c-id'); if (id) id.value = '';
  const t = document.getElementById('ckTitle'); if (t) t.innerHTML = '<i class="bi bi-plus-circle"></i> เพิ่มผลตรวจสุขภาพ';
}
function editCheckup(c) {
  resetCheckup();
  document.getElementById('c-id').value = c.id || '';
  CK_FIELDS.forEach(k => { const el = document.getElementById('c-' + k); if (el) el.value = (c[k] === null || c[k] === undefined) ? '' : c[k]; });
  const v = c.v || {};
  Object.keys(v).forEach(code => { const el = document.getElementById('vc-' + code); if (el) el.value = v[code] === null ? '' : v[code]; });
  document.getElementById('ckTitle').innerHTML = '<i class="bi bi-pencil-square"></i> แก้ไขผลตรวจสุขภาพ';
  new bootstrap.Modal(document.getElementById('checkupModal')).show();
}

// ===== บันทึกค่าสุขภาพรายครั้ง (quick log) =====
function quickLog(code) {
  const m = (window.HEALTH_METRICS || {})[code];
  const set = (id, prop, val) => { const el = document.getElementById(id); if (el) el[prop] = val; };
  set('l-id', 'value', '');
  set('l-metric', 'value', code);
  set('l-metric-sel', 'value', code);
  set('l-val', 'value', '');
  set('l-note', 'value', '');
  // ไฮไลต์ปุ่มค่าที่เลือก
  document.querySelectorAll('.lmc-btn').forEach(b => b.classList.toggle('active', b.dataset.metric === code));
  if (m) {
    set('l-title', 'textContent', m[0]);
    set('l-unit', 'textContent', m[1]);
    const ic = document.getElementById('l-ic');
    if (ic) { ic.innerHTML = '<i class="bi ' + m[2] + '"></i>'; ic.style.color = m[3]; }
    let ref = '';
    if (m[4] !== null && m[5] !== null) ref = 'เกณฑ์แนะนำ ' + m[4] + '–' + m[5] + ' ' + m[1];
    else if (m[4] !== null) ref = 'เกณฑ์แนะนำ ≥ ' + m[4] + ' ' + m[1];
    else if (m[5] !== null) ref = 'เกณฑ์แนะนำ ≤ ' + m[5] + ' ' + m[1];
    set('l-ref', 'textContent', ref);
  }
  setTimeout(() => { const v = document.getElementById('l-val'); if (v) v.focus(); }, 300);
}
function editHealth(l) {
  quickLog(l.metric);
  document.getElementById('l-id').value = l.id || '';
  document.getElementById('l-val').value = l.val;
  document.getElementById('l-date').value = l.log_date || l.date || '';
  document.getElementById('l-note').value = l.note || '';
  new bootstrap.Modal(document.getElementById('logModal')).show();
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
