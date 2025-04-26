<?php

use Twig\Environment;

final class ArtistPageGenerator
{
    private array $artist;

    public function __construct(private readonly ISiteUrlBuilder $siteUrlBuilder, private string $path, string $artistSlug, string $artistName, private array $albums)
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

        $pageUrl = $this->siteUrlBuilder->buildSiteUrl("artist/{$this->artist['slug']}/");

        $generatedHtml = $twigEnvironment->render('artist.twig', [
            'artist' => $this->artist,
            'page_url' => $pageUrl
        ]);
        return file_put_contents($this->path, $generatedHtml);
    }
}
