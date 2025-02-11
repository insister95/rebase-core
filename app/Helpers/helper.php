<?php

if (!function_exists('get_language_timezones')) {
    function get_language_timezones(): array
    {
        return [
            'zh' => 'Asia/Shanghai',       // 中文 (简体)
            'zh-TW' => 'Asia/Taipei',      // 中文 (繁体)
            'en' => 'UTC',                 // 英语 (默认 UTC)
            'ja' => 'Asia/Tokyo',          // 日语
            'ko' => 'Asia/Seoul',          // 韩语
            'fr' => 'Europe/Paris',        // 法语
            'es' => 'Europe/Madrid',       // 西班牙语
            'it' => 'Europe/Rome',         // 意大利语
            'de' => 'Europe/Berlin',       // 德语
            'tr' => 'Europe/Istanbul',     // 土耳其语
            'ru' => 'Europe/Moscow',       // 俄语
            'pt' => 'Europe/Lisbon',       // 葡萄牙语
            'vi' => 'Asia/Ho_Chi_Minh',    // 越南语
            'id' => 'Asia/Jakarta',        // 印尼语
            'th' => 'Asia/Bangkok',        // 泰语
            'ms' => 'Asia/Kuala_Lumpur',   // 马来语
            'ar' => 'Asia/Riyadh',         // 阿拉伯语
            'hi' => 'Asia/Kolkata',        // 印地语
        ];
    }
}

if (!function_exists('get_languages')) {
    function get_languages(): array
    {
        return array_keys(get_language_timezones());
    }
}

if (!function_exists('get_timezone_with_language')) {
    function get_timezone_with_language(string $language): string
    {
        $languageTimeZones = get_language_timezones();
        return $languageTimeZones[$language] ?? $languageTimeZones[config('app.fallback_locale')];
    }
}

if (!function_exists('get_timezone')) {
    function get_timezone(): array
    {
        return config('app.timezone');
    }
}

if (!function_exists('get_language')) {
    function get_language(): string
    {
        return config('app.locale');
    }
}

if (!function_exists('get_envs')) {
    function get_envs(): array
    {
        return ['dev', 'stag', 'prod'];
    }
}

if (!function_exists('get_env')) {
    function get_env(): string
    {
        return config('app.env');
    }
}

if (!function_exists('is_prod')) {
    function is_prod(): bool
    {
        return get_env() == 'prod';
    }
}

