<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand("generate", "Generate website content")]
final class GenerateCommand extends Command
{
    private const OUTPUT_PATH = 'output_path';
    private const ENVIRONMENT = 'environment';

    private ?array $currentEnvironment = null;
    private ?string $outputPath = null;

    function __construct(private array $environments)
    {
        parent::__construct();
    }

    function configure()
    {
        $this
            ->addArgument(self::OUTPUT_PATH, InputArgument::REQUIRED, "Output path for the generate files")
            ->addOption(self::ENVIRONMENT, null, InputOption::VALUE_REQUIRED, 'Environment', 'dev');
    }

    function execute(InputInterface $input, OutputInterface $output): int
    {
        $environmentKey = $input->getOption(self::ENVIRONMENT);
        $this->currentEnvironment = $this->environments[$environmentKey];

        $this->outputPath = $input->getArgument(self::OUTPUT_PATH);

        try {
            $output->writeln($this->getApplication()->getName());
            $output->writeln('');

            $output->writeln('<info>Settings</info>');
            $output->writeln("  <comment>Output folder: {$this->outputPath}</comment>");
            $output->writeln("  <comment>Environment: {$environmentKey}</comment>");
            $output->writeln('');

            $output->write('<info>Fetching database JSON file...</info>');
            $jsonData = $this->fetchJSON();
            $output->writeln(' Done!');
            $referenceCount = count($jsonData);
            $output->writeln("  {$referenceCount} references found");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('');
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function fetchJSON(): array
    {
        $url = $this->buildDashboardUrl('/JsonGenerator/generate');
        $content = file_get_contents($url);
        if ($content === false) {
            throw new Exception("Failed to fetch JSON file");
        }

        $parsedContent = json_decode($content, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        if (!is_array($parsedContent) || !array_is_list($parsedContent)) {
            throw new Exception("Invalid JSON format");
        }
        return $parsedContent;
    }

    private function buildDashboardUrl(string $path): string
    {
        return "{$this->currentEnvironment['dashboard_url']}{$path}";
    }
}
