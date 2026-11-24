@props([
    'title' => null,
    'subtitle' => null,
    'class' => null,
    'actions' => null,
])

<section class="section-header {{ $class }}">
    <div class="section-header-content">
        @if($title || $subtitle)
            <div class="section-title-group">
                @if($title)
                    <h2 class="section-title">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="section-subtitle">{{ $subtitle }}</p>
                @endif
            </div>
        @endif

        @if($actions)
            <div class="section-actions">
                {{ $actions }}
            </div>
        @endif
    </div>
</section>

<style>
    .section-header {
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .section-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        flex-wrap: wrap;
        gap: 15px;
    }

    .section-title-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .section-title {
        font-size: 22px;
        font-weight: 700;
        color: var(--primary-clr, #67513A);
        margin: 0;
    }

    .section-subtitle {
        font-size: 14px;
        color: #67513A;
        opacity: 0.8;
        margin: 0;
    }

    .section-actions {
        display: flex;
        gap: 10px;
    }

    @media (max-width: 576px) {
        .section-title {
            font-size: 18px;
        }
        .section-header {
            flex-direction: column;
            align-items: stretch;
        }
        .section-actions {
            justify-content: flex-end;
        }
    }
</style>
