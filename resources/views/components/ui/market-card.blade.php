@props([
    'product' => null,
    'image' => null,
    'name' => null,
    'price' => null,
    'farming_status' => null,
    'is_backyard' => false,
    'store_name' => null,
    'rating' => 0,
    'link' => '#',
    'cardClass' => null,
])

<article class="market-card {{ $cardClass }}">
    <a href="{{ $link }}" class="market-card-link">
        <div class="market-card-image">
            @if($image)
                <img src="{{ $image }}" alt="{{ $name ?? 'Product' }}" class="market-card-img onerror-image"
                     data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}">
            @else
                <div class="market-card-img-placeholder">
                    <i class="tio-package"></i>
                </div>
            @endif

            @if($farming_status === 'harvest_ready')
                <span class="market-badge market-badge--harvest">{{ translate('messages.harvest_ready') }}</span>
            @elseif($farming_status === 'farming')
                <span class="market-badge market-badge--farming">{{ translate('messages.farming') }}</span>
            @endif

            @if($is_backyard)
                <span class="market-badge market-badge--backyard">{{ translate('messages.backyard_grown') }}</span>
            @endif
        </div>

        <div class="market-card-content">
            @if($store_name)
                <span class="market-card-store">{{ $store_name }}</span>
            @endif

            <h3 class="market-card-title">{{ $name ?? translate('messages.product') }}</h3>

            <div class="market-card-rating">
                @for($i = 1; $i <= 5; $i++)
                    <i class="tio-star {{ $i <= $rating ? 'filled' : '' }}"></i>
                @endfor
                <span class="rating-text">{{ $rating }}</span>
            </div>

            @if($price)
                <span class="market-card-price">{{\App\CentralLogics\Helpers::format_currency($price)}}</span>
            @endif
        </div>
    </a>
</article>

<style>
    .market-card {
        border: 1px solid #E0D6BD;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .market-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(79, 62, 44, 0.12);
        border-color: var(--primary-clr, #67513A);
    }

    .market-card-link {
        display: flex;
        flex-direction: column;
        height: 100%;
        text-decoration: none;
        color: inherit;
    }

    .market-card-image {
        position: relative;
        height: 200px;
        overflow: hidden;
        background: #FAF8F5;
    }

    .market-card-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .market-card:hover .market-card-img {
        transform: scale(1.05);
    }

    .market-card-img-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #67513A;
        font-size: 48px;
    }

    .market-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        z-index: 10;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .market-badge--harvest {
        background: #155724;
    }

    .market-badge--farming {
        background: #856404;
    }

    .market-badge--backyard {
        position: absolute;
        top: 10px;
        right: 10px;
        left: auto;
        background: #67513A;
    }

    .market-card-content {
        padding: 16px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .market-card-store {
        display: block;
        font-size: 12px;
        color: var(--secondary-clr, #8B9F69);
        font-weight: 600;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .market-card-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--primary-clr, #67513A);
        margin: 0 0 8px 0;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .market-card-rating {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
    }

    .market-card-rating i {
        color: #8B9F69;
        font-size: 12px;
    }

    .market-card-rating i.filled {
        color: #F7A629;
    }

    .market-card-rating .rating-text {
        font-size: 11px;
        color: #666;
    }

    .market-card-price {
        font-size: 18px;
        font-weight: 800;
        color: var(--primary-clr, #67513A);
        margin-top: auto;
    }

    @media (max-width: 576px) {
        .market-card-image {
            height: 160px;
        }
        .market-card-title {
            font-size: 15px;
        }
        .market-card-price {
            font-size: 16px;
        }
    }
</style>
