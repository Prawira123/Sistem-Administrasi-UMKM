<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard ringkas: barang aktif, stok menipis, dan (Administrator) penjualan hari ini serta saldo kas.
     */
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $user = $request->user();

        return view('dashboard', ['user' => $user] + $dashboard->ringkasan($user));
    }
}
