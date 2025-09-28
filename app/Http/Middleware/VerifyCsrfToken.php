<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Whether to set XSRF-TOKEN cookie in response
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * URIs that should be excluded from CSRF verification.
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
