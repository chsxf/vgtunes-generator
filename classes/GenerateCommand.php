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
    private const STATIC_FILES_ONLY = 'static-files-only';
    private const SKIP_MINIFY = 'skip-minify';
    private const PRETTY_SEARCH_INDEX = 'pretty-search-index';

    private ?array $currentEnvironment = null;
    private ?string $outputPath = null;

    function configure()
    {
        $this
            ->addArgument(self::OUTPUT_PATH, InputArgument::REQUIRED, "Output path for the generate files")
            ->addOption(self::ENVIRONMENT, null, InputOption::VALUE_REQUIRED, 'Environment', 'dev')
            ->addOption(self::STATIC_FILES_ONLY, description: "Generates static files only (js, css)")
            ->addOption(self::SKIP_MINIFY, description: "Skips minification")
            ->addOption(self::PRETTY_SEARCH_INDEX, description: "Generates a JSON search index with the 'pretty print' setting");
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
        $twigEnvironment->addGlobal('base_url', $this->currentEnvironment['base_url']);

        try {
            $output->writeln($this->getApplication()->getName());
            $output->writeln('');

            $output->writeln('<info>Settings</info>');
            $output->writeln("  <comment>Output folder: {$this->outputPath}</comment>");
            $output->writeln("  <comment>Environment: {$environmentKey}</comment>");
            $output->writeln('');

            $output->writeln('<info>Compile CSS files</info>');
            $scssCompiler = new SCSSCompiler('scss', $this->buildOutputPath('/css'));
            if (!$scssCompiler->process($output)) {
                throw new Exception('Unable to compile SCSS files');
            }
            $output->writeln(' <comment>Done</comment>');
            $output->writeln('');

            $output->writeln('<info>Export JS files</info>');
            $jsManager = new JavascriptManager('js', $this->buildOutputPath('/js'));
            if (!$jsManager->process($output)) {
                throw new Exception('Unable to export JS files');
            }
            $output->writeln(' <comment>Done</comment>');
            $output->writeln('');

            if (!$input->getOption(self::STATIC_FILES_ONLY)) {
                $output->write('<info>Fetching database JSON file... </info>');
                $jsonData = $this->fetchJSON($this->currentEnvironment['dashboard_key']);
                $output->writeln('<comment>Done</comment>');
                $referenceCount = count($jsonData);
                $output->writeln("  {$referenceCount} references found");
                $output->writeln('');

                $output->writeln('<info>Warming up git hashes cache...</info>');
                $gitHashCache = new GitHashManager($this->buildOutputPath('/'));
                if (!$gitHashCache->process($output)) {
                    throw new Exception('Unable to warm up git hashed cache');
                }
                $output->writeln(' <comment>Done</comment>');
                $twigEnvironment->addGlobal('git_hash_cache', $gitHashCache);

                $output->write('<info>Generating search index...</info>');
                $searchIndexPath = $this->buildOutputPath('/searchIndex.json');
                $prettyPrint = $this->currentEnvironment['search_index_pretty_print'] ?? $input->getOption(self::PRETTY_SEARCH_INDEX);
                $sig = new SearchIndexGenerator($jsonData, $searchIndexPath, false, $prettyPrint);
                if (!$sig->generate()) {
                    throw new Exception("Unable to generate search index.");
                }
                $output->writeln(' <comment>Done</comment>');

                $output->write('<info>Generating home page...</info>');
                $homePagePath = $this->buildOutputPath('/index.html');
                $hg = new HomeGenerator($jsonData, $homePagePath);
                if (!$hg->generate($twigEnvironment)) {
                    throw new Exception("Unable to generate home page.");
                }
                $output->writeln(' <comment>Done</comment>');

                $output->write('<info>Generating privacy policy and settings page...</info>');
                $cookiePrivacyPath = $this->buildOutputPath('/privacy-policy-and-settings.html');
                $cpg = new PrivacyPageGenerator($jsonData, $cookiePrivacyPath);
                if (!$cpg->generate($twigEnvironment)) {
                    throw new Exception("Unable to generate privacy policy and settings page.");
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
                    $filePath = $this->buildOutputPath("/albums/{$album['slug']}/index.html");
                    $apg = new AlbumPageGenerator($this->currentEnvironment['base_url'], $album, $filePath);
                    if (!$apg->generate($twigEnvironment)) {
                        throw new Exception("Unable to generate page album for slug '{$album['slug']}'");
                    }
                    $output->writeln('<comment>OK</comment>');
                }
                $output->writeln('');
            }

            $output->writeln('<info>Replacements...</info>');
            $rm = new ReplacementsManager($this->buildOutputPath('/'), $this->currentEnvironment['replacements']);
            $output->writeln('  Populating files to apply replacements...');
            $rm->populate($output);
            $output->writeln('  Processing files...');
            if (!$rm->process($output)) {
                throw new Exception('Unable to apply replacements');
            }
            $output->writeln('  <comment>Replacements Complete</comment>');
            $output->writeln('');

            $output->write('<info>Minification...</info>');
            if (empty($this->currentEnvironment['minify']) || $input->getOption(self::SKIP_MINIFY)) {
                $output->writeln(' <comment>Skipped</comment>');
            } else {
                $output->writeln('');
                $mm = new MinificationManager($this->buildOutputPath('/'));
                $output->writeln('  Populating files to minify...');
                $mm->populate($output);
                $output->writeln('  Processing files...');
                if (!$mm->process($output)) {
                    throw new Exception('Unable to complete minification');
                }
                $output->writeln('  <comment>Minification Complete</comment>');
            }
            $output->writeln('');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('');
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function fetchJSON(string $token): array
    {
        $url = $this->buildDashboardUrl('/JsonGenerator/generate');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['VGTunes-Token: ' . base64_encode($token)],
            CURLOPT_RETURNTRANSFER => true
        ]);
        $content = curl_exec($ch);
        curl_close($ch);
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
