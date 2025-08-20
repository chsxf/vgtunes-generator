<?php

class PlatformHelpers
{
    private const array ORDER = [
        'apple_music',
        'deezer',
        'spotify',
        'tidal',
        'bandcamp',
        'steam_game',
        'steam_soundtrack'
    ];

    public static function sortPlatformKeys($keyA, $keyB)
    {
        $keyAIndex = array_search($keyA, self::ORDER);
        $keyBIndex = array_search($keyB, self::ORDER);

        if ($keyAIndex === false && $keyBIndex === false) {
            return $keyA <=> $keyB;
        }
        return $keyAIndex <=> $keyBIndex;
    }
}
