@php
    $paths = [
        'hoy' => '<path d="M4 5h16v15H4z"/><path d="M8 3v4M16 3v4M4 10h16"/><path d="M9 15l2 2 4-4"/>',
        'agenda' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18M8 14h2M14 14h2M8 18h2"/>',
        'pacientes' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6"/><path d="M17 4a4 4 0 010 8M22 21c0-3-2-5-4-6"/>',
        'caja' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M3 11h18M7 3h10v4H7z"/><path d="M15 15h3"/>',
        'config' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'cotizacion' => '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/><path d="M9 13h7M9 17h5"/>',
        'cartera' => '<path d="M3 7h15a3 3 0 013 3v8a3 3 0 01-3 3H3z"/><path d="M3 7l12-4v4"/><circle cx="16.5" cy="14" r="1.5"/>',
        'gastos' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'reportes' => '<path d="M3 21h18"/><path d="M6 17V11M11 17V6M16 17v-4M21 17V9"/>',
        'alert' => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/>',
        'salir' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    ];
    $size = $size ?? 22;
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? '' !!}</svg>
