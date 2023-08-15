<?php

namespace Concrete\Package\SitemapXml\Page\Element;

class MultilingualSitemapIndexes
{
    /**
     * @var MultilingualSitemapIndex[] $sitemaps
     */
    private array $sitemaps = [];

    /**
     * @return MultilingualSitemapIndex[]
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @param MultilingualSitemapIndex[] $sitemap
     * @return MultilingualSitemapIndexes
     */
    public function setSitemaps(array $sitemap): MultilingualSitemapIndexes
    {
        $this->sitemaps = $sitemap;
        return $this;
    }

    public function addSitemap(MultilingualSitemapIndex $sitemap): MultilingualSitemapIndexes
    {
        $this->sitemaps[] = $sitemap;
        return $this;
    }
}
