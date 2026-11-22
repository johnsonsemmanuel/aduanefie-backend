<div style="margin-bottom:18px;">
    <img src="https://aduanefie.com/static/aduanefie_logo.png" alt="Aduanefie" style="height:36px;width:auto;display:block;margin-bottom:14px;" onerror="this.style.display='none'">
    <h1 style="margin:0 0 6px;">{{ $heading ?? 'Sign in' }}</h1>
    @if(!empty($subtitle))
        <p class="muted small" style="margin:0;">{{ $subtitle }}</p>
    @endif
</div>
