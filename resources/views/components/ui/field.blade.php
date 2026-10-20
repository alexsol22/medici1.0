@props(['label', 'name'])
<div {{ $attributes->class('') }}>
    <label class="label">{{ $label }}</label>
    {{ $slot }}
    @error($name) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
