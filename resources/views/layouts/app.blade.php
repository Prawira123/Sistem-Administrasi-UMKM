{{--
    Layout utama aplikasi (setelah login).
    Pemakaian di halaman:
    <x-layouts::app title="Data Barang"> ... isi halaman ... </x-layouts::app>
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 print:bg-white font-sans text-gray-800 antialiased" x-data="{ sidebarBuka: false }">

    {{-- Latar gelap saat sidebar dibuka di layar kecil --}}
    <div x-show="sidebarBuka" x-cloak x-transition.opacity @click="sidebarBuka = false"
        class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="sidebarBuka ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-40 flex w-64 print:hidden -translate-x-full flex-col bg-primary-900 text-primary-50 transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-16 items-center justify-between gap-2 border-b border-white/10 px-5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold">
                <span class="flex size-8 items-center justify-center rounded-lg bg-primary-500 text-white">
                    <x-icon name="cube" class="size-5" />
                </span>
                <span class="leading-tight">Toko UMKM<br><span class="text-xs font-normal text-primary-200">Stok &amp; Keuangan</span></span>
            </a>
            <button type="button" class="rounded p-1 hover:bg-white/10 lg:hidden" @click="sidebarBuka = false" aria-label="Tutup menu">
                <x-icon name="x-mark" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4">
            @include('layouts.partials.sidebar-menu')
        </nav>
    </aside>

    <div class="lg:pl-64 print:pl-0">
        {{-- Header --}}
        <header class="sticky top-0 z-20 flex h-16 print:hidden items-center gap-3 border-b border-gray-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 lg:hidden" @click="sidebarBuka = true" aria-label="Buka menu">
                <x-icon name="bars-3" class="size-6" />
            </button>

            <div class="flex-1"></div>

            {{-- Lonceng notifikasi stok minimum --}}
            @include('layouts.partials.notifikasi-stok')

            {{-- Menu pengguna --}}
            <div class="relative" x-data="{ buka: false }" @click.outside="buka = false">
                <button type="button" @click="buka = !buka" class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-gray-100">
                    <span class="flex size-9 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->nama, 0, 1)) }}
                    </span>
                    <span class="hidden text-left sm:block">
                        <span class="block text-sm font-medium text-gray-800">{{ auth()->user()->nama }}</span>
                        <span class="block text-xs text-gray-500">{{ auth()->user()->labelRole() }}</span>
                    </span>
                    <x-icon name="chevron-down" class="size-4 text-gray-400" />
                </button>

                <div x-show="buka" x-cloak x-transition
                    class="absolute right-0 mt-2 w-56 rounded-xl border border-gray-200 bg-white py-2 shadow-lg">
                    <div class="border-b border-gray-100 px-4 pb-2">
                        <p class="text-sm font-medium text-gray-800">{{ auth()->user()->nama }}</p>
                        <p class="text-xs text-gray-500">{{ '@'.auth()->user()->username }} &middot; {{ auth()->user()->labelRole() }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="px-2 pt-2">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-sm text-danger-600 hover:bg-danger-50">
                            <x-icon name="logout" class="size-4" /> Keluar
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8 print:p-0">
            <x-alert />
            {{ $slot }}
        </main>
    </div>
</body>
</html>
