<?php

use Twig\Environment;

final class PrivacyPageGenerator
{
    public function __construct(private array $jsonData, private string $path) {}

    public function generate(Environment $twigEnvironment): bool
    {
        $generatedHtml = $twigEnvironment->render('privacy-policy-and-settings.twig', []);
        return file_put_contents($this->path, $generatedHtml);
    }
}
