<?php

use Twig\Environment;

final class AlbumPageGenerator
{
    public function __construct(private readonly ISiteUrlBuilder $siteUrlBuilder, private array $album, private string $path, private array $artistMap) {}

    public function generate(Environment $twigEnvironment): bool
    {
        $folderPath = dirname($this->path);
        if (!is_dir($folderPath) && !mkdir($folderPath, recursive: true)) {
            return false;
        }

        $pageUrl = $this->siteUrlBuilder->buildSiteUrl("albums/{$this->album['slug']}/");
        $coverUrl = "https://images.vgtunes.chsxf.dev/covers/{$this->album['slug']}/cover_500.webp";

        $this->album['artist'] = $this->artistMap[$this->album['artists'][0]];
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
                'deezer' => ["https://www.deezer.com/album/{$platformId}", "deezer://album/{$platformId}"],
                'spotify' => ["https://open.spotify.com/album/{$platformId}", "spotify://album/{$platformId}"],
                'apple_music' => ["https://geo.music.apple.com/album/{$platformId}", "music://music.apple.com/album/{$platformId}"],
                'bandcamp' => [explode('|', $platformId)[1]],
                'steam_game' => ["https://store.steampowered.com/app/{$platformId}", "steam://store/{$platformId}"],
                'steam_soundtrack' => ["https://store.steampowered.com/app/{$platformId}", "steam://store/{$platformId}"],
                default => [$platformId, '']
            };
        }
        return $result;
    }
}
