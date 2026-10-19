// Shared UI helpers
(function () {
  const body = document.body;
  const toggle = document.getElementById('navToggle');
  const scrim = document.getElementById('scrim');
  if (toggle) toggle.addEventListener('click', () => body.classList.toggle('nav-open'));
  if (scrim) scrim.addEventListener('click', () => body.classList.remove('nav-open'));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') body.classList.remove('nav-open'); });
})();

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function toast(message, type) {
  let box = document.getElementById('toasts');
  if (!box) { box = document.createElement('div'); box.id = 'toasts'; box.setAttribute('role', 'status'); document.body.appendChild(box); }
  const t = document.createElement('div');
  t.className = 'toast ' + (type || '');
  t.textContent = message;
  box.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

async function postForm(url, data) {
  const res = await fetch(url, { method: 'POST', body: data instanceof FormData ? data : new URLSearchParams(data), credentials: 'same-origin' });
  let json;
  try { json = await res.json(); } catch (e) { throw new Error('Unexpected server response.'); }
  return json;
}

// Fitness helpers (same formulas the server uses)
const FIT = {
  bmi: (w, h) => w / Math.pow(h / 100, 2),
  vo2: (rest, max) => 15.3 * (max / rest),
  bmiCat: b => b < 18.5 ? 'Underweight' : b < 25 ? 'Normal weight' : b < 30 ? 'Overweight' : 'Obese',
  vo2Cat: v => v >= 60 ? 'Excellent' : v >= 50 ? 'Very good' : v >= 40 ? 'Good' : v >= 30 ? 'Fair' : 'Poor',
  skills: ['flexibility', 'strength', 'agility', 'speed', 'endurance'],
  skillAvg: r => r ? FIT.skills.reduce((a, k) => a + (+r[k] || 0), 0) / FIT.skills.length : 0
};

// Reads the page's CSS colours so every chart matches the theme (light or dark).
function chartTheme() {
  const css = getComputedStyle(document.documentElement);
  const v = n => css.getPropertyValue(n).trim();
  const t = { text: v('--text'), muted: v('--muted'), line: v('--line'), surface: v('--surface'),
    primary: v('--primary'), accent: v('--accent'), neutral: v('--neutral'),
    series: [v('--primary'), v('--accent'), v('--c3'), v('--c4'), v('--c5')] };
  if (window.Chart) {
    Chart.defaults.color = t.muted;
    Chart.defaults.font.family = '"Instrument Sans", system-ui, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.plugins.tooltip.backgroundColor = t.text;
    Chart.defaults.plugins.tooltip.titleColor = t.surface;
    Chart.defaults.plugins.tooltip.bodyColor = t.surface;
    Chart.defaults.plugins.tooltip.cornerRadius = 6;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
  }
  return t;
}

// Profile editor (sidebar / top-bar avatar)
(function () {
  const dlg = document.getElementById('profileModal');
  if (!dlg) return;
  const form = document.getElementById('profileForm');
  const err = document.getElementById('pfError');
  const preview = document.getElementById('pfPreview');
  const picture = document.getElementById('pfPicture');
  const saveBtn = document.getElementById('pfSave');
  const originalSrc = preview.getAttribute('src');

  function showError(msg) { err.textContent = msg; err.hidden = false; err.scrollIntoView({ block: 'nearest' }); }
  function reset() {
    form.reset(); err.hidden = true; preview.src = originalSrc;
    dlg.querySelector('details.pf-password').open = false;
  }

  document.querySelectorAll('.js-open-profile').forEach(b => b.addEventListener('click', () => {
    document.body.classList.remove('nav-open');
    dlg.showModal();
  }));
  document.getElementById('pfClose').addEventListener('click', () => dlg.close());
  document.getElementById('pfCancel').addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });   // click on backdrop
  dlg.addEventListener('close', reset);

  picture.addEventListener('change', () => {
    const f = picture.files[0];
    if (!f) { preview.src = originalSrc; return; }
    if (!/^image\/(jpeg|png|webp|gif)$/.test(f.type)) { picture.value = ''; showError('Please choose a JPG, PNG, WEBP or GIF image.'); return; }
    if (f.size > 2 * 1024 * 1024) { picture.value = ''; showError('That image is over 2 MB. Please choose a smaller one.'); return; }
    err.hidden = true;
    preview.src = URL.createObjectURL(f);
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    err.hidden = true;
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const cur = form.current_password.value, nw = form.new_password.value, cf = form.confirm_password.value;
    if ((cur || nw || cf) && !dlg.querySelector('details.pf-password').open) dlg.querySelector('details.pf-password').open = true;
    if (nw !== cf) { showError('New passwords do not match.'); return; }
    saveBtn.disabled = true;
    try {
      const d = await postForm('save-profile.php', new FormData(form));
      if (!d.success) throw new Error(d.message || 'Could not save your changes.');
      toast('Profile updated', 'success');
      setTimeout(() => location.reload(), 600);
    } catch (ex) {
      showError(ex.message);
      saveBtn.disabled = false;
    }
  });
})();
