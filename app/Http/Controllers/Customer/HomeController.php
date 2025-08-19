<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    public function index()
    {
        // Simple home page - no complex data needed
        return view('home');
    }
}
