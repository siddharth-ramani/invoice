{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($locales as $locale)
<url><loc>{{ \App\Support\PublicSeo::url('', $locale) }}</loc>
@foreach($locales as $alternate)
<xhtml:link rel="alternate" hreflang="{{ $alternate }}" href="{{ \App\Support\PublicSeo::url('', $alternate) }}" />
@endforeach
<xhtml:link rel="alternate" hreflang="x-default" href="{{ \App\Support\PublicSeo::url() }}" />
</url>
@endforeach
</urlset>
