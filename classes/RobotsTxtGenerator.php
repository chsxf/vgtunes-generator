<?php

final class RobotsTxtGenerator
{
    public function __construct(private readonly string $path, private readonly ISiteUrlBuilder $siteUrlBuilder) {}

    public function generate(): bool
    {
        $rules = [
            '*' => [
                '*.md'
            ]
        ];

        $sitemap = $this->siteUrlBuilder->buildSiteUrl("sitemap.xml");

        $fileContent = '';
        foreach ($rules as $rule => $disallows) {
            $fileContent .= "User-agent: {$rule}\n";
            foreach ($disallows as $rule_disallow) {
                $fileContent .= "Disallow: {$rule_disallow}\n";
            }
        }

        $fileContent .= "\nSitemap: {$sitemap}\n";
        return file_put_contents($this->path, $fileContent);
    }
}
