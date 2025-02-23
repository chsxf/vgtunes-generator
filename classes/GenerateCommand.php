<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

#[AsCommand("generate", "Generate website content")]
final class GenerateCommand extends AbstractCommand implements IOutputPathBuilder
{
    private ?array $currentEnvironment = null;
    private ?string $outputPath = null;

    private Environment $twigEnvironment;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $environmentKey = $input->getOption(self::ENVIRONMENT);
        $envFilePath = "environments/env.{$environmentKey}.php";
        $this->currentEnvironment = require_once($envFilePath);

        $twigOptions = [
            'cache' => false,
            'auto_reload' => true,
            'strict_variables' => true
        ];
        if (array_key_exists('twig', $this->currentEnvironment)) {
            $twigOptions = array_merge($twigOptions, $this->currentEnvironment['twig']);
        }

        $loader = new FilesystemLoader('templates');
        $this->twigEnvironment = new Environment($loader, $twigOptions);
        if (!empty($twigOptions['debug'])) {
            $this->twigEnvironment->addExtension(new DebugExtension());
        }
        $this->twigEnvironment->addGlobal('base_url', $this->currentEnvironment['base_url']);
        $this->twigEnvironment->addGlobal('platform_names', [
            'apple_music' => 'Apple Music',
            'bandcamp' => 'Bandcamp',
            'deezer' => 'Deezer',
            'spotify' => 'Spotify'
        ]);

        $this->outputPath = $input->getArgument(self::OUTPUT_PATH);

        try {
            $output->writeln($this->getApplication()->getName());
            $output->writeln('');

            $output->writeln('<info>Settings</info>');
            $output->writeln("  <comment>Output folder: {$this->outputPath}</comment>");
            $output->writeln("  <comment>Environment: {$input->getOption(self::ENVIRONMENT)}</comment>");
            $output->writeln('');

            $output->writeln('<info>Compiling SCSS files</info>');
            $output->write('  <comment>Clearing CSS folder...</comment> ');
            $cssFolder = $this->buildOutputPath('/css');
            if (!FileHelpers::clearFolder($cssFolder)) {
                throw new Exception("Unable to clear the CSS folder.");
            }
            $output->writeln('<info>Done</info>');
            $scssCompiler = new SCSSCompiler('assets/scss', $cssFolder);
            if (!$scssCompiler->process($output)) {
                throw new Exception('Unable to compile SCSS files');
            }
            $output->writeln(' <info>Done</info>');
            $output->writeln('');

            $output->writeln('<info>Exporting JS files</info>');
            $output->write('  <comment>Clearing JS folder...</comment> ');
            $jsFolder = $this->buildOutputPath('/js');
            if (!FileHelpers::clearFolder($jsFolder)) {
                throw new Exception("Unable to clear the JS folder.");
            }
            $output->writeln('<info>Done</info>');
            $jsManager = new JavascriptManager('assets/js', $jsFolder);
            if (!$jsManager->process($output)) {
                throw new Exception('Unable to export JS files');
            }
            $output->writeln(' <info>Done</info>');
            $output->writeln('');

            $output->writeln('<info>Exporting images</info>');
            $output->write('  <comment>Clearing images folder...</comment> ');
            $imgFolder = $this->buildOutputPath('/images');
            if (!FileHelpers::clearFolder($imgFolder)) {
                throw new Exception("Unable to clear the images folder.");
            }
            $output->writeln('<info>Done</info>');
            $imgManager = new ImageManager('assets/images', $imgFolder);
            if (!$imgManager->process($output)) {
                throw new Exception('Unable to export images files');
            }
            $output->writeln(' <info>Done</info>');
            $output->writeln('');

            if (!$input->getOption(self::STATIC_FILES_ONLY)) {
                $output->writeln('<info>Warming up git hashes cache...</info>');
                $gitHashCache = new GitHashManager($this->buildOutputPath('/'));
                if (!$gitHashCache->process($output)) {
                    throw new Exception('Unable to warm up git hashed cache');
                }
                $this->twigEnvironment->addGlobal('git_hash_cache', $gitHashCache);
                $output->writeln(' <info>Done</info>');
                $output->writeln('');

                $dashboardExportPath = $input->getOption(self::DASHBOARD_EXPORT);
                if ($dashboardExportPath === null) {
                    $output->writeln('<info>Fetching database JSON file... </info>');
                    $jsonData = $this->fetchJSON($this->currentEnvironment['dashboard_key']);
                } else {
                    $output->writeln("<info>Loading dashboard export file '{$dashboardExportPath}'...</info> ");
                    if (!file_exists($dashboardExportPath)) {
                        throw new Exception("Dashboard export file '{$dashboardExportPath}' does not exist");
                    }
                    $jsonData = json_decode(file_get_contents($dashboardExportPath), flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
                }
                if (!isset($jsonData['albums']) || !array_is_list($jsonData['albums']) || empty($jsonData['artists'])) {
                    throw new Exception("Invalid JSON format");
                }
                $albumCount = count($jsonData['albums']);
                $artistCount = count($jsonData['artists']);
                $output->writeln("  <comment>Found {$albumCount} albums</comment>");
                $output->writeln("  <comment>Found {$artistCount} artists</comment>");
                $output->writeln('<info>Done</info>');
                $output->writeln('');

                $output->write('<info>Generating search index...</info>');
                $searchIndexPath = $this->buildOutputPath('/searchIndex.json');
                $prettyPrint = $this->currentEnvironment['search_index_pretty_print'] ?? $input->getOption(self::PRETTY_SEARCH_INDEX);
                $sig = new SearchIndexGenerator($jsonData, $searchIndexPath, false, $prettyPrint);
                if (!$sig->generate()) {
                    throw new Exception("Unable to generate search index.");
                }
                $output->writeln(' <info>Done</info>');

                $output->write('<info>Generating home page...</info>');
                $homePagePath = $this->buildOutputPath('/index.html');
                $hg = new HomeGenerator($jsonData, $homePagePath);
                if (!$hg->generate($this->twigEnvironment)) {
                    throw new Exception("Unable to generate home page.");
                }
                $output->writeln(' <info>Done</info>');

                $output->write('<info>Generating privacy policy and settings page...</info>');
                $cookiePrivacyPath = $this->buildOutputPath('/privacy-policy-and-settings.html');
                $cpg = new PrivacyPageGenerator($jsonData, $cookiePrivacyPath);
                if (!$cpg->generate($this->twigEnvironment)) {
                    throw new Exception("Unable to generate privacy policy and settings page.");
                }
                $output->writeln(' <info>Done</info>');

                $output->write('<info>Generating 404 page...</info>');
                $pageNotFoundPath = $this->buildOutputPath('/404.html');
                $cpg = new PageNotFoundGenerator($jsonData, $pageNotFoundPath);
                if (!$cpg->generate($this->twigEnvironment)) {
                    throw new Exception("Unable to generate 404 page.");
                }
                $output->writeln(' <info>Done</info>');
                $output->writeln('');

                $output->write('<info>Generating robots.txt file...</info>');
                $robotsTxtPath = $this->buildOutputPath('/robots.txt');
                $rtg = new RobotsTxtGenerator($robotsTxtPath, $this->currentEnvironment['base_url']);
                if (!$rtg->generate()) {
                    throw new Exception("Unable to generate robots.txt file.");
                }
                $output->writeln(' <info>Done</info>');
                $output->writeln('');

                $output->writeln('<info>Generating album pages...</info>');
                if (!$input->getOption(self::SKIP_ALBUMS)) {
                    $output->write('  <comment>Clearing album pages folder...</comment> ');
                    $artistPagesFolder = $this->buildOutputPath('/albums');
                    if (!FileHelpers::clearFolder($artistPagesFolder)) {
                        throw new Exception("Unable to clear the album pages folder.");
                    }
                    $output->writeln('<info>Done</info>');

                    foreach ($jsonData['albums'] as $album) {
                        $output->write("  <comment>Album: {$album['title']}</comment> ");
                        $filePath = $this->buildOutputPath("/albums/{$album['slug']}/index.html");
                        $apg = new AlbumPageGenerator($this->currentEnvironment['base_url'], $album, $filePath, $jsonData['artists']);
                        if (!$apg->generate($this->twigEnvironment)) {
                            throw new Exception("Unable to generate page album for slug '{$album['slug']}'");
                        }
                        $output->writeln('<info>OK</info>');
                    }
                } else {
                    $output->writeln('  <info>Skipping</info>');
                }
                $output->writeln('');

                $output->writeln('<info>Generating artist pages...</info>');
                if (!$input->getOption(self::SKIP_ARTISTS)) {
                    $output->write('  <comment>Clearing artist pages folder...</comment> ');
                    $artistPagesFolder = $this->buildOutputPath('/artists');
                    if (!FileHelpers::clearFolder($artistPagesFolder)) {
                        throw new Exception("Unable to clear the artist pages folder.");
                    }
                    $output->writeln('<info>Done</info>');

                    foreach ($jsonData['artists'] as $slug => $name) {
                        $output->write("  <comment>Artist: {$name}</comment> ");
                        $filePath = $this->buildOutputPath("/artists/{$slug}/index.html");
                        $albums = array_values(array_filter($jsonData['albums'], fn($album) => in_array($slug, $album['artists'])));
                        $apg = new ArtistPageGenerator($this->currentEnvironment['base_url'], $filePath, $slug, $name, $albums);
                        if (!$apg->generate($this->twigEnvironment)) {
                            throw new Exception("Unable to generate artist page for slug '{$slug}'");
                        }
                        $output->writeln('<info>OK</info>');
                    }
                } else {
                    $output->writeln('  <info>Skipping</info>');
                }
                $output->writeln('');

                $output->writeln('<info>Generating catalog pages...</info>');
                if (!$input->getOption(self::SKIP_CATALOG)) {
                    $output->write('  <comment>Clearing catalog pages folder...</comment> ');
                    $artistPagesFolder = $this->buildOutputPath('/catalog');
                    if (!FileHelpers::clearFolder($artistPagesFolder)) {
                        throw new Exception("Unable to clear the catalog pages folder.");
                    }
                    $output->writeln('<info>Done</info>');

                    $cg = new CatalogGenerator($this->currentEnvironment['base_url'], $jsonData, $this);
                    if (!$cg->generate($output, $this->twigEnvironment)) {
                        throw new Exception("Unable to generate catalog pages");
                    }
                } else {
                    $output->writeln('  <info>Skipping</info>');
                }
                $output->writeln('');
            }

            $output->writeln('<info>Replacements...</info>');
            $rm = new ReplacementsManager($this->buildOutputPath('/'), $this->currentEnvironment['replacements']);
            $output->writeln('  <comment>Populating files to apply replacements...</comment>');
            $rm->populate($output);
            $output->writeln('  <comment>Processing files...</comment>');
            if (!$rm->process($output)) {
                throw new Exception('Unable to apply replacements');
            }
            $output->writeln('  <comment>Replacements Complete</comment>');
            $output->writeln('');

            $output->write('<info>Minification...</info>');
            if (empty($this->currentEnvironment['minify']) || $input->getOption(self::SKIP_MINIFY)) {
                $output->writeln(' <info>Skipped</info>');
            } else {
                $output->writeln('');
                $mm = new MinificationManager($this->buildOutputPath('/'));
                $output->writeln('  <comment>Populating files to minify...</comment>');
                $mm->populate($output);
                $output->writeln('  <comment>Processing files...</comment>');
                if (!$mm->process($output)) {
                    throw new Exception('Unable to complete minification');
                }
                $output->writeln('  <info>Minification Complete</info>');
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

        return json_decode($content, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
    }

    private function buildDashboardUrl(string $path): string
    {
        return "{$this->currentEnvironment['dashboard_url']}{$path}";
    }

    public function buildOutputPath(string $relativePath): string
    {
        return "{$this->outputPath}{$relativePath}";
    }
}
