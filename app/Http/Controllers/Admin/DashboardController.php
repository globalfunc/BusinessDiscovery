<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessOwner;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Full KPI/activity data lands in S4.1 — this session only wires the BO
     * count, which S1.3 makes available for free.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'totalBusinessOwners' => BusinessOwner::count(),
        ]);
    }
}
