<x-layouts::guest title="Login">
    <div class="mb-6 text-center">
        <span class="mx-auto mb-3 flex size-14 items-center justify-center rounded-2xl bg-primary-600 text-white shadow-lg">
            <x-icon name="cube" class="size-8" />
        </span>
        <h1 class="text-xl font-semibold text-gray-900">Sistem Monitoring Stok &amp; Keuangan</h1>
        <p class="mt-1 text-sm text-gray-500">Toko UMKM Sekretariat DPRD Kabupaten Badung</p>
    </div>

    <x-card>
        <x-alert class="mb-4" />

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
            @csrf

            <div>
                <x-label for="username" value="Username" />
                <x-input name="username" :value="old('username')" autocomplete="username" autofocus required />
                <x-input-error for="username" />
            </div>

            <div x-data="{ lihat: false }">
                <x-label for="password" value="Password" />
                <div class="relative">
                    <x-input name="password" type="password" x-bind:type="lihat ? 'text' : 'password'"
                        autocomplete="current-password" class="pr-16" required />
                    <button type="button" x-on:click="lihat = !lihat"
                        class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-gray-500 hover:text-gray-700"
                        x-text="lihat ? 'Sembunyikan' : 'Lihat'">Lihat</button>
                </div>
                <x-input-error for="password" />
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                Ingat saya
            </label>

            <x-button class="w-full" icon="lock-closed">Masuk</x-button>
        </form>
    </x-card>

    <p class="mt-6 text-center text-xs text-gray-400">&copy; {{ now()->year }} Toko UMKM Sekretariat DPRD Kabupaten Badung</p>
</x-layouts::guest>
