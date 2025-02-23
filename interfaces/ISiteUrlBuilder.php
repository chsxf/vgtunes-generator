<?php

interface ISiteUrlBuilder
{
    function buildSiteUrl(string $relativePath): string;
}
