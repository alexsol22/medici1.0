@props(['alt' => null])

<img
    src="{{ asset(config('brand.logo')) }}"
    alt="{{ $alt ?? config('app.name') }}"
    {{ $attributes->merge(['class' => 'h-9 w-auto']) }}
>
