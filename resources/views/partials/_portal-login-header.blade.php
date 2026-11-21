<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
    <img src="https://aduanefie.com/static/aduanefie_logo.png" alt="Aduanefie" style="height:36px;width:auto;" onerror="this.style.display='none'">
    <span style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#EDF2E4;color:#5E7042;border:1px solid #C8D6AF;">
        {{ $badge ?? 'Aduanefie Portal' }}
    </span>
</div>
<h1>{{ $heading ?? 'Sign in' }}</h1>
@if(!empty($subtitle))
    <p class="muted small" style="margin-top:0;">{{ $subtitle }}</p>
@endif
