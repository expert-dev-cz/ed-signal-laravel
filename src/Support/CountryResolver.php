<?php

namespace ExpertDev\EdSignalLaravel\Support;

use Illuminate\Http\Request;

class CountryResolver
{
    /**
     * @param list<string> $headerKeys
     */
    public function __construct(
        private readonly array $headerKeys,
    ) {
    }

    public function resolve(Request $request): ?string
    {
        foreach ($this->headerKeys as $key) {
            $value = trim((string) $request->headers->get($key, ''));

            if ($value === '') {
                $serverValue = trim((string) $request->server->get($key, ''));
                $value = $serverValue;
            }

            $value = strtoupper($value);

            if ($value === '' || $value === 'XX' || $value === 'T1') {
                continue;
            }

            if (preg_match('/^[A-Z]{2}$/', $value) === 1) {
                return $value;
            }
        }

        return null;
    }
}
