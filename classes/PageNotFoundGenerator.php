<?php

use Twig\Environment;

final class PageNotFoundGenerator
{
    public function __construct(private array $jsonData, private string $path) {}

    public function generate(Environment $twigEnvironment): bool
    {
        $generatedHtml = $twigEnvironment->render('404.twig', []);
        return file_put_contents($this->path, $generatedHtml);
    }
}
