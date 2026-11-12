@extends('layouts.landing.app')

@php($business_name = \App\CentralLogics\Helpers::get_settings('business_name'))
@section('title', translate('messages.landing_page') . ' | ' . $business_name != 'null' ? $business_name : 'Aduanefie')

@push('css_or_js')
<style>
    .farming-badge--farming { background: #856404; }
    .farming-badge--harvest { background: #155724; }
    .farming-badge--backyard { background: #67513A; }
    .market-card { border: 1px solid #E0D6BD; border-radius: 12px; overflow: hidden; background: #fff; transition: all 0.2s ease; }
    .market-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(79, 62, 44, 0.12); border-color: var(--primary-clr, #67513A); }
    .market-card-image { position: relative; height: 200px; overflow: hidden; background: #FAF8F5; }
    .market-card-img { width: 100%; height: 100%; object-fit: cover; }
    .market-badge { position: absolute; top: 10px; left: 10px; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #fff; z-index: 10; text-transform: uppercase; }
    .market-badge--harvest { background: #155724; }
    .market-badge--farming { background: #856404; }
    .market-badge--backyard { position: absolute; top: 10px; right: 10px; left: auto; background: #67513A; }
    .market-card-content { padding: 16px; display: flex; flex-direction: column; flex: 1; }
    .market-card-store { display: block; font-size: 12px; color: var(--secondary-clr, #8B9F69); font-weight: 600; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
    .market-card-title { font-size: 16px; font-weight: 700; color: var(--primary-clr, #67513A); margin: 0 0 8px 0; line-height: 1.3; }
    .market-card-price { font-size: 18px; font-weight: 800; color: var(--primary-clr, #67513A); margin-top: auto; }
    .category-chip { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 12px 8px; border-radius: 10px; border: 2px solid transparent; background: #fff; color: #67513A; font-weight: 600; font-size: 13px; text-align: center; text-decoration: none; transition: all 0.2s ease; min-width: 80px; }
    .category-chip.active { border-color: var(--primary-clr, #67513A); background: #F0F7EE; box-shadow: 0 4px 12px rgba(139, 159, 105, 0.15); }
    .preloader { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; padding: 40px 20px; }
</style>
@endpush

@section('content')

    <!-- Basic Settings -->
    @php($front_end_url = \App\Models\BusinessSetting::where(['key' => 'front_end_url'])->first())
    @php($front_end_url = $front_end_url ? $front_end_url->value : null)
    @php($landing_page_text = \App\Models\BusinessSetting::where(['key' => 'landing_page_text'])->first())
    @php($landing_page_text = isset($landing_page_text->value) ? json_decode($landing_page_text->value, true) : null)
    @php($landing_page_links = \App\Models\BusinessSetting::where(['key' => 'landing_page_links'])->first())
    @php($landing_page_links = isset($landing_page_links->value) ? json_decode($landing_page_links->value, true) : null)
    @php($landing_page_images = \App\Models\BusinessSetting::where(['key' => 'landing_page_images'])->first())
    @php($landing_page_images = isset($landing_page_images->value) ? json_decode($landing_page_images->value, true) : null)

    <!-- ==== Banner Section Starts Here ==== -->
    @php($logo = \App\Models\BusinessSetting::where(['key' => 'logo'])->first())
    <section class="banner-section position-relative">
        <div class="container">
            <div class="banner-content wow fadeInUp">
                <h1 class="title">{{ $landing_data['fixed_header_title'] }}</h1>
                <img class="w-100 onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                    src="{{ \App\CentralLogics\Helpers::get_full_url('business', $logo?->value ?? '', $logo?->storage[0]?->value ?? 'public', 'favicon') }}"
                    alt="">
                <div class="text">
                    {{ $landing_data['fixed_header_sub_title'] }}
                </div>
            </div>
        </div>
        <div class="px-xl-5 d-flex justify-content-center text-base banner-svg-img">
            <x-ui.preloader size="lg" text="{{ translate('messages.loading') }}" />
        </div>
    </section>
    <!-- ==== Banner Section Ends Here ==== -->

    <!-- ==== Ecommerce Venture Section Starts Here ==== -->
    <x-ui.section-header title="{{ $landing_data['fixed_module_title'] }}" subtitle="{{ $landing_data['fixed_module_sub_title'] }}" />

    <section class="ecommerce-venture-section">
        <div class="container max-w1060">
            <div class="nav--tabs-wrapper">
                @php($modules = \App\Models\Module::Active()->get())
                <div class="nav--tabs">
                    <div class="owl-theme owl-carousel" id="sync2">
                        @foreach ($modules as $key => $item)
                            <div class="item">
                                <img class="__img-50 onerror-image"
                                    data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                                    src="{{ $item['icon_full_url'] ?? asset('public/assets/admin/img/100x100/2.png') }}"
                                    alt="image">
                                <div class="txt d-block">{{ translate("messages.{$item->module_name}") }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="owl-theme owl-carousel" id="sync1">
                    @foreach ($modules as $key => $item)
                        <div class="row flex-wrap-reverse justify-content-center align-items-center">
                            <div class="col-lg-6 col-md-8">
                                <div class="venture-content mt-4 mt-lg-0">
                                    <div class="venture-content-box">
                                        {!! $item->description ?? '' !!}
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-8">
                                <div class="venture-img mx-1">
                                    <img src="{{ $item['thumbnail_full_url'] ?? asset('public/assets/admin/img/100x100/2.png') }}"
                                        class="img-fluid onerror-image w-100 mw-450"
                                        data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                                        alt="image">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    <!-- ==== Ecommerce Venture Section Ends Here ==== -->

    <!-- ==== Main Category Section Starts Here ==== -->
    @php($promotion_banner = $landing_data['promotional_banners'])
    @if ($promotion_banner && count($promotion_banner) > 0)
        <x-ui.section-header title="{{ translate('messages.main_categories') }}" />

        <section class="main-category overflow-hidden pt-80 pb-80">
            <div class="container">
                <div class="overflow-hidden">
                    <div class="main-category-slider owl-theme owl-carousel">
                        @foreach ($promotion_banner as $item)
                            <div class="category-slide-item"
                                style="background: url({{ $item['image_full_url'] }}) no-repeat center center / cover">
                                <div>
                                    <h2 class="title">{{ $item['title'] ?? '' }}</h2>
                                    <div class="text">{{ $item['sub_title'] ?? '' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif
    <!-- ==== Main Category Section Ends Here ==== -->

    <!-- ==== Learn Feature Section Starts Here ==== -->
    <x-ui.section-header title="{{ $landing_data['feature_title'] }}" subtitle="{{ $landing_data['feature_short_description'] }}" />

    <section class="learn-feature-section"
        style="background: url({{ asset('public/assets/landing/img/learn-feature-bg.svg') }}) no-repeat center center / cover">
        <div class="container position-relative">
            <div class="row gy-5 gx-0 gx-xl-4 align-items-center">
                <div class=" col-lg-6 pe-lg-5">
                    <div class="learn-feature-content wow fadeInUp">
                        <div class="section-header text-start mb-0">
                            <h2 class="title">
                                {{ $landing_data['feature_title'] }}
                            </h2>
                            <div class="text">
                                {{ $landing_data['feature_short_description'] }}
                            </div>
                        </div>
                    </div>
                </div>
                @php($feature = $landing_data['features'])
                <?php
                $array1 = array_slice($feature, 0, ceil(count($feature) / 2));
                $array2 = array_slice($feature, ceil(count($feature) / 2));
                ?>
                @if (isset($feature) && ($x = count($feature) > 0))

                    <div class="col-lg-6">
                        <div class="learn-feature-wrapper py-5">
                            <div class="row g-4 learn-feature-item-group">
                                <div class="col-6">
                                    <div class="row gy-4 gy-sm-5">
                                        <!-- Item -->
                                        @foreach ($array1 as $item)
                                            <div class="col-12">
                                                <div class="learn-feature-item">
                                                    <div class="learn-feature-icon">
                                                        <img src="{{ $item['image_full_url'] }}"
                                                            alt="{{ $item['title'] ?? '' }}">
                                                    </div>
                                                    <div class="learn-feature-item-content">
                                                        <h5 class="subttle">{{ $item['title'] ?? '' }}</h5>
                                                        <div class="text">
                                                            {{ $item['sub_title'] ?? '' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                        <!-- Item End-->
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="row gy-4 gy-sm-5 mt-5 pt-5">
                                        @foreach ($array2 as $item)
                                            <div class="col-12">
                                                <div class="learn-feature-item">
                                                    <div class="learn-feature-icon">
                                                        <img src="{{ $item['image_full_url'] }}"
                                                            alt="{{ $item['title'] ?? '' }}">
                                                    </div>
                                                    <div class="learn-feature-item-content">
                                                        <h5 class="subttle">{{ $item['title'] ?? '' }}</h5>
                                                        <div class="text">
                                                            {{ $item['sub_title'] ?? '' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
    <!-- ==== Learn Feature Section Ends Here ==== -->

    <!-- ==== Delivery Area Section Starts Here ==== -->
    @if ($landing_data['available_zone_status'] && $landing_data['available_zone_list'])
        <x-ui.section-header title="{{ $landing_data['available_zone_title'] }}" subtitle="{{ $landing_data['available_zone_short_description'] }}" />

        <section class="delivery-area-section">
            <div class="container">
                <div class="row text-center gy-4 flex-wrap-reverse align-items-center">
                    <div class="col-lg-5 col-xl-6 text-lg-start">
                        <div class="zone-list-container">
                            <div class="zone-list-wrapper mt-4">
                                <div class="zone-list">
                                    @foreach ($landing_data['available_zone_list'] as $zone)
                                        @if (count($zone['modules']->toArray()) > 0)
                                            <span class="item" data-bs-trigger="hover" data-bs-toggle="popover"
                                                data-bs-placement="top" title="{{ $zone['display_name'] }}"
                                                data-bs-content="{{ count($zone['modules']->toArray()) > 0 ? implode(', ', $zone['modules']->toArray()) . ' ' . translate('are_available.') : translate('right_now_no_module_available.') }}">
                                                {{ $zone['display_name'] }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 col-xl-6 text-lg-end">
                        <img src="{{ $landing_data['available_zone_image_full_url'] }}" alt=""
                            class="img-fluid w-100 mw-450">
                    </div>
                </div>
            </div>
        </section>
    @endif
    <!-- ==== Delivery Area Section Ends Here ==== -->

    <!-- ==== Refer Section Starts Here ==== -->
    <x-ui.section-header title="{{ $landing_data['fixed_referal_title'] }}" subtitle="{{ $landing_data['fixed_referal_sub_title'] }}" />

    <section class="refer-section pb-80">
        <div class="container">
            <div class="row align-items-center text-center gy-4 flex-wrap-reverse">
                <div class="col-lg-5 col-xl-6 text-lg-start">
                    <div class="refer-content">
                        <h2 class="title">{{ $landing_data['fixed_referal_title'] }}</h2>
                        <div class="text">{{ $landing_data['fixed_referal_sub_title'] }}</div>
                    </div>
                </div>
                <div class="col-lg-7 col-xl-6 text-lg-end">
                    <x-ui.preloader size="lg" text="{{ translate('messages.referral_program_loading') }}" />
                </div>
            </div>
        </div>
    </section>
    <!-- ==== Refer Section Ends Here ==== -->

@endsection

@push('script_2')
<script>
    "use strict";
    $(".main-category-slider").owlCarousel({
        loop: true,
        nav: false,
        dots: true,
        items: 1,
        margin: 12,
        autoplay: true,
        rtl: {{ $landing_site_direction === 'rtl'?'true':'false' }},
    });
    $(".testimonial-slider").owlCarousel({
        loop: false,
        margin: 15,
        responsiveClass: true,
        nav: false,
        dots: false,
        autoplay: true,
        autoplayTimeout: 2000,
        autoplayHoverPause: true,
        items: 1,
        rtl: {{ $landing_site_direction === 'rtl'?'true':'false' }},
        responsive: {
            768: {
                items: 2,
                margin: 20,
            },
            992: {
                items: 3,
                margin: 20,
            },
            1200: {
                items: 3,
                margin: 22,
            },
        },
    });
    $(".owl-prev").html('<i class="fas fa-angle-left">');
    $(".owl-next").html('<i class="fas fa-angle-right">');
    let sync1 = $("#sync1");
     let sync2 = $("#sync2");
     let thumbnailItemClass = ".owl-item";
     let slides = sync1
        .owlCarousel({
            items: 1,
            loop: false,
            margin: 30,
            mouseDrag: true,
            touchDrag: true,
            pullDrag: false,
            scrollPerPage: true,
            autoplayHoverPause: false,
            nav: false,
            dots: false,
            rtl: {{ $landing_site_direction === 'rtl'?'true':'false' }},
        })
        .on("changed.owl.carousel", syncPosition);

    function syncPosition(el) {
        let  $owl_slider = $(this).data("owl.carousel");
        let loop = $owl_slider.options.loop;
        let current;
        if (loop) {
            let count = el.item.count - 1;
             current = Math.round(
                el.item.index - el.item.count / 2 - 0.5
            );
            if (current < 0) {
                current = count;
            }
            if (current > count) {
                current = 0;
            }
        } else {
             current = el.item.index;
        }

        let owl_thumbnail = sync2.data("owl.carousel");
        let itemClass = "." + owl_thumbnail.options.itemClass;

        let thumbnailCurrentItem = sync2
            .find(itemClass)
            .removeClass("synced")
            .eq(current);
        thumbnailCurrentItem.addClass("synced");

        if (!thumbnailCurrentItem.hasClass("active")) {
            let duration = 500;
            sync2.trigger("to.owl.carousel", [current, duration, true]);
        }
    }

    let thumbs = sync2
        .owlCarousel({
            items: 2,
            loop: false,
            margin: 0,
            autoplay: false,
            nav: true,
            navText: ["",""],
            dots: false,
            mouseDrag: true,
            touchDrag: true,
            rtl: {{ $landing_site_direction === 'rtl'?'true':'false' }},
            responsive: {
                400: {
                    items: 3,
                },
                768: {
                    items: 6,
                },
                1200: {
                    items: 6,
                },
            },
            onInitialized: function (e) {
                let thumbnailCurrentItem = $(e.target)
                    .find(thumbnailItemClass)
                    .eq(this._current);
                thumbnailCurrentItem.addClass("synced");
            },
        })
        .on("click", thumbnailItemClass, function (e) {
            e.preventDefault();
            let duration = 500;
            let itemIndex = $(e.target).parents(thumbnailItemClass).index();
            sync1.trigger("to.owl.carousel", [itemIndex, duration, true]);
        })
        .on("changed.owl.carousel", function (el) {
            let number = el.item.index;
            let  $owl_slider = sync1.data("owl.carousel");
            $owl_slider.to(number, 500, true);
        });
    sync1.owlCarousel();
</script>
@endpush
