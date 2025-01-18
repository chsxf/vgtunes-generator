<?php

use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

final class CatalogGenerator
{
    private array $albumBuckets;
    private array $artistBuckets;

    public function __construct(private string $baseUrl, private array $albums)
    {
        $this->albumBuckets = $this->prepareAlbumBuckets();
        $this->artistBuckets = $this->prepareArtistBuckets();
    }

    private function prepareAlbumBuckets(): array
    {
        $buckets = self::prepareEmptyBuckets();

        foreach ($this->albums as $album) {
            $title = $album['title'];

            $sortableTitle = preg_replace('/^(the|a) /i', '', $title);
            $sortKey = strtoupper($sortableTitle[0]);
            if (!array_key_exists($sortKey, $buckets)) {
                $sortKey = '#';
            }

            $buckets[$sortKey][] = array_merge($album, ['sortable_title' => $sortableTitle]);
        }

        $buckets = array_filter($buckets);

        foreach ($buckets as $key => &$albums) {
            if ($key !== '#') {
                usort($albums, fn($itemA, $itemB) => strcasecmp($itemA['sortable_title'], $itemB['sortable_title']));
            }
        }

        return $buckets;
    }

    private function prepareArtistBuckets(): array
    {
        return [];
    }

    private static function prepareEmptyBuckets(): array
    {
        $keys = array_merge(range('A', 'Z'), ['#']);
        $values = array_pad([], count($keys), []);
        return array_combine($keys, $values);
    }

    public function generate(OutputInterface $output, Environment $twig): bool
    {
        return $this->generateAlbumCatalog($output, $twig) && $this->generateArtistCatalog($output, $twig);
    }

    private function generateAlbumCatalog(OutputInterface $output, Environment $twig): bool
    {
        return true;
    }

    private function generateArtistCatalog(OutputInterface $output, Environment $twig): bool
    {
        return true;
    }
}
