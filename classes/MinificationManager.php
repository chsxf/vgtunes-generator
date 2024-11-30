<?php

use Symfony\Component\Console\Output\OutputInterface;
use Wikimedia\Minify\CSSMin;
use Wikimedia\Minify\JavaScriptMinifier;

final class MinificationManager
{
    private array $jsFiles = [];
    private array $cssFiles = [];
    private array $htmlFiles = [];

    public function __construct(private string $basePath) {}

    public function populate(OutputInterface $output): bool
    {
        if (!$this->populatePath($this->basePath)) {
            return false;
        }

        $output->writeln(sprintf("    Found %d JavaScript files", count($this->jsFiles)));
        $output->writeln(sprintf("    Found %d CSS files", count($this->cssFiles)));
        $output->writeln(sprintf("    Found %d HTML files", count($this->htmlFiles)));
        return true;
    }

    private function populatePath(string $path): bool
    {
        if (!is_dir($path) || ($dir = opendir($path)) === false) {
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            if (preg_match('/^\./', $file)) {
                continue;
            }

            $fullPath = "{$path}/{$file}";
            if (is_dir($fullPath)) {
                if (!$this->populatePath($fullPath)) {
                    closedir($dir);
                    return false;
                }
            } else if (preg_match('/\.(js|css|html)$/', $file, $regs) && !preg_match('/\.min\.(js|css)$/', $file)) {
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

    public function process(OutputInterface $output): bool
    {
        $output->write("    Processing JavaScript files...");
        if (!$this->processJSFiles()) {
            return false;
        }
        $output->writeln(' <comment>Done</comment>');

        $output->write("    Processing CSS files...");
        if (!$this->processCSSFiles()) {
            return false;
        }
        $output->writeln(' <comment>Done</comment>');

        $output->write("    Processing HTML files...");
        if (!$this->processHTMLFiles()) {
            return false;
        }
        $output->writeln(' <comment>Done</comment>');

        return true;
    }

    private function outputMinifiedFile(string $expandedFilePath, string $minifiedData): bool
    {
        $outputPath = preg_replace('/\.(js|css|html)$/', '.min.$1', $expandedFilePath);
        return file_put_contents($outputPath, $minifiedData) !== false;
    }

    private function processJSFiles(): bool
    {
        foreach ($this->jsFiles as $fullPath) {
            if (($expanded = file_get_contents($fullPath)) === false) {
                return false;
            }
            $minified = JavaScriptMinifier::minify($expanded);
            if (!$this->outputMinifiedFile($fullPath, $minified)) {
                return false;
            }
        }
        return true;
    }

    private function processCSSFiles(): bool
    {
        foreach ($this->cssFiles as $fullPath) {
            if (($expanded = file_get_contents($fullPath)) === false) {
                return false;
            }
            $minified = CSSMin::minify($expanded);
            if (!$this->outputMinifiedFile($fullPath, $minified)) {
                return false;
            }
        }
        return true;
    }

    private function processHTMLFiles(): bool
    {
        foreach ($this->htmlFiles as $fullPath) {
            if (($unprocessedContent = file_get_contents($fullPath)) === false) {
                return false;
            }

            $processedContent = $unprocessedContent;

            if (preg_match_all('#<link.+href="([^"]+)".+/>#U', $processedContent, $allRegs, PREG_SET_ORDER)) {
                foreach ($allRegs as $regs) {
                    $cssMinifiedURL = preg_replace('/\.css$/', '.min.css', $regs[1]);
                    $replacement = str_replace($regs[1], $cssMinifiedURL, $regs[0]);
                    $processedContent = str_replace($regs[0], $replacement, $processedContent);
                }
            }

            if (preg_match_all('/<script.+src="([^"]+)".+>/U', $processedContent, $allRegs, PREG_SET_ORDER)) {
                foreach ($allRegs as $regs) {
                    $cssMinifiedURL = preg_replace('/\.js$/', '.min.js', $regs[1]);
                    $replacement = str_replace($regs[1], $cssMinifiedURL, $regs[0]);
                    $processedContent = str_replace($regs[0], $replacement, $processedContent);
                }
            }

            if (preg_match_all('#<style type="text/css">(.+)</style>#Us', $processedContent, $allRegs, PREG_SET_ORDER)) {
                foreach ($allRegs as $regs) {
                    $minifiedCSS = CSSMin::minify($regs[1]);
                    $replacement = str_replace($regs[1], $minifiedCSS, $regs[0]);
                    $processedContent = str_replace($regs[0], $replacement, $processedContent);
                }
            }

            $lines = explode("\n", $processedContent);
            $lines = array_map(fn($line) => preg_replace('/^\s+/', '', $line), $lines);
            $processedContent = implode("\n", $lines);

            if (file_put_contents($fullPath, $processedContent) === false) {
                return false;
            }
        }

        return true;
    }
}
