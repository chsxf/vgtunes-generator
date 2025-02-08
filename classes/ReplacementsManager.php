<?php

use Symfony\Component\Console\Output\OutputInterface;

final class ReplacementsManager extends AbstractFileBasedManager
{
    public function __construct(string $basePath, private array $replacements)
    {
        parent::__construct($basePath);
    }

    public function process(OutputInterface $output): bool
    {
        $output->write("    <comment>Processing JavaScript files...</comment>");
        if (!$this->replaceInFiles($this->jsFiles)) {
            return false;
        }
        $output->writeln(' <info>Done</info>');

        $output->write("    <comment>Processing CSS files...</comment>");
        if (!$this->replaceInFiles($this->cssFiles)) {
            return false;
        }
        $output->writeln(' <info>Done</info>');

        $output->write("    <comment>Processing HTML files...</comment>");
        if (!$this->replaceInFiles($this->htmlFiles)) {
            return false;
        }
        $output->writeln(' <info>Done</info>');

        return true;
    }

    private function replaceInFiles(array $files): bool
    {
        foreach ($files as $file) {
            if (!$this->replaceInFile($file)) {
                return false;
            }
        }
        return true;
    }

    private function replaceInFile(string $filePath): bool
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return false;
        }

        $resultCount = preg_match_all('#(// BEGIN REPLACE (\w+) (.+))^(.+)^(\s*)(// END REPLACE \2)#Ums', $contents, $matches, PREG_SET_ORDER);
        if ($resultCount === false) {
            return false;
        }

        foreach ($matches as $match) {
            if (!array_key_exists($match[2], $this->replacements)) {
                return false;
            }

            $leadingSpaces = '';
            if (preg_match('/^\s+/', $match[4], $regs)) {
                $leadingSpaces = $regs[0];
            }

            $newLine = str_replace("{{$match[2]}}", $this->replacements[$match[2]], $match[3]);
            $replacement = "{$match[1]}{$leadingSpaces}{$newLine}{$match[5]}{$match[6]}";
            $contents = str_replace($match[0], $replacement, $contents);
        }

        return file_put_contents($filePath, $contents);
    }
}
