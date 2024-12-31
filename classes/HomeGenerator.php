<?php

use Twig\Environment;

final class HomeGenerator
{
    public function __construct(private array $jsonData, private string $path) {}

    public function generate(Environment $twigEnvironment): bool
    {
        usort($this->jsonData, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        $latestAlbums = array_slice($this->jsonData, 0, 50);
        shuffle($latestAlbums);

        $baseAlbumUrl = "/albums/";
        $baseCoverUrl = "https://images.vgtunes.chsxf.dev/covers/";

        $generatedHtml = $twigEnvironment->render('home.twig', [
            'base_album_url' => $baseAlbumUrl,
            'base_cover_url' => $baseCoverUrl,
            'latest_albums' => $latestAlbums
        ]);
        return file_put_contents($this->path, $generatedHtml);
    }
}
