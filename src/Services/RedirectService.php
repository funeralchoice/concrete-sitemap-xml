<?php

declare(strict_types=1);

namespace Concrete\Package\SitemapXml\Services;

use Concrete\Package\SitemapXml\Helpers\ServiceHelper;
use Concrete\Core\Routing\RedirectResponse;
use Concrete\Core\Support\Facade\Url;
use Symfony\Component\HttpFoundation\Cookie;

class RedirectService
{
    /**
     * Redirect, optionally setting a cookie
     *
     * @param string $url
     * @param Cookie|null $cookie
     */
    public function redirect(string $url, Cookie $cookie = null): void
    {
        $response = new RedirectResponse((string) Url::to($url));
        if ($cookie !== null) {
            $response->headers->setCookie($cookie);
        }
        $response->send();
        ServiceHelper::app()->shutdown();
    }
}
