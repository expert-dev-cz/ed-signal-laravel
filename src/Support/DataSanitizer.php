<?php

namespace ExpertDev\EdSignalLaravel\Support;

class DataSanitizer
{
    /**
     * @param array<string, mixed> $data
     * @param list<string> $blockedFragments
     * @return array<string, mixed>
     */
    public function sanitize(array $data, array $blockedFragments): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if ($this->isBlockedKey($key, $blockedFragments)) {
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->sanitize($value, $blockedFragments);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @param list<string> $blockedFragments */
    private function isBlockedKey(string $key, array $blockedFragments): bool
    {
        $lower = strtolower($key);

        foreach ($blockedFragments as $fragment) {
            if ($fragment !== '' && str_contains($lower, strtolower($fragment))) {
                return true;
            }
        }

        return false;
    }
}
