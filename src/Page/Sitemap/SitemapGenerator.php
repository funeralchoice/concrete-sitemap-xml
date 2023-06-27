<?php

namespace Concrete\Package\SitemapXml\Page\Sitemap;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Page\Page;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Url\UrlImmutable;
use Concrete\Package\SitemapXml\Entity\SitemapXml;
use Concrete\Package\SitemapXml\Handler\AbstractHandler;
use DateTime;
use Concrete\Core\Url\Resolver\Manager\ResolverManager;
use Concrete\Package\SitemapXml\Page\Element\SitemapPage;

class SitemapGenerator
{
    private PageListGenerator $pageListGenerator;
    private Repository $config;
    /**
     * @var array<int>
     */
    private array $pagesProcessed = [];
    private ResolverManager $urlResolver;

    public function __construct(PageListGenerator $pageListGenerator, Repository $config, ResolverManager $urlResolver)
    {
        $this->pageListGenerator = $pageListGenerator;
        $this->config = $config;
        $this->urlResolver = $urlResolver;
    }

    /**
     * @param SitemapXml $index
     * @param callable|null $pulse
     * @return SitemapPage[]
     * @throws \Exception
     */
    public function generatePages(SitemapXml $index, ?callable $pulse = null): array
    {
        $parentPage = Page::getByID($index->getPageId(), 'ACTIVE');
        $pages = $this->pageListGenerator->getPagesByParent($parentPage);
        $sitemapPages = [];

        foreach ($pages as $page) {
            $sitemapPage = $this->getSitemapPage($page, $pulse);
            if ($sitemapPage) {
                $sitemapPages[] = $sitemapPage;
            }
        }

        if ($index->getHandler()) {
            /**
             * @var AbstractHandler $handlerClass
             */
            $handlerClass = Application::getFacadeApplication()->make($index->getHandler(), [$this]);
            $handlerClass->generate($parentPage, $sitemapPages, $pulse);
        }
        return $sitemapPages;
    }

    private function getSitemapPage(Page $page, ?callable $pulse = null): ?SitemapPage
    {
        if ($this->pageListGenerator->canIncludePageInSitemap($page)) {
            if ($page->getCollectionID()) {
                $lastModified = new DateTime($page->getCollectionDateLastModified() ?? '');
                $sitemapPage = (new SitemapPage())
                    ->setLoc($this->getPageLink($page))
                    ->setLastmod($lastModified->format(DateTime::W3C))
                    ->setChangefreq($this->getPageChangeFrequency($page))
                    ->setPriority($this->getPagePriority($page));
                $this->pagesProcessed[] = $page->getCollectionID();
                if ($pulse !== null) {
                    $pulse($sitemapPage);
                }
                return $sitemapPage;
            }
        }
        return null;
    }

    /**
     * @param callable|null $pulse
     * @return SitemapPage[]
     */
    public function getGeneralPages(?callable $pulse = null): array
    {
        $sitemapPages = [];
        $pages = $this->pageListGenerator->getGeneralPages($this->pagesProcessed);
        foreach ($pages as $page) {
            $sitemapPage = $this->getSitemapPage($page, $pulse);
            if ($sitemapPage) {
                $sitemapPages[] = $sitemapPage;
            }
        }
        return $sitemapPages;
    }

    public function getPageChangeFrequency(?Page $page = null): string
    {
        /**
         * @var string|null $changeFrequency
         */
        $changeFrequency = $page ? $page->getAttribute('sitemap_changefreq') : null;
        if ($changeFrequency === null) {
            /**
             * @var string $changeFrequency
             */
            $changeFrequency = $this->config->get('concrete.sitemap_xml.frequency');
        }
        return $changeFrequency;
    }

    public function getPagePriority(?Page $page = null): string
    {
        /**
         * @var string|null $priority
         */
        $priority = $page ? $page->getAttribute('sitemap_priority') : null;
        if ($priority === null) {
            /**
             * @var string $priority
             */
            $priority = $this->config->get('concrete.sitemap_xml.priority');
        }
        return $priority;
    }

    /**
     * @param Page $page
     * @param array<string|int>|null $params
     * @return string
     */
    public function getPageLink(Page $page, ?array $params = []): string
    {
        $params = $params ? array_merge([$page], $params) : [$page];
        /**
         * @var UrlImmutable $url
         */
        $url = $this->urlResolver->resolve($params);
        return $url->__toString();
    }

    /**
     * @return PageListGenerator
     */
    public function getPageListGenerator(): PageListGenerator
    {
        return $this->pageListGenerator;
    }
}
