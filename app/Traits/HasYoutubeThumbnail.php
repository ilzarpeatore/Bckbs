<?php

namespace App\Traits;

trait HasYoutubeThumbnail
{
    private function youtubeThumbnail(string $url): ?string
    {
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $matches);
        return isset($matches[1]) ? "https://img.youtube.com/vi/{$matches[1]}/sddefault.jpg" : null;
    }
}
