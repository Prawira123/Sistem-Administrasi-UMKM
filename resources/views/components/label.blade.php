{{-- Label form. Contoh: <x-label for="username" value="Username" :wajib="true" /> --}}
@props(['value' => null, 'wajib' => false])

<label {{ $attributes->merge(['class' => 'mb-1 block text-sm font-medium text-gray-700']) }}>
    {{ $value ?? $slot }}
    @if ($wajib)<span class="text-danger-600">*</span>@endif
</label>
