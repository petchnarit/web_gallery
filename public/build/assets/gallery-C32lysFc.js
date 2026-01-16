import{M as A,i as R}from"./imagesloaded-CyESsmFl.js";const $=window.APP_CONFIG?.apiBase;if(!$)throw new Error("APP_CONFIG.apiBase not defined");const h=18,j=[320,640,1024,2048],P="(min-width: 640px) 50vw, 100vw";let a=1,d=1,m=null;const s=(n,t=document)=>t.querySelector(n),z=(n,t=document)=>Array.from(t.querySelectorAll(n));function w(n,t){const e=new URL(n,location.origin);return Object.entries(t).forEach(([o,i])=>{i!=null&&i!==""&&e.searchParams.set(o,i)}),e.toString()}function T(n,t=j){return t.map(e=>w(n,{w:e,q:80})).map((e,o)=>`${e} ${t[o]}w`).join(", ")}async function k(n=1,t=h){const e=new URL($);e.searchParams.set("page",n),e.searchParams.set("perPage",t);const o=await fetch(e,{headers:{Accept:"application/json"}});if(!o.ok)throw new Error(`Fetch failed: ${o.status}`);return o.json()}function H(n){m?.destroy&&m.destroy(),m=new A(n,{itemSelector:".masonry-item",columnWidth:".grid-sizer",gutter:".gutter-sizer",percentPosition:!0,transitionDuration:"0.2s"}),R(n).on("progress",()=>m.layout())}function N(n){const t=document.createElement("link");t.rel="preload",t.as="image",t.href=n,document.head.appendChild(t)}function C(n,t,e,o){const i=s("#gallery-masonry");i.innerHTML='<div class="grid-sizer"></div><div class="gutter-sizer"></div>';const r=n.map((l,M)=>{const[f,v]=(l.aspectRatio||"4/3").split("/").map(Number),b=l.w??320,S=l.h??Math.round(b*(v/f)),g=l.cover,y=T(g,[320,640]),I=`/gallery/view/${encodeURIComponent(l.id)}`;let x="lazy",L="low";return t===1&&M===0&&(x="eager",L="high",N(w(g,{w:640,q:80}))),`
      <article class="masonry-item">
        <a href="${I}" class="group block rounded-lg overflow-hidden transition-all transform hover:-translate-y-1 hover:shadow-xl bg-white shadow-md">
          <div class="relative overflow-hidden" style="aspect-ratio:${f}/${v};background:#f0f0f0">
            <picture>
              <source type="image/webp" srcset="${y}" sizes="${P}">
              <img
                src="${w(g,{w:32,q:10})}"
                srcset="${y}"
                sizes="${P}"
                alt="${l.title}"
                width="${b}"
                height="${S}"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                loading="${x}"
                decoding="async"
                fetchpriority="${L}"
              />
            </picture>
          </div>

          <div class="p-5">
            <h3 class="text-lg font-semibold text-gray-900 mb-1 group-hover:text-purple-600 transition-colors line-clamp-2">${l.title}</h3>
            <div class="flex items-center text-sm text-gray-500">
              <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              ${new Date(l.date).toLocaleDateString("th-TH",{year:"numeric",month:"long",day:"numeric"})}
            </div>

            <div class="flex items-center text-sm text-gray-500 mt-1">
              <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              ${l.picturesCount??0} photos
            </div>
          </div>
        </a>
      </article>
    `}).join("");i.insertAdjacentHTML("beforeend",r),H(i);const c=(t-1)*h+1,u=Math.min(t*h,e);s("#showing-start").textContent=e?c:0,s("#showing-end").textContent=e?u:0,s("#showing-total").textContent=e,s("#current-page").textContent=t,s("#total-pages").textContent=o,window.scrollTo({top:0,behavior:"smooth"})}function E(){const n=s("#page-numbers");n.innerHTML="";const t=[],e=r=>{!t.includes(r)&&r>=1&&r<=d&&t.push(r)};e(1),e(2),e(a-1),e(a),e(a+1),e(d-1),e(d);const o=[...new Set(t)].sort((r,c)=>r-c);let i=0;o.forEach(r=>{if(r-i>1){const u=document.createElement("span");u.className="px-2 text-gray-400 select-none",u.textContent="…",n.appendChild(u)}const c=document.createElement("button");c.className=`px-4 py-2 rounded-lg border transition-colors ${r===a?"bg-purple-600 text-white border-purple-600":"bg-white text-gray-700 border-gray-300 hover:bg-gray-50"}`,c.textContent=r,c.setAttribute("aria-current",r===a?"page":"false"),c.addEventListener("click",()=>p(r)),n.appendChild(c),i=r}),s("#prev-page").disabled=a<=1,s("#next-page").disabled=a>=d}async function p(n,{pushState:t=!0}={}){a=Math.max(1,Math.min(n,d));const e=await k(a,h);if(d=e.totalPages,e.total,C(e.galleries,e.page,e.total,e.totalPages),E(),t){const o=new URL(location.href);o.searchParams.set("page",a),history.pushState({page:a},"",o)}}function U(){const n=s("#mobile-menu-btn"),t=s("#mobile-menu");!n||!t||(n.addEventListener("click",()=>t.classList.toggle("hidden")),z("#mobile-menu a",t).forEach(e=>e.addEventListener("click",()=>t.classList.add("hidden"))))}function q(){const n=s("#scroll-top");if(!n)return;const t=window.matchMedia("(prefers-reduced-motion: reduce)").matches,e=400;window.addEventListener("scroll",()=>{window.scrollY>e?n.classList.remove("hidden"):n.classList.add("hidden")}),n.addEventListener("click",()=>{window.scrollTo({top:0,behavior:t?"auto":"smooth"})})}(async function(){const t=new URL(location.href),e=parseInt(t.searchParams.get("page")||"1",10);a=Number.isFinite(e)?e:1;const o=await k(a,h);d=o.totalPages,o.total,C(o.galleries,o.page,o.total,o.totalPages),E(),s("#prev-page")?.addEventListener("click",()=>p(a-1)),s("#next-page")?.addEventListener("click",()=>p(a+1)),window.addEventListener("popstate",i=>{const r=i.state?.page??parseInt(new URL(location.href).searchParams.get("page")||"1",10);p(r,{pushState:!1})}),U(),q()})();
