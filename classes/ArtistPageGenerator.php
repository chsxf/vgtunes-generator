<?php

use Twig\Environment;

final class ArtistPageGenerator
{
    private array $artist;

    public function __construct(private string $baseUrl, private string $path, string $artistSlug, string $artistName, private array $albums)
    {
        $this->artist = [
            'slug' => $artistSlug,
            'name' => $artistName,
            'albums' => $albums
        ];
    }

    public function generate(Environment $twigEnvironment): bool
    {
        $folderPath = dirname($this->path);
        if (!is_dir($folderPath) && !mkdir($folderPath, recursive: true)) {
            return false;
        }

        $pageUrl = "{$this->baseUrl}artist/{$this->artist['slug']}/";
        $baseCoverUrl = "https://images.vgtunes.chsxf.dev/covers/";

        $generatedHtml = $twigEnvironment->render('artist.twig', [
            'artist' => $this->artist,
            'page_url' => $pageUrl,
            'base_cover_url' => $baseCoverUrl
        ]);
        return file_put_contents($this->path, $generatedHtml);
    }
}
