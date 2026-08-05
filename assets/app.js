// เติมข้อมูลลงฟอร์มเพื่อแก้ไข
function editRow(r) {
  document.getElementById('f-id').value = r.id || '';
  document.getElementById('f-date').value = r.record_date || '';
  document.getElementById('f-note').value = r.note || '';

  const fields = ['m1_sys','m1_dia','m1_hr','m2_sys','m2_dia','m2_hr',
                  'n1_sys','n1_dia','n1_hr','n2_sys','n2_dia','n2_hr'];
  fields.forEach(f => {
    const el = document.getElementById('f-' + f);
    if (el) el.value = (r[f] === null || r[f] === undefined) ? '' : r[f];
  });

  document.getElementById('form-title').textContent = '✏️ แก้ไขบันทึก';
  document.getElementById('btn-reset').textContent = 'ยกเลิก';
  document.getElementById('form-panel').scrollIntoView({ behavior: 'smooth' });
}

// ล้าง/ยกเลิกฟอร์ม
function resetForm() {
  document.getElementById('bp-form').reset();
  document.getElementById('f-id').value = '';
  document.getElementById('form-title').textContent = '➕ เพิ่มบันทึกใหม่';
  document.getElementById('btn-reset').textContent = 'ล้างฟอร์ม';
}
