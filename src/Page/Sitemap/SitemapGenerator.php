<?php

namespace Concrete\Package\SitemapXml\Page\Sitemap;

use Application\Helpers\ServiceHelper;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Multilingual\Page\Section\Section;
use Concrete\Core\Multilingual\Page\Section\Section;
use Concrete\Core\Multilingual\Page\Section\Section as MultilingualSection;
use Concrete\Core\Multilingual\Page\Section\Section as MultilingualSection;
use Concrete\Core\Page\Page;
use Concrete\Core\Site\Service;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Url\UrlImmutable;
use Concrete\Package\SitemapXml\Entity\SitemapXml;
use Concrete\Package\SitemapXml\Handler\AbstractHandler;
use Concrete\Package\SitemapXml\Page\Element\SitemapPageLink;
use DateTime;
use Concrete\Core\Url\Resolver\Manager\ResolverManager;
use Concrete\Package\SitemapXml\Page\Element\SitemapPage;
use Concrete\Core\Entity\Site\Site;

class SitemapGenerator
{
    private PageListGenerator $pageListGenerator;
    private Repository $config;
    /**
     * @var array<int>
     */
    private array $pagesProcessed = [];
    private ResolverManager $urlResolver;
    private bool $isMultilingual = false;
    /**
     * @var array<Section>|null $multilingualSections
     */
    private ?array $multilingualSections = null;

    /**
     * @var Site|null|false
     */
    private Site|null|false $site = false;
    private Service $siteService;

    public function __construct(PageListGenerator $pageListGenerator, Repository $config, ResolverManager $urlResolver, Service $siteService)
    {
        $this->pageListGenerator = $pageListGenerator;
        $this->config = $config;
        $this->urlResolver = $urlResolver;
        $this->siteService = $siteService;
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

                $this->populateLanguageAlternatives($sitemapPage, $page);
                /**
                 * @phpstan-ignore-next-line
                 */
                $this->pagesProcessed[$page->getSiteHomePageID()][] = $page->getCollectionID();
                if ($pulse !== null) {
                    $pulse($sitemapPage);
                }
                return $sitemapPage;
            }
        }
        return null;
    }


    protected function populateLanguageAlternatives(SitemapPage $sitemapPage, Page $page): void
    {
        $pageSection = $this->getMultilingualSectionForPage($page);
        if ($pageSection !== null) {
            foreach ($this->getMultilingualSections() as $relatedSection) {
                if ($relatedSection !== $pageSection) {
                    $relatedPageID = $relatedSection->getTranslatedPageID($page);
                    if ($relatedPageID) {
                        $relatedPage = Page::getByID($relatedPageID);
                        if ($relatedPage->getCollectionID() && $this->pageListGenerator->canIncludePageInSitemap($relatedPage)) {
                            $relatedUrl = $this->getPageLink($relatedPage);
                            $xLink = new SitemapPageLink();
                            $locale = $relatedPage->getSiteTreeObject()?->getLocale()->getLocale();
                            $lang = strtolower(str_replace('_', '-', $locale));
                            $xLink->setRel('alternate')
                                ->setHreflang($lang)
                                ->setHref($relatedUrl);
                            $sitemapPage->addLink($xLink);
                        }
                    }
                }
            }
        }
    }

    public function getMultilingualSectionForPage(Page $page): ?Section
    {
        $result = null;
        $siteTree = $page->getSiteTreeObject();
        if ($siteTree !== null) {
            $homeID = $siteTree->getSiteHomePageID();
            if ($homeID) {
                $mlSections = $this->getMultilingualSections();
                if (isset($mlSections[$homeID])) {
                    $result = $mlSections[$homeID];
                }
            }
        }

        return $result;
    }

    /**
     * @return array<Section>
     */
    public function getMultilingualSections(): array
    {
        if ($this->multilingualSections === null) {
            $site = $this->getSite();
            if ($site === null) {
                $this->multilingualSections = [];
            } else {
                $list = [];
                if ($site) {
                    foreach (MultilingualSection::getList($site) as $section) {
                        $list[$section->getCollectionID()] = $section;
                    }
                }
                $this->multilingualSections = $list;
            }
        }

        return $this->multilingualSections;
    }

    public function getSite(): false|\Concrete\Core\Entity\Site\Site|null
    {
        if ($this->site === false) {
            $this->site = $this->siteService->getDefault();
        }

        return $this->site;
    }

    public function setSite(false|\Concrete\Core\Entity\Site\Site|null $site): self
    {
        $this->site = $site;
        return $this;
    }

    /**
     * @param Page $section
     * @param callable|null $pulse
     * @return SitemapPage[]
     */
    public function getGeneralPages(Page $section, ?callable $pulse = null): array
    {
        $sitemapPages = [];
        $excludeIds = [];
        /**
         * @var int|null $homepageId
         */
        $homepageId = $section->getSiteTreeObject()?->getSiteHomePageID();
        if ($homepageId && array_key_exists($homepageId, $this->pagesProcessed)) {
            /**
             * @var array<int> $excludeIds
             */
            $excludeIds = $this->pagesProcessed[$homepageId];
        }
        $pages = $this->pageListGenerator->getGeneralPages($section, $excludeIds);
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

    public function isMultilingual(): bool
    {
        return $this->isMultilingual;
    }

    public function setIsMultilingual(bool $isMultilingual): self
    {
        $this->isMultilingual = $isMultilingual;
        return $this;
    }
}
