{{-- Foto de perfil o iniciales. Requiere $u; $size opcional (px). --}}
@php $size = $size ?? 36; @endphp
@if ($u->avatarUrl())
    <img class="avatar" src="{{ $u->avatarUrl() }}" alt="" width="{{ $size }}" height="{{ $size }}" style="width:{{ $size }}px;height:{{ $size }}px">
@else
    <span class="avatar initials" aria-hidden="true" style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ round($size * .38) }}px">{{ $u->initials() }}</span>
@endif
