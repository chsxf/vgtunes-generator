<?php

use Twig\Environment;

final class HomeGenerator
{
    public function __construct(private array $jsonData, private string $path) {}

    public function generate(Environment $twigEnvironment): bool
    {
        $albums = $this->jsonData['albums'];
        usort($albums, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        $latestAlbums = array_slice($albums, 0, 54);

        $latestAlbums = array_map(function ($album) {
            $album['artist'] = $this->jsonData['artists'][$album['artists'][0]];
            return $album;
        }, $latestAlbums);

        $baseAlbumUrl = "/albums/";
        $baseArtistUrl = "/artists/";
        $baseCoverUrl = "https://images.vgtunes.chsxf.dev/covers/";

        $featuredAlbum = null;
        if (!empty($this->jsonData['featured_albums'])) {
            $featuredSlug = $this->jsonData['featured_albums'][0]['slug'];

            $matchingAlbum = array_filter($this->jsonData['albums'], fn($album) => $album['slug'] == $featuredSlug);
            $featuredAlbum = reset($matchingAlbum);
            $featuredAlbum['artist'] = $this->jsonData['artists'][$featuredAlbum['artists'][0]];

            if (array_key_exists('steam_soundtrack', $featuredAlbum['instances'])) {
                unset($featuredAlbum['instances']['steam_game']);
            }
        }

        $generatedHtml = $twigEnvironment->render('home.twig', [
            'base_album_url' => $baseAlbumUrl,
            'base_artist_url' => $baseArtistUrl,
            'base_cover_url' => $baseCoverUrl,
            'latest_albums' => $latestAlbums,
            'album_count' => count($this->jsonData['albums']),
            'artist_count' => count($this->jsonData['artists']),
            'featured_album' => $featuredAlbum
        ]);
        return file_put_contents($this->path, $generatedHtml);
    }
}
