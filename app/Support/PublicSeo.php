<?php

namespace App\Support;

class PublicSeo
{
    public static function url(string $path = '', ?string $locale = null): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/').($locale && $locale !== 'en' ? '?lang='.$locale : '');
    }

    public static function indexable(): bool
    {
        $host = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

        return config('seo.indexable') && parse_url(config('app.url'), PHP_URL_SCHEME) === 'https'
            && $host !== '' && $host !== 'localhost' && !str_ends_with($host, '.test')
            && !str_ends_with($host, '.local') && !filter_var($host, FILTER_VALIDATE_IP);
    }
}
