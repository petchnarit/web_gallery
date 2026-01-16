// resources/js/gallery.js
import Masonry from 'masonry-layout';
import imagesLoaded from 'imagesloaded';

/* ====== Config ====== */
const API_BASE = window.APP_CONFIG?.apiBase;
if (!API_BASE) {
  throw new Error('APP_CONFIG.apiBase not defined');
}

const itemsPerPage = 18;
const WIDTHS = [320, 640, 1024, 2048];
const SIZES = '(min-width: 640px) 50vw, 100vw';

/* ====== State ====== */
let currentPage = 1;
let totalPages = 1;
let totalItems = 0;
let msnry = null;

/* ====== DOM Helpers ====== */
const $ = (q, root = document) => root.querySelector(q);
const $$ = (q, root = document) => Array.from(root.querySelectorAll(q));

/* ====== URL Helpers ====== */
function withParams(url, extra) {
  const u = new URL(url, location.origin);
  Object.entries(extra).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') u.searchParams.set(k, v);
  });
  return u.toString();
}

function buildWebpSrcsetFromCover(coverUrl, widths = WIDTHS) {
  return widths
    .map(w => withParams(coverUrl, { w, q: 80 }))
    .map((u, i) => `${u} ${widths[i]}w`)
    .join(', ');
}

/* ====== Fetch Collections ====== */
async function fetchCollections(page = 1, perPage = itemsPerPage) {
  const url = new URL(API_BASE);
  url.searchParams.set('page', page);
  url.searchParams.set('perPage', perPage);

  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error(`Fetch failed: ${res.status}`);
  return res.json();
}

/* ====== Masonry ====== */
function initMasonry(container) {
  if (msnry && typeof msnry.destroy === 'function') {
    msnry.destroy();
    msnry = null;
  }

  msnry = new Masonry(container, {
    itemSelector: '.masonry-item',
    columnWidth: '.grid-sizer',
    gutter: '.gutter-sizer',
    percentPosition: true,
    originLeft: true,
    transitionDuration: '0.2s',
  });

  imagesLoaded(container).on('progress', () => msnry.layout());

  let tid;
  window.addEventListener('resize', () => {
    clearTimeout(tid);
    tid = setTimeout(() => msnry && msnry.layout(), 120);
  });
}

/* ====== Render Gallery ====== */
function renderGallery(galleries, page, total, totalPagesServer) {
  const container = $('#gallery-masonry');

  container.innerHTML = `
    <div class="grid-sizer"></div>
    <div class="gutter-sizer"></div>
  `;

  const cards = galleries.map((g, i) => {
    const [wRatio, hRatio] = (g.aspectRatio || '4/3').split('/').map(Number);
    const width = g.w ?? 320;
    const height = g.h ?? Math.round(width * (hRatio / wRatio));
    const cover = g.cover;
    const srcset = buildWebpSrcsetFromCover(cover, [320, 640]);
    const placeholder = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
    const viewUrl = `/gallery/view/${encodeURIComponent(g.id)}`;

    return `
      <article class="masonry-item">
        <a href="${viewUrl}" class="group block rounded-lg overflow-hidden transition-all transform hover:-translate-y-1 hover:shadow-xl bg-white shadow-md">
          <div class="relative overflow-hidden" style="aspect-ratio:${wRatio}/${hRatio}">
            <picture>
              <source type="image/webp" srcset="${srcset}" sizes="${SIZES}">
              <img
                src="${placeholder}"
                srcset="${srcset}"
                sizes="${SIZES}"
                alt="${g.title}"
                width="${width}"
                height="${height}"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                loading="${i < 2 && page === 1 ? 'eager' : 'lazy'}"
                decoding="async"
                fetchpriority="${i === 0 && page === 1 ? 'high' : 'low'}"
              />
            </picture>
          </div>

          <div class="p-5">
            <h3 class="text-lg font-semibold text-gray-900 mb-1 group-hover:text-purple-600 transition-colors">${g.title}</h3>
            <div class="flex items-center text-sm text-gray-500">
              <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              ${new Date(g.date).toLocaleDateString('th-TH', { year:'numeric', month:'long', day:'numeric' })}
            </div>

            <div class="flex items-center text-sm text-gray-500 mt-1">
              <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              ${g.picturesCount ?? 0} photos
            </div>
          </div>
        </a>
      </article>
    `;
  }).join('');

  container.insertAdjacentHTML('beforeend', cards);
  initMasonry(container);

  const startIndex = (page - 1) * itemsPerPage + 1;
  const endIndex   = Math.min(page * itemsPerPage, total);
  $('#showing-start').textContent = total ? startIndex : 0;
  $('#showing-end').textContent   = total ? endIndex : 0;
  $('#showing-total').textContent = total;
  $('#current-page').textContent  = page;
  $('#total-pages').textContent   = totalPagesServer;

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ====== Pagination ====== */
function buildPageNumbers() {
  const holder = $('#page-numbers');
  holder.innerHTML = '';

  const pages = [];
  const push = p => { if (!pages.includes(p) && p >= 1 && p <= totalPages) pages.push(p); };
  push(1); push(2);
  push(currentPage - 1); push(currentPage); push(currentPage + 1);
  push(totalPages - 1); push(totalPages);

  const uniqueSorted = [...new Set(pages)].sort((a,b)=>a-b);
  let prev = 0;

  uniqueSorted.forEach(p => {
    if (p - prev > 1) {
      const dots = document.createElement('span');
      dots.className = 'px-2 text-gray-400 select-none';
      dots.textContent = '…';
      holder.appendChild(dots);
    }
    const btn = document.createElement('button');
    btn.className = `px-4 py-2 rounded-lg border transition-colors ${
      p === currentPage
        ? 'bg-purple-600 text-white border-purple-600'
        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
    }`;
    btn.textContent = p;
    btn.setAttribute('aria-current', p === currentPage ? 'page' : 'false');
    btn.addEventListener('click', () => gotoPage(p));
    holder.appendChild(btn);
    prev = p;
  });

  $('#prev-page').disabled = currentPage <= 1;
  $('#next-page').disabled = currentPage >= totalPages;
}

/* ====== Navigation ====== */
async function gotoPage(p, { pushState = true } = {}) {
  currentPage = Math.max(1, Math.min(p, totalPages));
  const data = await fetchCollections(currentPage, itemsPerPage);
  totalPages = data.totalPages;
  totalItems = data.total;

  renderGallery(data.galleries, data.page, data.total, data.totalPages);
  buildPageNumbers();

  if (pushState) {
    const url = new URL(location.href);
    url.searchParams.set('page', currentPage);
    history.pushState({ page: currentPage }, '', url);
  }
}

/* ====== UI Enhancements ====== */
function initMobileMenu() {
  const btn = $('#mobile-menu-btn');
  const menu = $('#mobile-menu');
  if (!btn || !menu) return;
  btn.addEventListener('click', () => menu.classList.toggle('hidden'));
  $$('#mobile-menu a', menu).forEach(a => a.addEventListener('click', ()=> menu.classList.add('hidden')));
}

function initScrollTop() {
  const btn = $('#scroll-top');
  if (!btn) return;
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const revealAt = 400;
  window.addEventListener('scroll', () => {
    if (window.scrollY > revealAt) btn.classList.remove('hidden');
    else btn.classList.add('hidden');
  });
  btn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: prefersReduced ? 'auto' : 'smooth' });
  });
}

/* ====== Bootstrap ====== */
(async function init() {
  const url = new URL(location.href);
  const p = parseInt(url.searchParams.get('page') || '1', 10);
  currentPage = Number.isFinite(p) ? p : 1;

  const data = await fetchCollections(currentPage, itemsPerPage);
  totalPages = data.totalPages;
  totalItems = data.total;

  renderGallery(data.galleries, data.page, data.total, data.totalPages);
  buildPageNumbers();

  $('#prev-page')?.addEventListener('click', () => gotoPage(currentPage - 1));
  $('#next-page')?.addEventListener('click', () => gotoPage(currentPage + 1));

  window.addEventListener('popstate', (e) => {
    const page = e.state?.page ?? parseInt(new URL(location.href).searchParams.get('page') || '1', 10);
    gotoPage(page, { pushState: false });
  });

  initMobileMenu();
  initScrollTop();
})();