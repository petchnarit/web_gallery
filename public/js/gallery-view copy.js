/* ========= Config ========= */
const API_BASE = '/gallery/api';   // /{id}, /file, /raw
const ROWS_PER_CHUNK = 8;          // เติมครั้งละกี่แถว
const GAP = 8;                     // ต้องตรงกับ CSS .jg-row gap
const ALLOWED_WIDTHS = [320, 2048]; // ใช้เฉพาะ 320 (grid) และ 2048 (lightbox)

/* ========= Utils ========= */
const $  = (q, root=document) => root.querySelector(q);
const $$ = (q, root=document) => Array.from(root.querySelectorAll(q));
const fmtTH = (iso) =>
  new Date(iso).toLocaleDateString('th-TH-u-ca-gregory', { day:'numeric', month:'long', year:'numeric' });

function withParams(url, extra) {
  const u = new URL(url, location.origin);
  Object.entries(extra).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') u.searchParams.set(k, v);
  });
  return u.toString();
}
function parseAspect(aspectStr, fallback = 4/3) {
  if (!aspectStr) return fallback;
  if (typeof aspectStr === 'number') return aspectStr;
  const m = String(aspectStr).match(/^(\d+(?:\.\d+)?)\/(\d+(?:\.\d+)?)$/);
  if (!m) return fallback;
  const w = parseFloat(m[1]), h = parseFloat(m[2]);
  return (h > 0 ? (w/h) : fallback);
}
function getRowTargetHeight() {
  const cs = getComputedStyle(document.documentElement);
  const v = parseFloat(cs.getPropertyValue('--rowH') || '220');
  return Math.max(120, Math.min(320, v)); // guard
}

/* ========= State ========= */
let IMAGES = [];
let DATA_CTX = null;
let CUR = 0;

let NEXT_IDX = 0;      // index ของภาพถัดไปที่จะจัดวาง
let rowBuffer = [];    // leftover จากแถวก่อนหน้า

// Observers / flags
let io = null;               // IntersectionObserver
let ro = null;               // ResizeObserver
let SENTINEL = null;
let isLayingOut = false;     // กัน re-entrancy
let relayoutScheduled = false;
let suppressRO = false;      // ปิด RO ชั่วคราวระหว่าง relayout ใหญ่
let stallTimer = null;       // backoff กรณีไม่มี progress
let ioCooldown = false;      // คูลดาวน์ IO
const IO_COOLDOWN_MS = 180;  // หน่วงนิดให้ภาพ/DOM ทันอัปเดต
let lastContainerW = 0;      // กว้างล่าสุดของกริด

/* ========= API ========= */
async function fetchAlbumById(id, store, subdir) {
  const url = new URL(`${API_BASE}/${encodeURIComponent(id)}`, location.origin);
  if (store)  url.searchParams.set('store', store);
  if (subdir) url.searchParams.set('subdir', subdir);
  const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' }});
  if (!res.ok) throw new Error(`Fetch ${res.status}`);
  return res.json();
}

/* ========= IO single-flight ========= */
function requestLayoutSoon() {
  if (ioCooldown) return;
  ioCooldown = true;
  setTimeout(() => { ioCooldown = false; layoutNextRows(); }, IO_COOLDOWN_MS);
}

/* ========= Layout core (Justified Grid) ========= */
function layoutNextRows() {
  if (isLayingOut) return;          // กันซ้อน
  isLayingOut = true;

  let progressed = false;
  const prevNext = NEXT_IDX;

  try {
    const grid = $('#grid');
    const containerW = Math.floor(grid.clientWidth || 0);
    if (!containerW) return;        // กว้าง 0 ไม่ทำอะไร (กัน loop)
    lastContainerW = containerW;

    if (NEXT_IDX >= IMAGES.length && rowBuffer.length === 0) {
      // เสร็จแล้ว—ถอด observe
      if (io && SENTINEL) io.unobserve(SENTINEL);
      return;
    }

    const targetH = getRowTargetHeight();
    let rowsAdded = 0;

    // ใช้ buffer ถ้ามี
    let workList = [...rowBuffer];
    rowBuffer = [];

    while (NEXT_IDX < IMAGES.length || workList.length) {
      let row = [];
      let aspectSum = 0;

      // เติม leftover ก่อน
      while (workList.length) {
        const it = workList.shift();
        row.push(it);
        aspectSum += it.aspect;
      }

      // เก็บรูปจนแถวใกล้เต็ม
      while (NEXT_IDX < IMAGES.length) {
        const img = IMAGES[NEXT_IDX];
        const aspect = parseAspect(img.aspectRatio, 4/3);
        row.push({ idx: NEXT_IDX, img, aspect });
        aspectSum += aspect;
        NEXT_IDX++;

        const totalWidth = aspectSum * targetH + GAP * (row.length - 1);
        if (totalWidth >= containerW * 0.98) break;
        if (row.length >= 12) break; // กันแถวยาวเกิน (เคสพาโน)
      }

      if (!row.length) break;

      // คิดความสูงจริงของแถว
      let rowH = targetH;
      let scaled = false;
      const canStretch = (NEXT_IDX < IMAGES.length); // มีของเติมแถวถัดไป
      const rawW = aspectSum * targetH + GAP * (row.length - 1);

      // บังคับยืดถ้าแถวหลวมมากเพื่อไม่ให้ติด buffer วน
      if (!scaled) {
        if (rawW < containerW * 0.7 && row.length >= 6 && canStretch) {
          const forceScale = (containerW - GAP * (row.length - 1)) / (aspectSum * targetH);
          rowH = Math.max(120, Math.min(360, Math.round(targetH * forceScale)));
          scaled = true;
        }
      }

      if (canStretch && !scaled && rawW !== containerW) {
        const scale = (containerW - GAP * (row.length - 1)) / (aspectSum * targetH);
        rowH = Math.max(120, Math.min(360, Math.round(targetH * scale)));
        scaled = true;
      }

      const rowEl = document.createElement('div');
      rowEl.className = 'jg-row';
      rowEl.style.height = `${rowH}px`;

      // กว้างแต่ละภาพ
      let widths = row.map(r => Math.round(r.aspect * rowH));
      const totalW = widths.reduce((a,b)=>a+b, 0) + GAP * (row.length - 1);
      let delta = containerW - totalW;

      if (scaled && Math.abs(delta) <= row.length * 2) {
        for (let k = 0; delta !== 0 && k < widths.length; k++) {
          widths[k] += (delta > 0 ? 1 : -1);
          delta += (delta > 0 ? -1 : 1);
        }
      }

      // วาดรูปในแถว
      row.forEach((r, i) => {
        const w = widths[i];
        const h = rowH;

        const item = document.createElement('figure');
        item.className = 'jg-item';
        item.style.width = `${w}px`;
        item.style.height = `${h}px`;

        const baseFileUrl = `${API_BASE}/file?store=${encodeURIComponent(DATA_CTX.store || 'public')}&album=${encodeURIComponent(DATA_CTX.id)}&f=${encodeURIComponent(r.img.filename)}`;

        // จำกัดเฉพาะ 320 และ 2048
        const src320  = withParams(baseFileUrl, { w: ALLOWED_WIDTHS[0], q: 80 });
        const src2048 = withParams(baseFileUrl, { w: ALLOWED_WIDTHS[1], q: 80 });
        const sizes = `${w}px`;
        const alt = `${DATA_CTX.title || ''} - ${r.img.filename || ''}`.trim().replace(/"/g, '&quot;');

        item.innerHTML = `
          <picture>
            <source type="image/webp" srcset="${src320} 320w" sizes="${sizes}">
            <img
              data-index="${r.idx}"
              src="${src320}"
              srcset="${src320} 320w"
              sizes="${sizes}"
              alt="${alt}"
              loading="${i < 8 ? 'eager' : 'lazy'}"
              decoding="async"
              fetchpriority="${r.idx < 8 ? 'high' : 'low'}"
            />
          </picture>
        `;

        item.querySelector('img').addEventListener('click', (e)=>{
          CUR = Number(e.currentTarget.dataset.index || 0);
          openLB(CUR, DATA_CTX);
        });

        rowEl.appendChild(item);
      });

      $('#grid').appendChild(rowEl);
      rowsAdded++;

      if (rowsAdded >= ROWS_PER_CHUNK) break;

      // ถ้าแถวนี้ยังหลวมและยังมีภาพเหลือ เก็บไว้ต่อรอบหน้า
      if (!scaled && NEXT_IDX < IMAGES.length) {
        rowBuffer = row.map(r => ({...r}));
        break;
      } else {
        rowBuffer = []; // ถ้า scaled แล้ว ห้ามเก็บ buffer เดิม (กันวน)
      }
    }

    progressed = (rowsAdded > 0 || NEXT_IDX > prevNext);

    // ---- Progress check / anti-loop ----
    if (io && SENTINEL) {
      if (!progressed) {
        // ไม่มีความคืบหน้า: ปลด observe ชั่วคราว แล้วค่อยกลับมาใหม่หลังพัก
        io.unobserve(SENTINEL);
        clearTimeout(stallTimer);
        stallTimer = setTimeout(() => {
          if (NEXT_IDX < IMAGES.length || rowBuffer.length) {
            io.observe(SENTINEL);
            requestLayoutSoon();
          }
        }, 400);
      }
    }

    // เสร็จหมดแล้ว เอา sentinel ออก
    if (NEXT_IDX >= IMAGES.length && rowBuffer.length === 0) {
      if (io && SENTINEL) io.unobserve(SENTINEL);
      if (SENTINEL) { SENTINEL.remove(); SENTINEL = null; }
    }

  } finally {
    isLayingOut = false;
  }
}

/* ========= Infinite scroll ========= */
function setupInfiniteScroll() {
  if (SENTINEL) return;
  SENTINEL = document.createElement('div');
  SENTINEL.id = 'sentinel';
  SENTINEL.className = 'h-8';
  $('#grid').after(SENTINEL);

  io = new IntersectionObserver((entries)=>{
    if (entries.some(e=>e.isIntersecting)) {
      requestLayoutSoon();
    }
  }, { rootMargin: '800px 0px' });

  io.observe(SENTINEL);
}

/* ========= Album bootstrapping ========= */
function renderAlbum(data) {
  $('#album-title').textContent = data.title ?? '(Untitled)';
  $('#photo-count').textContent = `${data.count ?? (data.images?.length || 0)} photos`;
  const firstDate = data.images?.[0]?.captured || '';
  $('#album-date').textContent = firstDate ? fmtTH(firstDate) : '';

  const grid = $('#grid');
  grid.innerHTML = '';

  if (!data.images || data.images.length === 0) {
    $('#empty').classList.remove('hidden');
    return;
  }
  $('#empty').classList.add('hidden');

  IMAGES = data.images;
  DATA_CTX = data;
  NEXT_IDX = 0;
  rowBuffer = [];

  layoutNextRows();
  setupInfiniteScroll();

  const dlBtn = $('#lb-download');
  dlBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    try {
      await shareOrDownloadOriginal(DATA_CTX, CUR);
    } catch (err) {
      console.error(err);
    }
  });
}

/* ========= Lightbox ========= */
function getDownloadUrl(data, idx) {
  const img = IMAGES[idx];
  if (!img) return '';
  return `${API_BASE}/file?store=${encodeURIComponent(data.store || 'public')}`
       + `&album=${encodeURIComponent(data.id)}`
       + `&f=${encodeURIComponent(img.filename)}`
       + `&w=${ALLOWED_WIDTHS[1]}&q=80`; // 2048
}
function getViewUrl(data, idx) {
  const img = IMAGES[idx];
  if (!img) return '';
  return `${API_BASE}/file?store=${encodeURIComponent(data.store || 'public')}`
       + `&album=${encodeURIComponent(data.id)}`
       + `&f=${encodeURIComponent(img.filename)}`
       + `&w=${ALLOWED_WIDTHS[1]}&q=80`; // 2048
}

function openLB(i, dataCtx) {
  const viewUrl = getViewUrl(dataCtx, i);
  const imgEl = $('#lb-img');
  if (imgEl) imgEl.src = viewUrl;

  const a = $('#lb-download');
  if (a) {
    a.href = getDownloadUrl(dataCtx, i);
    a.setAttribute('download', IMAGES[i]?.filename || 'photo');
  }

  $('#lightbox').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}
function closeLB() {
  $('#lightbox').classList.add('hidden');
  $('#lb-img').src = '';
  document.body.style.overflow = '';
}
function nextLB(dir, dataCtx) {
  if (!IMAGES.length) return;
  CUR = (CUR + dir + IMAGES.length) % IMAGES.length;
  openLB(CUR, dataCtx);
}

/* ========= Share/Download (iOS first) ========= */
async function shareOrDownloadOriginal(data, idx) {
  const url = getDownloadUrl(data, idx);
  if (!url) return;

  const resp = await fetch(url, { method: 'GET' });
  if (!resp.ok) throw new Error(`Download failed: ${resp.status}`);
  const ct = resp.headers.get('Content-Type') || 'application/octet-stream';
  const blob = await resp.blob();

  const filename = (IMAGES[idx]?.filename || 'photo').replace(/[^\w.\- ()[\]]+/g, '_');
  const file = new File([blob], filename, { type: ct });

  if (navigator.canShare && navigator.canShare({ files: [file] }) && navigator.share) {
    try {
      await navigator.share({
        files: [file],
        title: data.title || filename,
        text: ''
      });
      return;
    } catch (err) {
      console.warn('Share failed, fallback to download:', err);
    }
  }

  const a = document.createElement('a');
  const href = URL.createObjectURL(blob);
  a.href = href;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(href), 2000);
}

/* ========= Resize handling (relayout แบบไม่กระตุก) ========= */
function scheduleRelayout() {
  if (relayoutScheduled) return;
  relayoutScheduled = true;

  setTimeout(() => {
    relayoutScheduled = false;

    suppressRO = true;         // ปิด RO ชั่วคราวกัน loop
    try {
      if (ro) ro.disconnect(); // ตัดการสังเกตก่อน
      if (io && SENTINEL) io.unobserve(SENTINEL);

      const savedIdx = CUR;
      const data = DATA_CTX;
      if (!data) return;

      const grid = $('#grid');
      grid.innerHTML = '';
      NEXT_IDX = 0;
      rowBuffer = [];

      layoutNextRows();

      CUR = savedIdx;

      // ต่อ RO กลับ พร้อม guard ถ้ากว้างไม่เปลี่ยนอย่าลั่น
      if (!ro) {
        ro = new ResizeObserver(() => {
          if (suppressRO) return;
          const w = Math.floor($('#grid').clientWidth || 0);
          if (!w || Math.abs(w - lastContainerW) < 4) return; // ไม่เปลี่ยนจริง
          scheduleRelayout();
        });
      }
      ro.observe($('#grid'));

      if (SENTINEL && io) io.observe(SENTINEL);
    } finally {
      suppressRO = false;
    }
  }, 120);
}

/* ========= Init ========= */
(function init(){
  $('#mobile-menu-btn')?.addEventListener('click', ()=>{
    const m = $('#mobile-menu');
    if (!m) return;
    m.classList.toggle('hidden');
  });

  // path: /gallery/gallery/view/{id}
  const parts = location.pathname.replace(/\/+$/,'').split('/');
  const id = parts[parts.length - 1] || '';

  const store  = 'public';
  const subdir = '';

  if (!id) {
    $('#error')?.classList.remove('hidden');
    $('#error-detail').textContent = 'ไม่พบค่า id จาก URL';
    return;
  }

  fetchAlbumById(id, store, subdir)
    .then((data)=>{
      if (!data?.ok) throw new Error('API return not ok');
      renderAlbum(data);

      // Lightbox controls
      $('#lb-close')?.addEventListener('click', closeLB);
      $('#lb-prev')?.addEventListener('click', ()=> nextLB(-1, data));
      $('#lb-next')?.addEventListener('click', ()=> nextLB(1,  data));
      $('#lightbox')?.addEventListener('click', (e)=> { if (e.target === e.currentTarget) closeLB(); });
      window.addEventListener('keydown', (e)=>{
        const lb = $('#lightbox');
        if (lb.classList.contains('hidden')) return;
        if (e.key === 'Escape') closeLB();
        if (e.key === 'ArrowRight') nextLB(1,  data);
        if (e.key === 'ArrowLeft')  nextLB(-1, data);
        if (e.key === 'D' || e.key === 'd') $('#lb-download')?.click();
      });

      // ResizeObserver: ผูกครั้งเดียว
      ro = new ResizeObserver(() => {
        if (suppressRO) return;
        const w = Math.floor($('#grid').clientWidth || 0);
        if (!w || Math.abs(w - lastContainerW) < 4) return; // เมินการเปลี่ยนเล็กน้อย
        scheduleRelayout();
      });
      ro.observe($('#grid'));

      // IntersectionObserver: ตั้งหลังจาก render รอบแรก
      setupInfiniteScroll();
    })
    .catch((err)=>{
      console.error(err);
      $('#error')?.classList.remove('hidden');
      $('#error-detail').textContent = String(err.message || err);
    });
})();
