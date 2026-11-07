@php
    $seoLocale = in_array(app()->getLocale(), ['en','gu','hi'], true) ? app()->getLocale() : 'en';
    $seoUrl = \App\Support\PublicSeo::url('', $seoLocale);
@endphp
<meta name="robots" content="{{ \App\Support\PublicSeo::indexable() ? 'index, follow, max-image-preview:large' : 'noindex, nofollow' }}">
<link rel="canonical" href="{{ $seoUrl }}">
@foreach(['en','gu','hi'] as $language)
<link rel="alternate" hreflang="{{ $language }}" href="{{ \App\Support\PublicSeo::url('', $language) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ \App\Support\PublicSeo::url() }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="BizPilot">
<meta property="og:title" content="BizPilot · {{ __('landing.title') }}">
<meta property="og:description" content="{{ __('landing.description') }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:locale" content="{{ ['en'=>'en_IN','gu'=>'gu_IN','hi'=>'hi_IN'][$seoLocale] }}">
<meta property="og:image" content="{{ \App\Support\PublicSeo::url('assets/brand/social-preview.png') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="BizPilot — GST invoices, quotations and payment tracking">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="BizPilot · {{ __('landing.title') }}">
<meta name="twitter:description" content="{{ __('landing.description') }}">
<meta name="twitter:image" content="{{ \App\Support\PublicSeo::url('assets/brand/social-preview.png') }}">
