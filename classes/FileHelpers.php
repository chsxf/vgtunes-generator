<?php

final class FileHelpers
{
    public static function clearFolder(string $folderPath): bool
    {
        if (is_dir($folderPath)) {
            $dirHandle = opendir($folderPath);
            while (($file = readdir($dirHandle)) !== false) {
                if (preg_match('/^\./', $file)) {
                    continue;
                }

                $filePath = "{$folderPath}/{$file}";
                if (is_dir($filePath)) {
                    if (!self::clearFolder($filePath)) {
                        closedir($dirHandle);
                        return false;
                    }
                } else if (is_file($filePath)) {
                    if (!unlink($filePath)) {
                        closedir($dirHandle);
                        return false;
                    }
                }
            }
            closedir($dirHandle);
        }
        return true;
    }
}
