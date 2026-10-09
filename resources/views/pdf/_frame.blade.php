{{-- Curvas de la marca y pie con contacto, repetidos en cada página. --}}
<img class="wave-top" src="{{ $waves['top'] }}" alt="">
<img class="wave-bottom" src="{{ $waves['bottom'] }}" alt="">
<div class="foot">
    @if ($clinic->instagram)<b>{{ $clinic->instagram }}</b>@endif
    @if ($clinic->phone) &nbsp;·&nbsp; WhatsApp {{ $clinic->phone }}@endif
    @php $sedeNames = $clinic->sedes->where('active', true)->pluck('name'); @endphp
    @if ($sedeNames->isNotEmpty())<br>{{ $sedeNames->implode(' · ') }}@endif
</div>
