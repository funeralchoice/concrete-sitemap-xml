<?php

namespace Concrete\Package\SitemapXml\Page\Element;

use Illuminate\Support\Collection;
use Symfony\Component\Serializer\Annotation\Ignore;

class Sitemap
{
    private string $loc;
    private string $lastmod;
    /**
     * @var string
     * @Ignore()
     */
    private string $fileName;

    /**
     * @var Collection<SitemapPage> $pages
     * @Ignore()
     */
    private Collection $pages;

    public function __construct()
    {
        $this->pages = new Collection();
    }

    /**
     * @return string
     */
    public function getLoc(): string
    {
        return $this->loc;
    }

    /**
     * @param string $loc
     * @return Sitemap
     */
    public function setLoc(string $loc): Sitemap
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
     * @return Sitemap
     */
    public function setLastmod(string $lastmod): Sitemap
    {
        $this->lastmod = $lastmod;
        return $this;
    }

    /**
     * @return Collection<SitemapPage>
     */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    /**
     * @param SitemapPage[] $pages
     * @return Sitemap
     */
    public function setPages(array $pages): Sitemap
    {
        $this->pages = new Collection($pages);
        return $this;
    }

    public function addPage(SitemapPage $page): Sitemap
    {
        $this->pages->add($page);
        return $this;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @param string $fileName
     * @return Sitemap
     */
    public function setFileName(string $fileName): Sitemap
    {
        $this->fileName = $fileName;
        return $this;
    }
}
