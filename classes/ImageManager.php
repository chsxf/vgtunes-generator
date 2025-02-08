<?php

use Symfony\Component\Console\Output\OutputInterface;

final class ImageManager
{
    private array $images;

    public function __construct(private string $inputPath, private string $outputPath)
    {
        $images = $this->recursiveScandir($inputPath);
        $this->images = array_map(function ($item) use ($inputPath) {
            $re = '/^' . preg_quote($inputPath, '/') . '/';
            return preg_replace($re, '', $item);
        }, $images);
    }

    private function recursiveScandir(string $path): array
    {
        $inputFolderContent = scandir($path);
        $inputFolderContent = array_filter($inputFolderContent, fn($item) => !preg_match('/^\./', $item));
        $folderAndSubFoldersContent = [];
        foreach ($inputFolderContent as $item) {
            $fullPath = "{$path}/{$item}";
            if (is_dir($fullPath)) {
                $folderAndSubFoldersContent = array_merge($folderAndSubFoldersContent, $this->recursiveScandir($fullPath));
            } else {
                $folderAndSubFoldersContent[] = $fullPath;
            }
        }
        return $folderAndSubFoldersContent;
    }

    public function process(OutputInterface $output): bool
    {
        foreach ($this->images as $image) {
            $output->write("  <comment>Exporting {$image}...</comment>");

            $fullInputPath = "{$this->inputPath}{$image}";
            $fullOutputPath = "{$this->outputPath}{$image}";

            $fullOutputFolderPath = dirname($fullOutputPath);
            if ((!file_exists($fullOutputFolderPath) && !mkdir($fullOutputFolderPath, recursive: true)) || !copy($fullInputPath, $fullOutputPath)) {
                return false;
            }

            $output->writeln(' <info>Done</info>');
        }

        return true;
    }
}
