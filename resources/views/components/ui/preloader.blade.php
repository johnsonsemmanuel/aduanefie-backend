@props([
    'size' => 'lg',
    'text' => null,
    'class' => null,
])

<div class="preloader {{ $class }}">
    <div class="preloader-spinner preloader--{{ $size }}"></div>

    @if($text)
        <div class="preloader-text">{{ $text }}</div>
    @endif
</div>

<style>
    .preloader {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding: 40px 20px;
    }

    .preloader-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #E0D6BD;
        border-top-color: var(--primary-clr, #67513A);
        border-radius: 50%;
        animation: preloader-spin 1s linear infinite;
    }

    .preloader--sm {
        width: 24px;
        height: 24px;
        border-width: 2px;
    }

    .preloader--md {
        width: 32px;
        height: 32px;
        border-width: 2.5px;
    }

    .preloader--lg {
        width: 48px;
        height: 48px;
        border-width: 4px;
    }

    .preloader-text {
        font-size: 14px;
        color: #67513A;
        font-weight: 500;
    }

    @keyframes preloader-spin {
        to { transform: rotate(360deg); }
    }

    @media (max-width: 576px) {
        .preloader {
            padding: 20px 16px;
        }
        .preloader-text {
            font-size: 12px;
        }
    }
</style>
