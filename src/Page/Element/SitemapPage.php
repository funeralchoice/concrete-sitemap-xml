<?php

namespace Concrete\Package\SitemapXml\Page\Element;

class SitemapPage
{
    private string $loc;
    private string $lastmod;
    private string $changefreq;
    private string $priority;

    /**
     * @return string
     */
    public function getLoc(): string
    {
        return $this->loc;
    }

    /**
     * @param string $loc
     * @return SitemapPage
     */
    public function setLoc(string $loc): SitemapPage
    {
        $this->loc = $loc;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastmod(): string
    {
        return $this->lastmod;
    }

    /**
     * @param string $lastmod
     * @return SitemapPage
     */
    public function setLastmod(string $lastmod): SitemapPage
    {
        $this->lastmod = $lastmod;
        return $this;
    }

    /**
     * @return string
     */
    public function getChangefreq(): string
    {
        return $this->changefreq;
    }

    /**
     * @param string $changefreq
     * @return SitemapPage
     */
    public function setChangefreq(string $changefreq): SitemapPage
    {
        $this->changefreq = $changefreq;
        return $this;
    }

    /**
     * @return string
     */
    public function getPriority(): string
    {
        return $this->priority;
    }

    /**
     * @param string $priority
     * @return SitemapPage
     */
    public function setPriority(string $priority): SitemapPage
    {
        $this->priority = $priority;
        return $this;
    }
}
