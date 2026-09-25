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

        $configJson = json_encode($initConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        $sdkUrlJson = json_encode($sdkUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        if (!is_string($configJson) || !is_string($sdkUrlJson)) {
            return $html;
        }

        $snippet = "\n<script data-ed-signal-measurement-sdk=\"1\" data-ed-signal-bootstrap=\"1\">(function(){"
            . 'var config=' . $configJson . ';'
            . 'function init(){if(window.ExpertMeasurement){window.ExpertMeasurement.init(config);}}'
            . 'if(window.ExpertMeasurement){init();return;}'
            . 'var script=document.createElement("script");script.async=true;script.src=' . $sdkUrlJson . ';'
            . 'script.onload=init;(document.head||document.documentElement).appendChild(script);'
            . "})();</script>\n";

        if (str_contains($html, '</head>')) {
            return str_replace('</head>', $snippet . '</head>', $html);
        }

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $snippet . '</body>', $html);
        }

        return $html . $snippet;
    }
}
