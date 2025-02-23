<?php

use Twig\Environment;

final class SitemapGenerator
{
    public function __construct(private readonly array $jsonData, private readonly string $path, private readonly ISiteUrlBuilder $siteUrlBuilder) {}

    public function generate(Environment $twig)
    {
        $urls = [
            '',
            'privacy-policy-and-settings.html'
        ];

        foreach ($this->jsonData['albums'] as $album) {
            $urls[] = "albums/{$album['slug']}/";
        }

        foreach (array_keys($this->jsonData['artists']) as $artistSlug) {
            $urls[] = "artists/{$artistSlug}/";
        }

        $allCatalogLetters = array_keys(CatalogGenerator::prepareEmptyBuckets());
        $allCatalogLetters = array_map(strtolower(...), $allCatalogLetters);
        foreach ($allCatalogLetters as $catalogLetter) {
            $urls[] = "catalog/albums/{$catalogLetter}/";
            $urls[] = "catalog/artists/{$catalogLetter}/";
        }

        $urls = array_map(fn($url) => $this->siteUrlBuilder->buildSiteUrl($url), $urls);
        $fileContent = $twig->render('sitemap.twig', ['urls' => $urls]);
        return file_put_contents($this->path, $fileContent);
    }
}
