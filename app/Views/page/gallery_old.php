<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>Gallery By Comcenter</title>
    <meta name="description" content="Browse our complete photography gallery featuring events, weddings, fashion, commercial, and portrait collections.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Open Graph -->
    <meta property="og:title" content="Gallery By Comcenter">
    <meta property="og:description" content="Browse our complete photography gallery featuring events, weddings, fashion, commercial, and portrait collections.">
    <meta property="og:image" content="https://pr.vru.ac.th/gallery/og-image.jpg">
    <meta property="og:url" content="https://pr.vru.ac.th/gallery/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="VRU Gallery">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Gallery By Comcenter">
    <meta name="twitter:description" content="Browse our complete photography gallery featuring events, weddings, fashion, commercial, and portrait collections.">
    <meta name="twitter:image" content="https://pr.vru.ac.th/gallery/og-image.jpg">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Noto Sans Thai"', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'sans-serif']
                    }
                }
            }
        }
    </script>


    <style>
        .break-inside-avoid {
            break-inside: avoid;
        }

        /* จัดคอนเทนเนอร์ให้อยู่กึ่งกลาง */
        .masonry {
            margin-left: auto;
            margin-right: auto;
        }

        /* ไอเท็ม + ระยะแนวตั้ง */
        .masonry-item {
            break-inside: avoid;
            margin-bottom: 16px;
        }

        /* ความกว้างคอลัมน์ (สัมพันธ์กับ gutter 16px) */
        .grid-sizer,
        .masonry-item {
            width: 100%;
        }

        @media (min-width: 320px) {

            /* ~2 คอลัมน์ */
            .grid-sizer,
            .masonry-item {
                width: calc((100% - 16px) / 2);
            }
        }

        @media (min-width: 640px) {

            /* ~2 คอลัมน์ */
            .grid-sizer,
            .masonry-item {
                width: calc((100% - 16px) / 2);
            }
        }

        @media (min-width: 1024px) {

            /* ~3 คอลัมน์ */
            .grid-sizer,
            .masonry-item {
                width: calc((100% - 32px) / 3);
            }
        }

        @media (min-width: 1280px) {

            /* ~4 คอลัมน์ */
            .grid-sizer,
            .masonry-item {
                width: calc((100% - 48px) / 4);
            }
        }

        /* ร่องคอลัมน์ */
        .gutter-sizer {
            width: 16px;
        }


        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
    <!-- Masonry + imagesLoaded (ต้องมาก่อนสคริปต์ของเรา) -->
    <script src="https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js"></script>
    <script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>

    <script>
        window.GALLERY_LIST_CONFIG = {
            apiBase: '<?= base_url('api/gallery'); ?>',
            defaultStore: 'public',
            defaultSubdir: ''
        };
    </script>
    <script src="<?= base_url('js/gallery.js'); ?>" defer></script>
</head>

<body class="font-sans antialiased bg-gray-50">

    <!-- Navigation -->
    <nav class="sticky top-0 z-50 bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0">
                    <a href="<?= base_url(); ?>" class="text-2xl font-bold text-gray-900">Gallery by comcenter</a>
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <!-- <a href="<?= base_url(); ?>" class="text-purple-600 border-b-2 border-purple-600 px-3 py-2 text-sm font-medium">Gallery</a>
                        <a href="<?= base_url(); ?>" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium transition-colors">About</a> -->
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
                <!-- <a href="<?= base_url(); ?>" class="block text-purple-600 bg-purple-50 px-3 py-2 text-base font-medium">Gallery</a>
                <a href="<?= base_url(); ?>" class="block text-gray-700 hover:bg-gray-50 px-3 py-2 text-base font-medium">About</a> -->
                <a href="<?= base_url(); ?>" class="block text-gray-700 hover:bg-gray-50 px-3 py-2 text-base font-medium">Gallery</a>
            </div>
        </div>
    </nav>

    <section class="py-8 lg:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">Gallery</h2>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto"></p>
        </div>
    </section>

    <section class="py-4 lg:py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-8 flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    Showing <span id="showing-start">1</span> to <span id="showing-end">12</span> of <span id="showing-total">0</span> collections
                </div>
                <div class="text-sm text-gray-600">
                    Page <span id="current-page">1</span> of <span id="total-pages">1</span>
                </div>
            </div>

            <div class="masonry-container" id="gallery-masonry"></div>

            <div class="mt-12 flex justify-center">
                <nav class="flex items-center gap-2" aria-label="Pagination">
                    <button id="prev-page" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" disabled>Previous</button>
                    <div id="page-numbers" class="flex gap-2"></div>
                    <button id="next-page" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">Next</button>
                </nav>
            </div>
        </div>
    </section>

    <footer class="bg-gray-50 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mt-8 pt-8 text-center text-gray-600">
                <p>&copy; 2025 งานศูนย์คอมพิวเตอร์</p>
            </div>
        </div>
    </footer>

    <button id="scroll-top" class="hidden fixed bottom-8 right-8 bg-purple-600 text-white p-3 rounded-full shadow-lg hover:bg-purple-700 transition-all transform hover:scale-110 z-40" aria-label="Scroll to top">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
        </svg>
    </button>

</body>

</html>