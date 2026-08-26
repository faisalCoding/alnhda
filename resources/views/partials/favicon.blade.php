{{-- أيقونة الموقع كما ضُبطت في اللوحة، أو المرفقة مع الموقع إن لم تُرفع واحدة. --}}
@php
    $appSettings = \App\Models\AppSettings::current();
    $faviconUrl = $appSettings->faviconUrl();
@endphp

<link rel="icon" type="image/png" sizes="{{ \App\Services\ImageService::FAVICON_SIZE }}x{{ \App\Services\ImageService::FAVICON_SIZE }}" href="{{ $faviconUrl }}">
<link rel="shortcut icon" href="{{ $faviconUrl }}">
<link rel="apple-touch-icon" href="{{ $appSettings->appleTouchIconUrl() }}">
