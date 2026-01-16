<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Gallery By Comcenter</title>
    <meta name="description" content="Browse our complete photography gallery featuring events, weddings, fashion, commercial, and portrait collections.">


    <!-- Vite-built CSS -->
    <?= vite('css/gallery.css') ?>
    <script>
        window.APP_CONFIG = {
            apiBase: "<?= rtrim(base_url('api'), '/') ?>"
        };
    </script>

    <!-- Vite-built JS -->
    <?= vite('js/gallery.js') ?>
</head>

<body class="font-sans antialiased bg-gray-50">

    <!-- Navigation -->
    <nav class="sticky top-0 z-50 bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0">
                    <a href="<?= base_url() ?>" class="text-2xl font-bold text-gray-900">Gallery by comcenter</a>
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <a href="<?= base_url() ?>" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium transition-colors">Gallery</a>
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
                <a href="<?= base_url() ?>" class="block text-gray-700 hover:bg-gray-50 px-3 py-2 text-base font-medium">Gallery</a>
            </div>
        </div>
    </nav>

    <!-- Gallery Section -->
    <section class="py-8 lg:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">Gallery</h2>
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
</body>

</html>