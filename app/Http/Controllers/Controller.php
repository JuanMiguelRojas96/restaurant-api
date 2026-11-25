<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Page size from ?per_page=, clamped so a client cannot ask for the whole table at once.
     */
    protected function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }
}
