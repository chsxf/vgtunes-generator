<?php

use Twig\Environment;

final class CookiePrivacyGenerator
{
    public function __construct(private array $jsonData, private string $path) {}

    public function generate(Environment $twigEnvironment): bool
    {
        $generatedHtml = $twigEnvironment->render('cookie-settings-and-privacy-policy.twig', []);
        return file_put_contents($this->path, $generatedHtml);
    }
}
