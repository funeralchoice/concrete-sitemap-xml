<?php

namespace Concrete\Package\SitemapXml\Controller;

use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Site\Service;
use Concrete\Core\Support\Facade\Application;
use Symfony\Component\HttpFoundation\Response;

class SitemapXmlController extends AbstractController
{
    public function index(): Response
    {
        /**
         * @var Service $siteService
         */
        $siteService = Application::getFacadeApplication()->make(Service::class);
        $site = $siteService->getSite();
        if (!$site) {
            return new Response('404 Not Found', Response::HTTP_NOT_FOUND);
        }
        /**
         * @phpstan-ignore-next-line
         * @var string $xml
         */
        $fileName = DIR_BASE . "/sitemaps/{$site->getSiteHandle()}-sitemap.xml";
        if (file_exists($fileName)) {
            /**
             * @var string $xml
             */
            $xml = file_get_contents($fileName);
            $response = new Response($xml);
            $response->headers->set('Content-Type', 'application/xml');
            return $response;
        }

        return new Response('404 Not Found', Response::HTTP_NOT_FOUND);
    }
}
