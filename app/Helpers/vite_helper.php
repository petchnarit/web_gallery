<?php
//v1
// function vite(string $entry)
// {
//     $manifestPath = FCPATH . 'build/manifest.json';

//     if (!file_exists($manifestPath)) {
//         return '<!-- Vite manifest not found -->';
//     }

//     $manifest = json_decode(file_get_contents($manifestPath), true);

//     if (!isset($manifest[$entry])) {
//         return "<!-- Vite entry not found: {$entry} -->";
//     }

//     $base = rtrim(config('App')->baseURL, '/');

//     return '<script type="module" src="' .
//         $base . '/build/' . $manifest[$entry]['file'] .
//         '"></script>';
// }


//v2
// function vite(string $entry)
// {
//     $manifestPath = FCPATH . 'build/manifest.json';

//     if (!file_exists($manifestPath)) {
//         return "<!-- Vite manifest not found -->";
//     }

//     $manifest = json_decode(file_get_contents($manifestPath), true);

//     if (!isset($manifest[$entry])) {
//         return "<!-- Vite entry not found: {$entry} -->";
//     }

//     $file = $manifest[$entry]['file'];

//     // CSS
//     if (str_ends_with($file, '.css')) {
//         return '<link rel="stylesheet" href="/gallery/build/' . $file . '">';
//     }

//     // JS
//     return '<script type="module" src="/gallery/build/' . $file . '" defer></script>';
// }

//v3
function vite(string $entry)
{
    $manifestPath = FCPATH . 'build/manifest.json';

    if (!file_exists($manifestPath)) {
        return "<!-- Vite manifest not found -->";
    }

    $manifest = json_decode(file_get_contents($manifestPath), true);

    if (!isset($manifest[$entry])) {
        return "<!-- Vite entry not found: {$entry} -->";
    }

    $file = $manifest[$entry]['file'];
    $html = '';

    // CSS
    if (str_ends_with($file, '.css')) {
        $html .= '<link rel="stylesheet" href="/gallery/build/' . $file . '">' . "\n";

        // ==== Preload fonts from manifest ====
        foreach ($manifest as $key => $info) {
            if (isset($info['isAsset']) && $info['isAsset'] && preg_match('/\.woff2$/', $info['file'])) {
                $html .= '<link rel="preload" href="/gallery/build/' . $info['file'] . '" as="font" type="font/woff2" crossorigin>' . "\n";
            }
        }

        return $html;
    }

    // JS
    $html .= '<script type="module" src="/gallery/build/' . $file . '" defer></script>';

    return $html;
}
