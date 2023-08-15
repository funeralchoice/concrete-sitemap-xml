<?php

namespace Concrete\Package\SitemapXml\Page\Element;

use Symfony\Component\Serializer\Annotation\SerializedName;

class SitemapPageLink
{
    /**
     * @SerializedName("@rel")
     */
    private string $rel;
    /**
     * @SerializedName("@hreflang")
     */
    private string $hreflang;
    /**
     * @SerializedName("@href")
     */
    private string $href;

    public function getRel(): string
    {
        return $this->rel;
    }

    public function setRel(string $rel): self
    {
        $this->rel = $rel;
        return $this;
    }

    public function getHreflang(): string
    {
        return $this->hreflang;
    }

    public function setHreflang(string $hreflang): self
    {
        $this->hreflang = $hreflang;
        return $this;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function setHref(string $href): self
    {
        $this->href = $href;
        return $this;
    }
}
