<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

#[AsCommand("generate", "Generate website content")]
final class GenerateCommand extends Command
{
    private const OUTPUT_PATH = 'output_path';
    private const ENVIRONMENT = 'environment';

    private ?array $currentEnvironment = null;
    private ?string $outputPath = null;

    function configure()
    {
        $this
            ->addArgument(self::OUTPUT_PATH, InputArgument::REQUIRED, "Output path for the generate files")
            ->addOption(self::ENVIRONMENT, null, InputOption::VALUE_REQUIRED, 'Environment', 'dev');
    }

    function execute(InputInterface $input, OutputInterface $output): int
    {
        $environmentKey = $input->getOption(self::ENVIRONMENT);
        $envFilePath = "environments/env.{$environmentKey}.php";
        $this->currentEnvironment = require_once($envFilePath);

        $this->outputPath = $input->getArgument(self::OUTPUT_PATH);

        $twigOptions = [
            'cache' => 'twig_cache',
            'auto_reload' => true,
            'strict_variables' => true
        ];
        if (array_key_exists('twig', $this->currentEnvironment)) {
            $twigOptions = array_merge($twigOptions, $this->currentEnvironment['twig']);
        }

        $loader = new FilesystemLoader('templates');
        $twigEnvironment = new Environment($loader, $twigOptions);

        if (!empty($twigOptions['debug'])) {
            $twigEnvironment->addExtension(new DebugExtension());
        }

        try {
            $output->writeln($this->getApplication()->getName());
            $output->writeln('');

            $output->writeln('<info>Settings</info>');
            $output->writeln("  <comment>Output folder: {$this->outputPath}</comment>");
            $output->writeln("  <comment>Environment: {$environmentKey}</comment>");
            $output->writeln('');

            $output->write('<info>Fetching database JSON file... </info>');
            $jsonData = $this->fetchJSON();
            $output->writeln('<comment>Done</comment>');
            $referenceCount = count($jsonData);
            $output->writeln("  {$referenceCount} references found");
            $output->writeln('');

            $output->write('<info>Generating search index...</info>');
            $searchIndexPath = $this->buildOutputPath('/searchIndex.json');
            $sig = new SearchIndexGenerator($jsonData, $searchIndexPath, false, $environmentKey !== 'prod');
            if (!$sig->generate()) {
                throw new Exception("Unable to generate search index.");
            }
            $output->writeln(' <comment>Done</comment>');

            $output->write('<info>Generating home page...</info>');
            $homePagePath = $this->buildOutputPath('/home.html');
            $hg = new HomeGenerator($jsonData, $homePagePath);
            if (!$hg->generate($twigEnvironment)) {
                throw new Exception("Unable to generate home page.");
            }
            $output->writeln(' <comment>Done</comment>');
            $output->writeln('');

            $output->writeln('<info>Generating album pages...</info>');
            $output->write('  Clearing album pages folder... ');
            $albumPagesFolder = $this->buildOutputPath('/albums');
            if (!FileHelpers::clearFolder($albumPagesFolder)) {
                throw new Exception("Unable to clear the album pages folder.");
            }
            $output->writeln('<comment>Done</comment>');

            foreach ($jsonData as $album) {
                $output->write("  Album: {$album['title']} ");
                $albumSlugFirstChar = $album['slug'][0];
                $filePath = $this->buildOutputPath("/albums/{$albumSlugFirstChar}/{$album['slug']}.html");
                $apg = new AlbumPageGenerator($this->currentEnvironment['base_url'], $album, $filePath);
                if (!$apg->generate($twigEnvironment)) {
                    throw new Exception("Unable to generate page album for slug '{$album['slug']}'");
                }
                $output->writeln('<comment>OK</comment>');
            }
            $output->writeln('');

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

    private function buildOutputPath(string $path): string
    {
        return "{$this->outputPath}{$path}";
    }
}
