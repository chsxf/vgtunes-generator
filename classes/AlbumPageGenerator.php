<?php

use Twig\Environment;

final class AlbumPageGenerator
{
    public function __construct(private string $baseUrl, private array $album, private string $path) {}

    public function generate(Environment $twigEnvironment): bool
    {
        $folderPath = dirname($this->path);
        if (!is_dir($folderPath) && !mkdir($folderPath, recursive: true)) {
            return false;
        }

        $pageUrl = "{$this->baseUrl}albums/{$this->album['slug']}/";
        $coverUrl = "https://images.vgtunes.chsxf.dev/covers/{$this->album['slug']}/cover_500.webp";

        $this->album['instances'] = $this->expandInstanceLinks($this->album['instances']);

        $generatedHtml = $twigEnvironment->render('album.twig', [
            'album' => $this->album,
            'cover' => $coverUrl,
            'page_url' => $pageUrl
        ]);
        return file_put_contents($this->path, $generatedHtml);
    }

    private function expandInstanceLinks(array $instances): array
    {
        $result = [];
        foreach ($instances as $platform => $platformId) {
            $result[$platform] = match ($platform) {
                'deezer' => "https://www.deezer.com/album/{$platformId}",
                'spotify' => "https://open.spotify.com/album/{$platformId}",
                'apple_music' => "https://geo.music.apple.com/us/album/{$platformId}",
                default => $platformId
            };
        }
        return $result;
    }
}
