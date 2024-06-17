<?php

namespace Concrete\Package\SitemapXml\Entity;

use Concrete\Core\Entity\Site\Locale;
use Concrete\Core\Page\Page;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="\Concrete\Package\SitemapXml\Repository\SitemapXmlRepository")
 * @ORM\Table(name="pkRawnetSitemapXml")
 * @UniqueEntity("fileName", message="Filename already exists", service="\Application\Bridge\Validator\UniqueEntityValidator")
 */
class SitemapXml
{
    /**
     * @var int|null
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(name="id", type="integer")
     */
    protected ?int $id = null;

    /**
     * @ORM\Column(type="string", nullable=false, unique=true)
     * @Assert\NotBlank()
     * @Assert\Regex(
     *     pattern="/\.xml$/",
     *     message="The file name must end with .xml"
     * )
     */
    protected string $fileName;

    /**
     * @ORM\Column(type="integer", nullable=false)
     * @Assert\GreaterThanOrEqual(value = 1, message = "You need to select a page.")
     */
    protected int $pageId;

    /**
     * @ORM\Column(type="string", nullable=false)
     * @Assert\NotBlank()
     */
    protected string $title;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    protected int $limitPerFile = 50000;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var class-string|null $entity
     */
    protected ?string $handler = null;

    /**
     * @ORM\Column(type="integer", nullable=true, options={"default"=1})
     * @deprecated
     */
    protected int $site;

    /**
     * @ORM\ManyToOne(targetEntity="\Concrete\Core\Entity\Site\Locale")
     * @ORM\JoinColumn(name="site_locale_id", referencedColumnName="siteLocaleID", nullable=true)
     */
    private ?Locale $locale = null;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     * @return SitemapXml
     */
    public function setId(?int $id): SitemapXml
    {
        $this->id = $id;
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
     * @return SitemapXml
     */
    public function setFileName(string $fileName): SitemapXml
    {
        $this->fileName = $fileName;
        return $this;
    }

    /**
     * @return int
     */
    public function getPageId(): int
    {
        return $this->pageId;
    }

    /**
     * @param int $pageId
     * @return SitemapXml
     */
    public function setPageId(int $pageId): SitemapXml
    {
        $this->pageId = $pageId;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return SitemapXml
     */
    public function setTitle(string $title): SitemapXml
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return int
     */
    public function getLimitPerFile(): int
    {
        return $this->limitPerFile;
    }

    /**
     * @param int $limitPerFile
     * @return SitemapXml
     */
    public function setLimitPerFile(int $limitPerFile): SitemapXml
    {
        $this->limitPerFile = $limitPerFile;
        return $this;
    }

    /**
     * @return class-string|null
     */
    public function getHandler(): ?string
    {
        /**
         * @phpstan-ignore-next-line
         */
        return $this->handler;
    }

    /**
     * @param class-string|null $handler
     * @return SitemapXml
     */
    public function setHandler(?string $handler): SitemapXml
    {
        $this->handler = $handler;
        return $this;
    }

    public function getSite(): int
    {
        return $this->site;
    }

    public function setSite(int $site): self
    {
        $this->site = $site;
        return $this;
    }

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }

    public function setLocale(?Locale $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getPagePath(): string
    {
        return Page::getByID($this->pageId)->getCollectionPath();
    }
}
