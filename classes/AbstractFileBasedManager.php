<?php

use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractFileBasedManager
{
    protected array $jsFiles = [];
    protected array $cssFiles = [];
    protected array $htmlFiles = [];

    public function __construct(protected readonly string $basePath) {}

    public final function populate(OutputInterface $output, bool $includeHtmlFiles = true, int $indent = 4): bool
    {
        if (!$this->populatePath($this->basePath, $includeHtmlFiles)) {
            return false;
        }

        $indentPrefix = str_pad('', $indent);

        $output->writeln(sprintf("{$indentPrefix}Found %d JavaScript files", count($this->jsFiles)));
        $output->writeln(sprintf("{$indentPrefix}Found %d CSS files", count($this->cssFiles)));
        if ($includeHtmlFiles) {
            $output->writeln(sprintf("{$indentPrefix}Found %d HTML files", count($this->htmlFiles)));
        }
        return true;
    }

    private function populatePath(string $path, bool $includeHtmlFiles): bool
    {
        if (!is_dir($path) || ($dir = opendir($path)) === false) {
            return false;
        }

        $regex = $includeHtmlFiles ? '/\.(js|css|html)$/' : '/\.(js|css)$/';

        while (($file = readdir($dir)) !== false) {
            if (preg_match('/^\./', $file)) {
                continue;
            }

            $fullPath = "{$path}/{$file}";
            if (is_dir($fullPath)) {
                if (!$this->populatePath($fullPath, $includeHtmlFiles)) {
                    closedir($dir);
                    return false;
                }
            } else if (preg_match($regex, $file, $regs) && !preg_match('/\.min\.(js|css)$/', $file)) {
                switch ($regs[1]) {
                    case 'js':
                        $this->jsFiles[] = $fullPath;
                        break;
                    case 'css':
                        $this->cssFiles[] = $fullPath;
                        break;
                    case 'html':
                        $this->htmlFiles[] = $fullPath;
                        break;
                }
            }
        }

        closedir($dir);
        return true;
    }

    abstract public function process(OutputInterface $output): bool;
}
