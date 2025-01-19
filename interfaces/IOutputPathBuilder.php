<?php

interface IOutputPathBuilder
{
    function buildOutputPath(string $relativePath): string;
}
