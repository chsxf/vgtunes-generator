<?php

use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

final class CatalogGenerator
{
    private const string OTHERS_KEY = 'others';

    private array $albumBuckets;
    private array $artistBuckets;

    private static ?array $allLetterKeys = null;

    public function __construct(private array $jsonData, private IOutputPathBuilder $outputPathBuilder)
    {
        $this->albumBuckets = $this->prepareAlbumBuckets();
        $this->artistBuckets = $this->prepareArtistBuckets();
    }

    private function prepareAlbumBuckets(): array
    {
        $buckets = self::prepareEmptyBuckets();

        foreach ($this->jsonData['albums'] as $album) {
            $title = $album['title'];

            $sortableTitle = preg_replace('/^(the|a) /i', '', $title);
            $sortKey = strtoupper($sortableTitle[0]);
            if (!array_key_exists($sortKey, $buckets)) {
                $sortKey = self::OTHERS_KEY;
            }

            $buckets[$sortKey][] = array_merge($this->remapArtists($album), ['sortable_title' => $sortableTitle]);
        }

        foreach ($buckets as $key => &$albums) {
            if ($key !== self::OTHERS_KEY) {
                usort($albums, fn($itemA, $itemB) => strcasecmp($itemA['sortable_title'], $itemB['sortable_title']));
            }
        }

        return $buckets;
    }

    private function prepareArtistBuckets(): array
    {
        $buckets = self::prepareEmptyBuckets();

        foreach ($this->jsonData['artists'] as $slug => $name) {
            $sortKey = strtoupper($name[0]);
            if (!array_key_exists($sortKey, $buckets)) {
                $sortKey = self::OTHERS_KEY;
            }

            $artistAlbums = array_filter($this->jsonData['albums'], fn($album) => in_array($slug, $album['artists']));

            $buckets[$sortKey][] = [
                'slug' => $slug,
                'name' => $name,
                'album_count' => count($artistAlbums)
            ];
        }

        return $buckets;
    }

    public static function prepareEmptyBuckets(): array
    {
        if (self::$allLetterKeys === null) {
            self::$allLetterKeys = array_merge(range('A', 'Z'), [self::OTHERS_KEY]);
        }

        $values = array_pad([], count(self::$allLetterKeys), []);
        return array_combine(self::$allLetterKeys, $values);
    }

    public function generate(OutputInterface $output, Environment $twig): bool
    {
        return $this->generateAlbumCatalog($output, $twig) && $this->generateArtistCatalog($output, $twig);
    }

    private function generateAlbumCatalog(OutputInterface $output, Environment $twig): bool
    {
        $filledBucketKeys = array_keys(array_filter($this->albumBuckets));

        $output->writeln('  <comment>Generating album catalogs...</comment>');

        foreach ($this->albumBuckets as $key => $bucket) {
            if ($key == self::OTHERS_KEY) {
                $message = 'Other';
            } else {
                $message = $key;
            }
            $output->write("    <comment>{$message}...</comment>");

            if (empty($bucket)) {
                $output->writeln(' <info>Skipped</info>');
                continue;
            }

            $fileContents = $twig->render('catalog/album.twig', [
                'catalog_key' => $key,
                'bucket' => $bucket,
                'all_letter_keys' => self::$allLetterKeys,
                'filled_bucket_keys' => $filledBucketKeys
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

            $output->writeln(' <info>Done</info>');
        }

        return true;
    }

    private function generateArtistCatalog(OutputInterface $output, Environment $twig): bool
    {
        $filledBucketKeys = array_keys(array_filter($this->artistBuckets));

        $output->writeln('  <comment>Generating artist catalogs...</comment>');

        foreach ($this->artistBuckets as $key => $bucket) {
            if ($key == self::OTHERS_KEY) {
                $message = 'Other';
            } else {
                $message = $key;
            }
            $output->write("    <comment>{$message}...</comment>");

            if (empty($bucket)) {
                $output->writeln(' <info>Skipped</info>');
                continue;
            }

            $fileContents = $twig->render('catalog/artist.twig', [
                'catalog_key' => $key,
                'bucket' => $bucket,
                'all_letter_keys' => self::$allLetterKeys,
                'filled_bucket_keys' => $filledBucketKeys
            ]);

            $keyForPath = strtolower($key);
            $path = $this->outputPathBuilder->buildOutputPath("/catalog/artists/{$keyForPath}/index.html");
            $folderPath = dirname($path);
            if (!is_dir($folderPath) && !mkdir($folderPath, recursive: true)) {
                return false;
            }

            if (!file_put_contents($path, $fileContents)) {
                return false;
            }

            $output->writeln(' <info>Done</info>');
        }

        return true;
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
