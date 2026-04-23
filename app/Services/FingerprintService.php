<?php

namespace App\Services;

class FingerprintService
{
    public function forException(string $class, ?string $file, ?int $line): string
    {
        return hash('sha256', implode('|', [
            $class,
            $this->normalizePath($file),
            (string) $line,
        ]));
    }

    public function forLog(string $channel, string $level, string $message): string
    {
        return hash('sha256', implode('|', [
            $channel,
            $level,
            $this->templatize($message),
        ]));
    }

    public function titleForException(string $class, string $message): string
    {
        $short = mb_substr(trim($message), 0, 180);

        return trim($class.': '.$short, ': ');
    }

    public function titleForLog(string $channel, string $message): string
    {
        $short = mb_substr(trim($message), 0, 200);

        return trim('['.$channel.'] '.$short);
    }

    private function normalizePath(?string $file): string
    {
        if ($file === null || $file === '') {
            return '';
        }

        $file = str_replace('\\', '/', $file);

        // Strip any leading project path — vendor/ and app/ are the meaningful anchors.
        foreach (['/vendor/', '/app/', '/bootstrap/', '/config/', '/database/', '/routes/', '/tests/'] as $anchor) {
            $pos = strrpos($file, $anchor);
            if ($pos !== false) {
                return ltrim(substr($file, $pos), '/');
            }
        }

        return basename($file);
    }

    private function templatize(string $message): string
    {
        $patterns = [
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i' => '{uuid}',
            '/\b[0-9a-f]{32,64}\b/i' => '{hash}',
            '/\b\d+(\.\d+)?\b/' => '{n}',
            '/"[^"]*"/' => '"{s}"',
            "/'[^']*'/" => "'{s}'",
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $message) ?? $message;
    }
}
