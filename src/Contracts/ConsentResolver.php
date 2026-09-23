<?php

namespace ExpertDev\EdSignalLaravel\Contracts;

use Illuminate\Http\Request;

interface ConsentResolver
{
    /**
     * @return array{analytics:bool,marketing:bool,preferences:bool}
     */
    public function resolve(Request $request): array;

    public function receiptToken(Request $request): ?string;
}
