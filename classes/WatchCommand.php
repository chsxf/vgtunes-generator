<?php

use chsxf\FolderWatcher\IWatchResponder;
use chsxf\FolderWatcher\Runner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand("watch", "Watches for file changes and reload generation")]
final class WatchCommand extends AbstractCommand implements IWatchResponder
{
    private InputInterface $input;
    private OutputInterface $output;

    private Runner $folderWatcherRunner;
    private string $generationCommandLine;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $this->generationCommandLine = $this->prepareGenerationCommandLine();
        $this->proceedWithGeneration();

        $this->folderWatcherRunner = new Runner(['.']);
        $this->folderWatcherRunner->addResponder($this);
        $this->folderWatcherRunner->watch();

        return Command::FAILURE;
    }

    private function prepareGenerationCommandLine(): string
    {
        $cmdArguments = [];
        $options = [
            self::DASHBOARD_EXPORT,
            self::ENVIRONMENT,
            self::SKIP_ALBUMS,
            self::SKIP_ARTISTS,
            self::SKIP_CATALOG,
            self::STATIC_FILES_ONLY,
            self::SKIP_MINIFY,
            self::PRETTY_SEARCH_INDEX
        ];
        foreach ($options as $option) {
            $this->prepareGenerationOption($cmdArguments, $option);
        }
        $cmdArguments[] = $this->input->getArgument(self::OUTPUT_PATH);

        $cmdLine = "{$_SERVER['PHP_SELF']} generate " . implode(' ', $cmdArguments);

        $this->output->writeln("<info>Execution command-line:</info>\n  <comment>{$cmdLine}</comment>", OutputInterface::VERBOSITY_VERBOSE);
        $this->output->writeln('');

        return $cmdLine;
    }

    private function proceedWithGeneration(): void
    {
        $this->output->writeln('<info>Generating...</info>');
        $this->output->writeln('');

        $proc = proc_open($this->generationCommandLine, [STDIN, STDOUT, STDOUT], $pipes);
        proc_close($proc);
    }

    private function prepareGenerationOption(array &$commandArguments, string $option)
    {
        $optionValue = $this->input->getOption($option);
        if ($optionValue !== null) {
            if (is_bool($optionValue)) {
                if ($optionValue) {
                    $commandArguments[] = "--{$option}";
                }
            } else {
                $commandArguments[] = "--{$option}={$optionValue}";
            }
        }
    }

    public function notifyStartWatching(): void
    {
        $this->output->writeln('<info>Waitting for changes...</info>');
        $this->output->writeln('');
    }

    public function processChanges(array $watchChanges): void
    {
        $this->output->writeln('<info>Changes detected...</info>');
        $this->output->writeln('');

        $this->proceedWithGeneration();
    }
}
