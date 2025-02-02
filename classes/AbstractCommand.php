<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractCommand extends Command
{
    protected const OUTPUT_PATH = 'output_path';
    protected const ENVIRONMENT = 'environment';
    protected const DASHBOARD_EXPORT = 'dashboard-export';
    protected const SKIP_ALBUMS = 'skip-albums';
    protected const SKIP_ARTISTS = 'skip-artists';
    protected const SKIP_CATALOG = 'skip-catalog';
    protected const STATIC_FILES_ONLY = 'static-files-only';
    protected const SKIP_MINIFY = 'skip-minify';
    protected const PRETTY_SEARCH_INDEX = 'pretty-search-index';

    protected function configure()
    {
        $this
            ->addArgument(self::OUTPUT_PATH, InputArgument::REQUIRED, "Output path for the generate files")
            ->addOption(self::ENVIRONMENT, null, InputOption::VALUE_REQUIRED, 'Environment', 'dev')
            ->addOption(self::DASHBOARD_EXPORT, null, InputOption::VALUE_REQUIRED, "Uses the specified dashboard export file")
            ->addOption(self::SKIP_ALBUMS, description: "Skips generating album pages")
            ->addOption(self::SKIP_ARTISTS, description: "Skips generating artist pages")
            ->addOption(self::SKIP_CATALOG, description: "Skips generating catalog pages")
            ->addOption(self::STATIC_FILES_ONLY, description: "Generates static files only (js, css)")
            ->addOption(self::SKIP_MINIFY, description: "Skips minification")
            ->addOption(self::PRETTY_SEARCH_INDEX, description: "Generates a JSON search index with the 'pretty print' setting");
    }
}
