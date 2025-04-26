<?php

use Twig\Environment;

final class HomeGenerator
{
    public function __construct(private array $jsonData, private string $path) {}

    public function generate(Environment $twigEnvironment): bool
    {
        $albums = $this->jsonData['albums'];
        usort($albums, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        $latestAlbums = array_map($this->remapArtists(...), array_slice($albums, 0, 54));

        $featuredAlbum = null;
        if (!empty($this->jsonData['featured_albums'])) {
            $featuredSlug = $this->jsonData['featured_albums'][0]['slug'];

            $matchingAlbum = array_filter($this->jsonData['albums'], fn($album) => $album['slug'] == $featuredSlug);
            $featuredAlbum = $this->remapArtists(reset($matchingAlbum));

            if (array_key_exists('steam_soundtrack', $featuredAlbum['instances'])) {
                unset($featuredAlbum['instances']['steam_game']);
            }
        }

        $generatedHtml = $twigEnvironment->render('home.twig', [
            'latest_albums' => $latestAlbums,
            'album_count' => count($this->jsonData['albums']),
            'artist_count' => count($this->jsonData['artists']),
            'featured_album' => $featuredAlbum
        ]);
        return file_put_contents($this->path, $generatedHtml);
    }

    private function remapArtists(array $album): array
    {
        $artists = [];
        foreach ($album['artists'] as $artistSlug) {
            $artists[$artistSlug] = $this->jsonData['artists'][$artistSlug];
        }
        $album['artists'] = $artists;
        return $album;
    }
}
