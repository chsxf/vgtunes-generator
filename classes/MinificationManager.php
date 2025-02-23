<?php

use Symfony\Component\Console\Output\OutputInterface;
use Wikimedia\Minify\CSSMin;
use Wikimedia\Minify\JavaScriptMinifier;

final class MinificationManager extends AbstractFileBasedManager
{
    public function process(OutputInterface $output): bool
    {
        $output->write("    <comment>Processing JavaScript files...</comment>");
        if (!$this->processJSFiles()) {
            return false;
        }
        $output->writeln(' <info>Done</info>');

        $output->write("    <comment>Processing CSS files...</comment>");
        if (!$this->processCSSFiles()) {
            return false;
        }
        $output->writeln(' <info>Done</info>');

        $output->write("    <comment>Processing HTML files...</comment>");
        if (!$this->processHTMLFiles()) {
            return false;
        }
        $output->writeln(' <info>Done</info>');

        if (!empty($this->additionalFiles)) {
            $output->write("    <comment>Processing additional files...</comment>");
            if (!$this->processAdditionalFiles()) {
                return false;
            }
            $output->writeln(' <info>Done</info>');
        }

        return true;
    }

    private function outputMinifiedFile(string $outputPath, string $minifiedData): bool
    {
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

    private static function removeLeadingSpaces(string $unprocessedContent): string
    {
        $lines = explode("\n", $unprocessedContent);
        $lines = array_map(ltrim(...), $lines);
        return implode("\n", $lines);
    }

    private function processHTMLFiles(): bool
    {
        foreach ($this->htmlFiles as $fullPath) {
            if (($unprocessedContent = file_get_contents($fullPath)) === false) {
                return false;
            }

            $processedContent = $unprocessedContent;

            if (preg_match_all('#<style type="text/css">(.+)</style>#Us', $processedContent, $allRegs, PREG_SET_ORDER)) {
                foreach ($allRegs as $regs) {
                    $minifiedCSS = CSSMin::minify($regs[1]);
                    $replacement = str_replace($regs[1], $minifiedCSS, $regs[0]);
                    $processedContent = str_replace($regs[0], $replacement, $processedContent);
                }
            }

            $processedContent = self::removeLeadingSpaces($processedContent);

            if (file_put_contents($fullPath, $processedContent) === false) {
                return false;
            }
        }

        return true;
    }

    private function processAdditionalFiles(): bool
    {
        foreach ($this->additionalFiles as $fullPath) {
            if (($unprocessedContent = file_get_contents($fullPath)) === false) {
                return false;
            }

            $processedContent = $unprocessedContent;
            $processedContent = self::removeLeadingSpaces($processedContent);

            if (file_put_contents($fullPath, $processedContent) === false) {
                return false;
            }
        }

        return true;
    }
}
