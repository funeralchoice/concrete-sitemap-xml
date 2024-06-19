<?php

namespace Concrete\Package\SitemapXml\Helpers;

use Concrete\Core\Application\Application;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class ServiceHelper
{
    public static function app(): Application
    {
        return \Concrete\Core\Support\Facade\Application::getFacadeApplication();
    }

    public static function flashBag(): FlashBagInterface
    {
        $instance = self::app()->make('session');
        if ($instance instanceof Session === false) {
            throw new Exception(sprintf('Unable to load %s', Session::class));
        }
        return $instance->getFlashBag();
    }
}
