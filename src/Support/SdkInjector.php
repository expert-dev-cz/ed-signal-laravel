<?php

namespace ExpertDev\EdSignalLaravel\Support;

class SdkInjector
{
    /**
     * @param array<string, mixed> $initConfig
     */
    public function inject(string $html, string $sdkUrl, array $initConfig): string
    {
        if ($html === '' || $sdkUrl === '') {
            return $html;
        }

        if (str_contains($html, 'ed-signal-measurement-sdk')) {
            return $html;
        }

        $configJson = json_encode($initConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($configJson)) {
            return $html;
        }

        $snippet = "\n<script async src=\"" . e($sdkUrl) . "\" data-ed-signal-measurement-sdk=\"1\"></script>\n"
            . "<script>window.ExpertMeasurement&&window.ExpertMeasurement.init(" . $configJson . ");</script>\n";

        if (str_contains($html, '</head>')) {
            return str_replace('</head>', $snippet . '</head>', $html);
        }

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $snippet . '</body>', $html);
        }

        return $html . $snippet;
    }
}
