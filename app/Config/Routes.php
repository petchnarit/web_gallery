<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// หน้าเว็บ
$routes->get('/', 'Home::gallery');
$routes->get('/test', 'Home::test');
// $routes->get('gallery',                'Home::gallery');
$routes->get('view/(:segment)', 'Home::gallery_view/$1');

// API (จัดลำดับให้ file มาก่อน catch-all)
$routes->group('api', static function ($routes) {
        $routes->get('file', 'Api\Gallery::file');     // ต้องมาก่อน
        $routes->get('raw', 'Api\Gallery::raw');   // <-- สำคัญ
        $routes->get('/',    'Api\Gallery::index');
        $routes->get('',     'Api\Gallery::index');
        $routes->get('(:segment)', 'Api\Gallery::show/$1'); // รับ short id
});


