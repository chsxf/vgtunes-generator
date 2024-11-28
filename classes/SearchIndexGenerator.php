<?php

final class SearchIndexGenerator
{
    public function __construct(private array $jsonData, private string $path, private bool $includeInstances, private bool $prettyPrint) {}

    private array $artistList;

    public function generate(): bool
    {
        $this->artistList = [];

        foreach ($this->jsonData as $album) {
            if (!in_array($album['artist'], $this->artistList)) {
                $this->artistList[] = $album['artist'];
            }
        }
        sort($this->artistList);

        $index = ['artists' => $this->artistList];

        $index['albums'] = [];
        foreach ($this->jsonData as $album) {
            $index['albums'][$album['slug']] = $this->remapAlbum($album);
        }

        $flags = $this->prettyPrint ? JSON_PRETTY_PRINT : 0;
        return file_put_contents($this->path, json_encode($index, $flags));
    }

    private function remapAlbum(array $album): array
    {
        $artistIndex = array_search($album['artist'], $this->artistList) + 1;
        $result = ['t' => $album['name'], 'a' => $artistIndex];
        if ($this->includeInstances) {
            $result['i'] = $this->remapInstances($album['instances']);
        }
        return $result;
    }

    private function remapInstances(array $instances): string
    {
        $map = [
            'apple_music' => 'am',
            'spotify' => 's',
            'deezer' => 'd'
        ];

        $result = [];
        foreach ($map as $src => $dest) {
            if (array_key_exists($src, $instances)) {
                $result[] = sprintf("%s:%s", $dest, $instances[$src]);
            }
        }
        return implode('|', $result);
    }
}
