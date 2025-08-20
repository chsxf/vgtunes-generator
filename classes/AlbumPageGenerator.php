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

        $this->album['instances'] = $this->expandInstanceLinks($this->album['instances']);
        $this->remapArtists();

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
                'tidal' => ["https://tidal.com/album/{$platformId}", "tidal://album/{$platformId}"],
                default => [$platformId, '']
            };
        }
        uksort($result, function ($keyA, $keyB) {
            $keyAisSteam = preg_match('/^steam_(.+)$/', $keyA, $regsA) > 0;
            $keyBisSteam = preg_match('/^steam_(.+)$/', $keyB, $regsB) > 0;

            if ($keyAisSteam && $keyBisSteam) {
                return $regsA[1] <=> $regsB[1];
            }
            if ($keyAisSteam) {
                return 1;
            }
            if ($keyBisSteam) {
                return -1;
            }
            return $keyA <=> $keyB;
        });
        return $result;
    }

    private function remapArtists(): void
    {
        $artists = [];
        foreach ($this->album['artists'] as $artistSlug) {
            $artists[$artistSlug] = $this->artistMap[$artistSlug];
        }
        $this->album['artists'] = $artists;
    }
}
