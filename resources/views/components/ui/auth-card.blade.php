@props([
    'title' => null,
    'subtitle' => null,
    'class' => null,
    'footer' => null,
])

<div class="auth-card {{ $class }}">
    @if($title || $subtitle)
        <div class="auth-header">
            @if($title)
                <h2 class="title">{{ $title }}</h2>
            @endif
            @if($subtitle)
                <div class="subtitle">{{ $subtitle }}</div>
            @endif
        </div>
    @endif

    <div class="auth-card-body">
        {{ $slot }}
    </div>

    @if($footer)
        <div class="auth-card-footer">
            {{ $footer }}
        </div>
    @endif
</div>

<style>
    .auth-card {
        background: #fff;
        border: 1px solid #E0D6BD;
        border-radius: 12px;
        padding: 32px 40px;
        box-shadow: 0 12px 40px rgba(79, 62, 44, 0.10);
        max-width: 460px;
        margin: 0 auto;
    }

    .auth-header {
        margin-bottom: 24px;
    }

    .auth-header .title {
        font-size: 22px;
        font-weight: 700;
        color: var(--primary-clr, #67513A);
        margin-bottom: 8px;
    }

    .auth-header .subtitle {
        color: #67513A;
        font-size: 14px;
        opacity: 0.85;
    }

    .auth-card-body {
        /* form content goes here */
    }

    .auth-card-footer {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid #E0D6BD;
        text-align: center;
        color: #67513A;
        font-size: 13px;
    }

    @media (max-width: 576px) {
        .auth-card {
            padding: 24px;
            margin: 0 16px;
        }
    }
</style>
