<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Gallery By Comcenter</title>

  <meta name="description" content="View photos inside a collection." />

  <!-- Vite-built CSS -->
  <?= vite('css/view.css') ?>
  <!-- Config -->
  <script>
    window.APP_CONFIG = {
      apiBase: "<?= rtrim(base_url('api'), '/') ?>"
    };
  </script>

  <!-- Vite-built JS -->
  <?= vite('js/view.js') ?>

</head>

<body class="font-sans antialiased bg-gray-50 text-gray-900">

  <!-- Navigation -->
  <nav class="sticky top-0 z-50 bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <div class="flex-shrink-0">
          <a href="<?= base_url(); ?>" class="text-2xl font-bold text-gray-900">Gallery by comcenter</a>
        </div>
        <div class="hidden md:block">
          <div class="ml-10 flex items-baseline space-x-8">
            <a href="<?= base_url(); ?>" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium transition-colors">Gallery</a>
          </div>
        </div>
        <div class="md:hidden">
          <button id="mobile-menu-btn" class="text-gray-700 hover:text-gray-900 p-2" aria-label="Open Menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>
      </div>
    </div>
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-200">
      <div class="px-2 pt-2 pb-3 space-y-1">
        <a href="<?= base_url(); ?>" class="block text-gray-700 hover:bg-gray-50 px-3 py-2 text-base font-medium">Gallery</a>
      </div>
    </div>
  </nav>

  <!-- Header -->
  <section class="py-8 lg:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
      <h2 id="album-title" class="text-3xl sm:text-4xl font-bold text-gray-900 mb-0">Loading…</h2>
      <!-- <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm text-gray-600 items-center">
        <span id="photo-count">0 photos</span>
        <span class="hidden sm:inline text-gray-300">|</span>
        <span id="album-date" class="line-clamp-1"></span>
      </div> -->
  </section>

  <!-- Content -->
  <main class="py-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <!-- Justified Grid container -->
      <div id="grid"></div>

      <!-- Empty -->
      <div id="empty" class="hidden text-center py-16">
        <p class="text-gray-500">ไม่พบรูปภาพในคอลเลกชันนี้</p>
      </div>

      <!-- Error -->
      <div id="error" class="hidden text-center py-16">
        <p class="text-red-600 font-medium">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>
        <p class="text-gray-500 mt-1" id="error-detail"></p>
      </div>
    </div>
  </main>

  <!-- Lightbox -->
  <div id="lightbox" class="fixed inset-0 z-[60] bg-black/90 hidden">
    <button id="lb-close" class="absolute top-4 right-4 p-2 rounded-full bg-white/10 hover:bg-white/20 text-white" aria-label="Close">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
    <button id="lb-prev" class="absolute left-2 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white" aria-label="Previous">
      <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
    </button>
    <button id="lb-next" class="absolute right-2 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white" aria-label="Next">
      <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </button>
    <!-- ปุ่มดาวน์โหลด -->
    <a id="lb-download"
      class="absolute top-4 left-4 p-2 rounded-full bg-white/10 hover:bg-white/20 text-white"
      href="#"
      download
      aria-label="Download">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
      </svg>
    </a>

    <div class="w-full h-full flex items-center justify-center p-4">
      <img id="lb-img" src="" alt="" class="max-h-full max-w-full object-contain select-none" />
    </div>
  </div>

  <footer class="bg-gray-50 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mt-8 pt-8 text-center text-gray-600">
        <p>&copy; 2025 งานศูนย์คอมพิวเตอร์</p>
      </div>
    </div>
  </footer>

</body>

</html>