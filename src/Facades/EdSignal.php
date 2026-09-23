<?php

namespace ExpertDev\EdSignalLaravel\Facades;

use Illuminate\Support\Facades\Facade;

class EdSignal extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ed-signal';
    }
}
