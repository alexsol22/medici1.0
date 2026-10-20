@props(['user', 'preview' => null])
@php
    $iniziali = strtoupper(mb_substr($user->name, 0, 1).mb_substr((string) $user->cognome, 0, 1));
    $src = null;
    if ($preview && method_exists($preview, 'temporaryUrl')) {
        try {
            $src = $preview->temporaryUrl();
        } catch (\Throwable) {
            $src = null;
        }
    }
    $src ??= $user->foto_path ? route('profilo.foto', [$user, 'v' => md5($user->foto_path)]) : null;
@endphp
<span {{ $attributes->class('flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-sky-100 font-bold text-sky-700') }}>
    @if ($src)
        <img src="{{ $src }}" alt="Foto di {{ $user->nome_completo }}" class="h-full w-full object-cover">
    @else
        {{ $iniziali }}
    @endif
</span>
