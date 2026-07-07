<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Real KPI/activity data lands in S4.1 — this session ships the shell only.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard');
    }
}
