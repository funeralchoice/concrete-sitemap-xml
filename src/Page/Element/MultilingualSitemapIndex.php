<?php

namespace Concrete\Package\SitemapXml\Page\Element;

use Illuminate\Support\Collection;
use Symfony\Component\Serializer\Annotation\Ignore;

class MultilingualSitemapIndex
{
    private string $loc;
    private string $lastmod;
    /**
     * @var string
     * @Ignore()
     */
    private string $fileName;

    /**
     * @var Collection<Sitemap> $sitemap
     * @Ignore()
     */
    private Collection $sitemap;

    public function __construct()
    {
        $this->sitemap = new Collection();
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
     * @return $this
     */
    public function setLoc(string $loc): self
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
     * @return $this
     */
    public function setLastmod(string $lastmod): self
    {
        $this->lastmod = $lastmod;
        return $this;
    }

    public function getSitemap(): Collection
    {
        return $this->sitemap;
    }

    public function setSitemap(Collection $sitemap): self
    {
        $this->sitemap = $sitemap;
        return $this;
    }

    public function addSitemap(Sitemap $sitemap): self
    {
        $this->sitemap->add($sitemap);
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
     * @return $this
     */
    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }
}
