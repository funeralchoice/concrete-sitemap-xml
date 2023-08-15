<?php

namespace Concrete\Package\SitemapXml\Page\Element;

class SitemapIndex
{
    /**
     * @var Sitemap[] $sitemaps
     */
    private array $sitemaps = [];

    /**
     * @return Sitemap[]
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @param Sitemap[] $sitemap
     * @return SitemapIndex
     */
    public function setSitemaps(array $sitemap): SitemapIndex
    {
        $this->sitemaps = $sitemap;
        return $this;
    }

    public function addSitemap(Sitemap $sitemap): SitemapIndex
    {
        $this->sitemaps[] = $sitemap;
        return $this;
    }
}
