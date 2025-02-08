<?php

use Symfony\Component\Console\Output\OutputInterface;

final class JavascriptManager
{
    private array $jsFiles;

    public function __construct(string $inputPath, private string $outputPath)
    {
        $inputFolderContent = scandir($inputPath);
        $inputFolderContent = array_filter($inputFolderContent, fn($item) => !preg_match('/^\./', $item) && preg_match('/\.js$/', $item));
        $this->jsFiles = array_map(fn($item) => "{$inputPath}/{$item}", $inputFolderContent);
    }

    public function process(OutputInterface $output): bool
    {
        foreach ($this->jsFiles as $jsFile) {
            $fileName = basename($jsFile);

            $output->write("  <comment>Exporting {$fileName} ...</comment>");
            $jsContent = file_get_contents($jsFile);
            $outputPath = "{$this->outputPath}/{$fileName}";
            if (!file_put_contents($outputPath, $jsContent)) {
                return false;
            }
            $output->writeln(' <info>Done</info>');
        }

        return true;
    }
}
