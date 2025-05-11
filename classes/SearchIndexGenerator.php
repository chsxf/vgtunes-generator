<?php

final class SearchIndexGenerator
{
    public function __construct(private array $jsonData, private string $path, private bool $includeInstances, private bool $prettyPrint) {}

    public function generate(): bool
    {
        $artistSlugs = array_keys($this->jsonData['artists']);

        $index = ['artists' => [], 'albums' => []];

        foreach ($this->jsonData['artists'] as $artistSlug => $artistName) {
            $index['artists'][] = [$artistName, $artistSlug];
        }

        foreach ($this->jsonData['albums'] as $album) {
            $index['albums'][$album['slug']] = $this->remapAlbum($album, $artistSlugs);
        }

        $flags = $this->prettyPrint ? JSON_PRETTY_PRINT : 0;
        return file_put_contents($this->path, json_encode($index, $flags));
    }

    private function remapAlbum(array $album, array $artistSlugs): array
    {
        $artistIndices = [];
        foreach ($album['artists'] as $artistSlug) {
            $artistIndices[] = array_search($artistSlug, $artistSlugs);
        }

        $result = ['t' => $album['title'], 'a' => $artistIndices];
        if ($this->includeInstances) {
            $result['i'] = $this->remapInstances($album['instances']);
        }
        return $result;
    }

    private function remapInstances(array $instances): array
    {
        $map = [
            'apple_music' => 'am',
            'bandcamp' => 'b',
            'deezer' => 'd',
            'spotify' => 's',
            'steam_game' => 'st',
            'steam_soundtrack' => 'ss'
        ];

        $result = [];
        foreach ($map as $src => $dest) {
            if (array_key_exists($src, $instances)) {
                $result[] = $dest;
            }
        }

        if (in_array('ss', $result)) {
            $result = array_values(array_filter($result, fn($item) => $item != 'st'));
        }

        return $result;
    }
}
