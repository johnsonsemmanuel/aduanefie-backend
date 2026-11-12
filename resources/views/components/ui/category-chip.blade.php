@props([
    'name' => null,
    'slug' => null,
    'active' => false,
    'image' => null,
    'onClick' => null,
])

<a href="{{ $slug ? route('market.show', $slug) : '#' }}"
   class="category-chip {{ $active ? 'active' : '' }}"
   {{ $onClick ? "onclick=\"{$onClick}\"" : '' }}
   style="{{ $image ? 'background-image: url(' . $image . ');' : '' }}">

    @if($image)
        <img src="{{ $image }}" alt="{{ $name }}" class="category-chip-img onerror-image"
             data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}">
    @endif

    <span class="category-chip-label">{{ $name }}</span>

    @if($active)
        <i class="tio-check category-chip-check"></i>
    @endif
</a>

<style>
    .category-chip {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 12px 8px;
        border-radius: 10px;
        border: 2px solid transparent;
        background: #fff;
        color: #67513A;
        font-weight: 600;
        font-size: 13px;
        text-align: center;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
        min-width: 80px;
    }

    .category-chip:hover {
        border-color: var(--primary-clr, #67513A);
        transform: translateY(-2px);
    }

    .category-chip.active {
        border-color: var(--primary-clr, #67513A);
        background: #F0F7EE;
        box-shadow: 0 4px 12px rgba(139, 159, 105, 0.15);
    }

    .category-chip-img {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 4px;
    }

    .category-chip-label {
        font-size: 12px;
        line-height: 1.2;
        display: block;
    }

    .category-chip-check {
        color: var(--primary-clr, #67513A);
        font-size: 10px;
    }

    @media (max-width: 576px) {
        .category-chip {
            padding: 10px 6px;
            min-width: 70px;
        }
        .category-chip-img {
            width: 36px;
            height: 36px;
        }
        .category-chip-label {
            font-size: 11px;
        }
    }
</style>
