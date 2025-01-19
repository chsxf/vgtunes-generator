<?php

use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

final class CatalogGenerator
{
    private const string OTHERS_KEY = 'others';

    private array $albumBuckets;
    private array $artistBuckets;

    private static ?array $allLetterKeys = null;

    public function __construct(private string $baseUrl, private array $albums, private IOutputPathBuilder $outputPathBuilder)
    {
        if (self::$allLetterKeys === null) {
            self::$allLetterKeys = array_merge(range('A', 'Z'), [self::OTHERS_KEY]);
        }

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
                $sortKey = self::OTHERS_KEY;
            }

            $buckets[$sortKey][] = array_merge($album, ['sortable_title' => $sortableTitle]);
        }

        $buckets = array_filter($buckets);

        foreach ($buckets as $key => &$albums) {
            if ($key !== self::OTHERS_KEY) {
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
        $values = array_pad([], count(self::$allLetterKeys), []);
        return array_combine(self::$allLetterKeys, $values);
    }

    public function generate(OutputInterface $output, Environment $twig): bool
    {
        return $this->generateAlbumCatalog($output, $twig) && $this->generateArtistCatalog($output, $twig);
    }

    private function generateAlbumCatalog(OutputInterface $output, Environment $twig): bool
    {
        $bucketKeys = array_keys($this->albumBuckets);

        foreach ($this->albumBuckets as $key => $bucket) {
            if ($key == self::OTHERS_KEY) {
                $message = 'Generating other albums catalog...';
            } else {
                $message = "Generating album catalog for letter {$key}...";
            }
            $output->write("  <comment>{$message}</comment>");

            $fileContents = $twig->render('catalog/album.twig', [
                'bucket' => $bucket,
                'all_letter_keys' => self::$allLetterKeys,
                'filled_bucket_keys' => $bucketKeys
            ]);

            $keyForPath = strtolower($key);
            $path = $this->outputPathBuilder->buildOutputPath("/catalog/albums/{$keyForPath}/index.html");
            $folderPath = dirname($path);
            if (!is_dir($folderPath) && !mkdir($folderPath, recursive: true)) {
                return false;
            }

            if (!file_put_contents($path, $fileContents)) {
                return false;
            }

            $output->writeln(' <comment>Done</comment>');
        }

        return true;
    }

    private function generateArtistCatalog(OutputInterface $output, Environment $twig): bool
    {
        return true;
    }
}
