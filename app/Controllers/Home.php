<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function test()
    {
        return view('page/test');       // ไฟล์ View หน้า List
    }

    public function index()
    {
        return redirect()->to(base_url('gallery'));
    }

    public function gallery()
    {
        return view('page/gallery');       // ไฟล์ View หน้า List
    }

    public function gallery_view($id)
    {
        // $id ไม่ต้องส่งไปที่ View เพราะ JS จะอ่านจาก path เอง
        return view('page/gallery-view');  // ไฟล์ View หน้า Viewer
    }
}
