<?php

use ScssPhp\ScssPhp\Compiler;
use Symfony\Component\Console\Output\OutputInterface;

final class SCSSCompiler
{
    private Compiler $compiler;
    private array $scssFiles;

    public function __construct(string $inputPath, private string $outputPath)
    {
        $this->compiler = new Compiler();

        $inputFolderContent = scandir($inputPath);
        $inputFolderContent = array_filter($inputFolderContent, fn($item) => !preg_match('/^\./', $item) && preg_match('/\.scss$/', $item));
        $this->scssFiles = array_map(fn($item) => "{$inputPath}/{$item}", $inputFolderContent);
    }

    public function process(OutputInterface $output): bool
    {
        foreach ($this->scssFiles as $scssFile) {
            $fileName = basename($scssFile);
            $fileNameWoExt = basename($scssFile, '.scss');

            $output->write("  <comment>Compiling {$fileName} ...</comment>");
            $cssContent = $this->compiler->compileFile($scssFile)->getCss();
            $outputPath = "{$this->outputPath}/{$fileNameWoExt}.css";
            if (!file_put_contents($outputPath, $cssContent)) {
                return false;
            }
            $output->writeln(' <comment>Done</comment>');
        }

        return true;
    }
}
