<?php

namespace Concrete\Package\SitemapXml\Page\Sitemap;

use Concrete\Core\Cache\Cache;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Entity\Site\Locale;
use Concrete\Core\Entity\Site\Site;
use Concrete\Core\Entity\Site\Tree;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Logging\LoggerFactory;
use Concrete\Core\Page\Page;
use Concrete\Core\Site\Service;
use Concrete\Core\Url\Resolver\Manager\ResolverManager;
use Concrete\Core\Url\UrlImmutable;
use Concrete\Package\SitemapXml\Entity\SitemapXml;
use Concrete\Package\SitemapXml\Page\Element\MultilingualSitemapIndex;
use Concrete\Package\SitemapXml\Page\Element\MultilingualSitemapIndexes;
use Concrete\Package\SitemapXml\Url\SitePathUrlResolver;
use Doctrine\ORM\EntityManager;
use Illuminate\Filesystem\Filesystem;
use DateTime;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Serializer;
use Concrete\Package\SitemapXml\Page\Element\Sitemap;
use Concrete\Package\SitemapXml\Page\Element\SitemapIndex;

class SitemapWriter
{
    protected Filesystem $filesystem;
    private SitemapGenerator $sitemapGenerator;
    private EntityManager $entityManager;
    private Serializer $serializer;
    private ResolverManager $urlResolver;
    private Repository $config;
    private LoggerInterface $log;
    private Service $siteService;
    private bool $isMultiSite = false;
    /**
     * @var Site[] $sites
     */
    private array $sites;
    private Site $currentSite;
    private SitePathUrlResolver $siteResolver;

    public function __construct(Filesystem $filesystem, SitemapGenerator $sitemapGenerator, EntityManager $entityManager, Serializer $serializer, ResolverManager $urlResolver, Repository $config, LoggerFactory $log, Service $siteService, SitePathUrlResolver $siteResolver)
    {
        $this->filesystem = $filesystem;
        $this->sitemapGenerator = $sitemapGenerator;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->urlResolver = $urlResolver;
        $this->config = $config;
        $this->log = $log->createLogger(Channels::CHANNEL_EXCEPTIONS);
        $this->siteService = $siteService;
        $this->siteResolver = $siteResolver;
    }

    /**
     * Generate the sitemap
     * @param callable|null $pulse a callback function to be called every time a new sitemap element will be processed
     */
    public function generate(?callable $pulse = null): void
    {
        try {
            $this->checkOutputFileName();
            Cache::disableAll();
            $now = (new DateTime())->format(DateTime::W3C);
            $this->sites = $this->siteService->getList();
            $this->isMultiSite = count($this->sites) > 1;
            foreach ($this->sites as $site) {

                $this->currentSite = $site;
                $siteHandle = $site->getSiteHandle();

                $this->setSiteResolver($site);
                $this->sitemapGenerator->setSite($site);
                $this->sitemapGenerator->getPageListGenerator()->setSite($site);

                $indexes = $this->getSitemapIndexForSite($site);

                // is site multilingual...
                $isMultilingual = $site->getLocales()->count() > 1;

                $sitemapIndex = new SitemapIndex();
                $indexCollection = new Collection($indexes);


                if ($isMultilingual) {
                    $this->sitemapGenerator->setIsMultilingual(true);

                    $multiLingualSitemapIndexes = new MultilingualSitemapIndexes();
                    foreach ($site->getLocales() as $locale) {
                        /**
                         * @var Collection<SitemapXml> $sitemaps
                         */
                        $sitemaps = $indexCollection->filter(function (SitemapXml $xml) use ($locale) {
                            return $xml->getLocale() && $xml->getLocale()->getLocaleID() === $locale->getLocaleID();
                        });

                        /**
                         * @var Tree $tree
                         */
                        $tree = $locale->getSiteTreeObject();
                        /**
                         * @var Page $section
                         */
                        $section = $tree->getSiteHomePageObject();

                        $multiLingualSitemapIndex = new MultilingualSitemapIndex();

                        $filename = "$siteHandle-sitemap-{$section->getCollectionHandle()}.xml";
                        $multiLingualSitemapIndex->setLoc($this->getLink([$this->getSitemapBasePath(), $filename]))
                            ->setLastmod($now)
                            ->setFileName($filename);

                        // check if indexes have been created
                        if ($sitemaps->count()) {
                            foreach ($sitemaps as $index) {
                                $this->generateSitemap($multiLingualSitemapIndex, $index, $now, $pulse);
                            }
                            $this->generateGeneralSitemap($multiLingualSitemapIndex, $section, $now, $site, $pulse);
                            $multiLingualSitemapIndexes->addSitemap($multiLingualSitemapIndex);
                        } else {
                            // no indexes created, just generate a general sitemap for the locale with all pages
                            $this->generateGeneralSitemap($multiLingualSitemapIndex, $section, $now, $site, $pulse);
                            $multiLingualSitemapIndexes->addSitemap($multiLingualSitemapIndex);
                        }
                    }

                    $this->writeMultiLingualSitemaps($multiLingualSitemapIndexes);
                } else {
                    // site is not multilingual
                    /**
                     * @var Locale $locale
                     */
                    $locale = $site->getDefaultLocale();
                    /**
                     * @var Tree $tree
                     */
                    $tree = $locale->getSiteTreeObject();
                    /**
                     * @var Page $section
                     */
                    $section = $tree->getSiteHomePageObject();

                    $sitemaps = $indexCollection->filter(function (SitemapXml $xml) use ($locale) {
                        return $xml->getLocale() && $xml->getLocale()->getLocaleID() === $locale->getLocaleID();
                    });

                    if ($sitemaps->count()) {
                        $this->generateFromIndexes($sitemapIndex, $sitemaps->toArray(), $now, $pulse);
                    }


                    $this->generateGeneralSitemap($sitemapIndex, $section, $now, $site, $pulse);
                    $this->writeFiles($sitemapIndex);
                }
            }
        } catch (\Exception $exception) {
            $this->log->error($exception->getMessage());
        } finally {
            Cache::enableAll();
        }
    }


    public function setSiteResolver(Site $site): void
    {
        $this->siteResolver->setSite($site);
        $this->urlResolver->addResolver('sitemapxml.site', $this->siteResolver, 1024);
    }

    /**
     * @param Site $site
     * @return SitemapXml[]
     * @throws \Doctrine\ORM\Exception\NotSupported
     */
    private function getSitemapIndexForSite(Site $site): array
    {
        $qb = $this->entityManager->getRepository(SitemapXml::class)->createQueryBuilder('si');
        $qb
            ->addSelect('l')
            ->addSelect('s')
            ->leftJoin('si.locale', 'l')
            ->leftJoin('l.site', 's');
        $qb->where('l.site = :site')
            ->setParameter('site', $site);

        /**
         * @var SitemapXml[] $indexes
         */
        $indexes = $qb->getQuery()->execute();

        return $indexes;
    }


    private function writeMultiLingualSitemaps(MultilingualSitemapIndexes $multilingualSitemapIndexes): void
    {

        $xmlIndex = $this->serializer->serialize(
            [
                '@xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
                'sitemap' => $multilingualSitemapIndexes->getSitemaps()
            ],
            'xml',
            [
                'xml_root_node_name' => 'sitemapindex',
                'xml_format_output' => true,
            ]
        );
        /**
         * @phpstan-ignore-next-line
         */
        $this->filesystem->put(DIR_BASE . '/' . $this->getSitemapFileName(), $xmlIndex);

        foreach ($multilingualSitemapIndexes->getSitemaps() as $multilingualSitemapIndex) {
            $xmlIndex = $this->serializer->serialize(
                [
                    '@xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
                    'sitemap' => $multilingualSitemapIndex->getSitemap()->toArray()
                ],
                'xml',
                [
                    'xml_root_node_name' => 'sitemapindex',
                    'xml_format_output' => true,
                ]
            );
            /**
             * @phpstan-ignore-next-line
             */
            $this->filesystem->put(DIR_BASE . '/' . $this->getSitemapBasePath() . "/{$multilingualSitemapIndex->getFileName()}", $xmlIndex);
            /**
             * @var Sitemap $sitemapIndex
             */
            foreach ($multilingualSitemapIndex->getSitemap() as $sitemapIndex) {
                /**
                 * @var array<array<string|array<string>>> $pages
                 */
                $pages = $this->serializer->normalize($sitemapIndex->getPages()->toArray(), 'array');

                $xml = $this->serializer->serialize(
                    [
                        '@xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
                        '@xmlns:x' => 'http://www.w3.org/1999/xhtml',
                        '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                        '@xmlns:xhtml' => 'http://www.w3.org/1999/xhtml',
                        '@xsi:schemaLocation' => 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.w3.org/TR/xhtml11/xhtml11_schema.html http://www.w3.org/2002/08/xhtml/xhtml1-strict.xsd',
                        'url' => $pages
                    ],
                    'xml',
                    [
                        'xml_root_node_name' => 'urlset',
                        'xml_format_output' => true,
                    ]
                );
                /**
                 * @phpstan-ignore-next-line
                 */
                $this->filesystem->put(DIR_BASE . '/' . $this->getSitemapBasePath() . "/{$sitemapIndex->getFileName()}", $xml);
            }
        }
    }

    /**
     * @param SitemapIndex $sitemapIndex
     * @param array<SitemapXml> $indexes
     * @param string $now
     * @param callable|null $pulse
     * @return void
     */
    private function generateFromIndexes(SitemapIndex $sitemapIndex, array $indexes, string $now, ?callable $pulse): void
    {
        foreach ($indexes as $index) {
            $this->generateSitemap($sitemapIndex, $index, $now, $pulse);
        }
    }

    private function generateSitemap(SitemapIndex|MultilingualSitemapIndex $sitemapIndex, SitemapXml $index, string $now, ?callable $pulse): void
    {
        if ($this->isMultiSite) {
            $fileName = "{$this->currentSite->getSiteHandle()}-{$index->getFileName()}";
        } else {
            $fileName = $index->getFileName();
        }
        $sitemap = new Sitemap();
        $sitemap->setLoc($this->getLink([$this->getSitemapBasePath(), $fileName]));
        $sitemap->setLastmod($now);
        $sitemap->setFileName($fileName);
        $pages = $this->sitemapGenerator->generatePages($index, $pulse);
        $sitemap->setPages($pages);
        if ($sitemap->getPages()->count() > $index->getLimitPerFile()) {
            // if it is more than the amount spilt into smaller sitemap indexes
            $this->spiltSitemaps($sitemapIndex, $sitemap, $index->getLimitPerFile(), $fileName, $now);
        } else {
            $sitemapIndex->addSitemap($sitemap);
        }
    }

    private function generateGeneralSitemap(SitemapIndex|MultilingualSitemapIndex $sitemapIndex, Page $section, string $now, ?Site $site = null, ?callable $pulse = null): void
    {
        $siteHandlePrefix = $site ? $site->getSiteHandle() . '-' : '';
        $filename = sprintf("%sgeneral%s.xml", $siteHandlePrefix, $section->getCollectionHandle() ? '-' . $section->getCollectionHandle() : '');
        $sitemap = new Sitemap();
        $sitemap->setLoc($this->getLink([$this->getSitemapBasePath(), $filename]));
        $sitemap->setLastmod($now);
        $sitemap->setFileName($filename);
        $pages = $this->sitemapGenerator->getGeneralPages($section, $pulse);
        $sitemap->setPages($pages);
        if ($sitemap->getPages()->count() > 50000) {
            $this->spiltSitemaps($sitemapIndex, $sitemap, 50000, $filename, $now);
        } else {
            $sitemapIndex->addSitemap($sitemap);
        }
    }

    private function spiltSitemaps(SitemapIndex|MultilingualSitemapIndex &$sitemapIndex, Sitemap &$sitemap, int $split, string $filename, string $now): void
    {
        $pages = $sitemap->getPages()->chunk($split);
        /**
         * @var Collection $firstResult
         */
        $firstResult = $pages->shift();
        $sitemap->setPages($firstResult->toArray());
        $pages->each(function ($sitemapPages, $key) use ($sitemapIndex, $filename, $now) {
            $sitemap = new Sitemap();
            $newFileName = $this->filesystem->name($filename) . '-' . ($key + 1) . '.' . $this->filesystem->extension($filename);
            $sitemap->setFileName($newFileName);
            $sitemap->setLoc($this->getLink([$this->getSitemapBasePath(), $sitemap->getFileName()]));
            $sitemap->setLastmod($now);
            $sitemap->setPages($sitemapPages->toArray());
            $sitemapIndex->addSitemap($sitemap);
        });
    }

    /**
     * @param SitemapIndex $sitemap
     * @return void
     */
    private function writeFiles(SitemapIndex $sitemap): void
    {
        $xmlIndex = $this->serializer->serialize(
            [
                '@xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
                'sitemap' => $sitemap->getSitemaps()
            ],
            'xml',
            [
                'xml_root_node_name' => 'sitemapindex',
                'xml_format_output' => true,
            ]
        );
        /**
         * @phpstan-ignore-next-line
         */
        $this->filesystem->put(DIR_BASE . '/' . $this->getSitemapFileName(), $xmlIndex);
        foreach ($sitemap->getSitemaps() as $sitemapIndex) {
            $xml = $this->serializer->serialize(
                [
                    '@xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
                    'url' => $sitemapIndex->getPages()->toArray()
                ],
                'xml',
                [
                    'xml_root_node_name' => 'urlset',
                    'xml_format_output' => true,
                ]
            );
            /**
             * @phpstan-ignore-next-line
             */
            $this->filesystem->put(DIR_BASE . '/' . $this->getSitemapBasePath() . "/{$sitemapIndex->getFileName()}", $xml);
        }
    }

    public function getSitemapFileName(): string
    {
        if ($this->isMultiSite) {
            $sitemapXmlFile = "sitemaps/{$this->currentSite->getSiteHandle()}-sitemap.xml";
        } else {
            /**
             * @var string $sitemapXmlFile
             */
            $sitemapXmlFile = $this->config->get('concrete.sitemap_xml.file');
        }
        return $sitemapXmlFile;
    }

    private function getSitemapBasePath(): string
    {
        $filename = $this->getSitemapFileName();
        $dirName = $this->filesystem->dirname($filename);
        if ($dirName !== '.') {
            return $dirName;
        }
        return '';
    }

    private function checkOutputFileName(): void
    {
        $sitemapXmlFile = $this->getSitemapFileName();
        $directoryName = $this->filesystem->dirname($sitemapXmlFile);
        /**
         * @phpstan-ignore-next-line
         */
        if ($this->filesystem->isDirectory(DIR_BASE . '/' . $directoryName) === false) {
            throw new UserMessageException(sprintf('The directory containing %s does not exist', $directoryName));
        }
        /**
         * @phpstan-ignore-next-line
         */
        if ($this->filesystem->isWritable(DIR_BASE . '/' . $directoryName) === false) {
            throw new UserMessageException(sprintf('The file %s is not writable', $sitemapXmlFile));
        }
    }

    /**
     * @param array<string|int> $params
     * @return string
     */
    public function getLink(array $params = []): string
    {
        /** @var UrlImmutable $resolved */
        $resolved = $this->urlResolver->resolve($params);
        $url = (string)$resolved;
        if (preg_match('/\.[a-z0-9]{2,4}\/$/i', $url) === 1) {
            $url = trim($url, '/');
        }
        return $url;
    }

    public function getSitemapUrl(): string
    {
        return $this->getLink([$this->getSitemapFileName()]);
    }

    /**
     * @return SitemapGenerator
     */
    public function getSitemapGenerator(): SitemapGenerator
    {
        return $this->sitemapGenerator;
    }
}
