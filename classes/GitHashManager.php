<?php

use Symfony\Component\Console\Output\OutputInterface;

final class GitHashManager extends AbstractFileBasedManager
{
    private array $hashes = [];

    public function process(OutputInterface $output): bool
    {
        $this->populate($output, includeHtmlFiles: false);

        $previousWorkingDir = getcwd();
        chdir($this->basePath);
        try {
            foreach ($this->jsFiles as $jsFile) {
                $relativePath = ltrim(substr($jsFile, strlen($this->basePath)), '/');
                $this->hashes[$relativePath] = $this->fetchHash($relativePath);
            }
            foreach ($this->cssFiles as $cssFile) {
                $relativePath = ltrim(substr($cssFile, strlen($this->basePath)), '/');
                $this->hashes[$relativePath] = $this->fetchHash($relativePath);
            }
        } finally {
            chdir($previousWorkingDir);
        }

        return true;
    }

    private function fetchHash(string $relativePath): string
    {
        $cmdLine = "git log --oneline --pretty=format:\"%h | %ai\" -- {$relativePath}";
        $result = shell_exec($cmdLine);
        if ($result === false || $result === null) {
            throw new Exception("Unable to get git has for file {$relativePath}");
        }

        $pipeIndex = strpos($result, '|');
        $hash = trim(substr($result, 0, $pipeIndex));
        return $hash;
    }

    public function getHash(string $path): ?string
    {
        if (!array_key_exists($path, $this->hashes)) {
            throw new Exception("Unable to find hash for file {$path}");
        }
        return $this->hashes[$path];
    }
}
