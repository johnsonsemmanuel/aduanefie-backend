<div style="margin-bottom:18px;">
    @php
        $portalLogo = \App\Models\BusinessSetting::where('key', 'logo')->first();
        $portalLogoUrl = $portalLogo ? \App\CentralLogics\Helpers::get_full_url('business', $portalLogo->value, $portalLogo->storage[0]?->value ?? 'public', 'logo') : null;
    @endphp
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;color:var(--brand,#2E6545);font-weight:700;">
        @if($portalLogoUrl)
            <img src="{{ $portalLogoUrl }}" alt="" style="height:36px;max-width:150px;object-fit:contain;" onerror="this.style.display='none'">
        @endif
        <span>Aduanefie</span>
    </div>
    <h1 style="margin:0 0 6px;">{{ $heading ?? 'Sign in' }}</h1>
    @if(!empty($subtitle))
        <p class="muted small" style="margin:0;">{{ $subtitle }}</p>
    @endif
</div>
