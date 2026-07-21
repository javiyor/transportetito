<div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #374151;">
    <div style="flex-shrink: 0;">
        @if(file_exists(public_path('images/logo.jpeg')))
            <img src="{{ asset('images/logo.jpeg') }}" alt="Logo" style="height: 50px; width: auto;">
        @endif
    </div>
    <div>
        <div style="font-size: 16px; font-weight: bold; color: #111827;">{{ $empresa->razon_social ?? 'Transporte Tito' }}</div>
        <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
            @if(!empty($empresa->telefono)) Tel: {{ $empresa->telefono }}@endif
            @if(!empty($empresa->telefono) && !empty($empresa->email)) &middot; @endif
            @if(!empty($empresa->email)) {{ $empresa->email }}@endif
            @if(!empty($empresa->whatsapp)) &middot; WhatsApp: {{ $empresa->whatsapp }}@endif
        </div>
    </div>
</div>
