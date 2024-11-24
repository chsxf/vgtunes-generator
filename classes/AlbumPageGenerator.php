<?php

final class AlbumPageGenerator
{
    public function __construct(private array $album, private string $path) {}

    public function generate(): bool
    {
        $folderPath = dirname($this->path);
        if (!is_dir($folderPath) && !mkdir($folderPath, recursive: true)) {
            return false;
        }

        return file_put_contents($this->path, json_encode($this->album, JSON_PRETTY_PRINT));;
    }
}
