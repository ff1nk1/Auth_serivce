<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin');
    }
}