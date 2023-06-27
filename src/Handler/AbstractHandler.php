<?php

namespace Concrete\Package\SitemapXml\Handler;

use Concrete\Core\Page\Page;
use Concrete\Package\SitemapXml\Page\Element\SitemapPage;
use Concrete\Package\SitemapXml\Page\Sitemap\SitemapGenerator;

abstract class AbstractHandler
{
    public function __construct(protected SitemapGenerator $sitemapGenerator)
    {

    }

    /**
     * @param Page $parent
     * @param SitemapPage[] $sitemapPages
     * @param callable|null $pulse
     * @return void
     */
    abstract public function generate(Page $parent, array &$sitemapPages, ?callable $pulse = null): void;

    public function getSitemapGenerator(): SitemapGenerator
    {
        return $this->sitemapGenerator;
    }
}
