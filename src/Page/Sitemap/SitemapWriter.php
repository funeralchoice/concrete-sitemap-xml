<?php

namespace Concrete\Package\SitemapXml\Page\Sitemap;

use Concrete\Core\Cache\Cache;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Logging\LoggerFactory;
use Concrete\Core\Url\Resolver\Manager\ResolverManager;
use Concrete\Core\Url\UrlImmutable;
use Concrete\Package\SitemapXml\Entity\SitemapXml;
use Doctrine\ORM\EntityManager;
use Illuminate\Filesystem\Filesystem;
use DateTime;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Concrete\Package\SitemapXml\Page\Element\Sitemap;
use Concrete\Package\SitemapXml\Page\Element\SitemapIndex;

class SitemapWriter
{
    protected Filesystem $filesystem;
    private SitemapGenerator $sitemapGenerator;
    private EntityManager $entityManager;
    private SerializerInterface $serializer;
    private ResolverManager $urlResolver;
    private Repository $config;
    private LoggerInterface $log;

    public function __construct(Filesystem $filesystem, SitemapGenerator $sitemapGenerator, EntityManager $entityManager, SerializerInterface $serializer, ResolverManager $urlResolver, Repository $config, LoggerFactory $log)
    {
        $this->filesystem = $filesystem;
        $this->sitemapGenerator = $sitemapGenerator;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->urlResolver = $urlResolver;
        $this->config = $config;
        $this->log = $log->createLogger(Channels::CHANNEL_EXCEPTIONS);
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
            $indexes = $this->entityManager->getRepository(SitemapXml::class)->findAll();
            $now = (new DateTime())->format(DateTime::W3C);
            $sitemapIndex = new SitemapIndex();

            foreach ($indexes as $index) {
                $sitemap = new Sitemap();
                $sitemap->setLoc($this->getLink([$this->getSitemapBasePath(), $index->getFileName()]));
                $sitemap->setLastmod($now);
                $sitemap->setFileName($index->getFileName());
                $pages = $this->sitemapGenerator->generatePages($index, $pulse);
                $sitemap->setPages($pages);
                if ($sitemap->getPages()->count() > $index->getLimitPerFile()) {
                    // if it is more than the amount spilt into smaller sitemap indexes
                    $this->spiltSitemaps($sitemapIndex, $sitemap, $index->getLimitPerFile(), $index->getFilename(), $now);
                } else {
                    $sitemapIndex->addSitemap($sitemap);
                }
            }

            $sitemap = new Sitemap();
            $sitemap->setLoc($this->getLink([$this->getSitemapBasePath(), 'general.xml']));
            $sitemap->setLastmod($now);
            $sitemap->setFileName('general.xml');
            $pages = $this->sitemapGenerator->getGeneralPages($pulse);
            $sitemap->setPages($pages);
            if ($sitemap->getPages()->count() > 50000) {
                $this->spiltSitemaps($sitemapIndex, $sitemap, 50000, 'general.xml', $now);
            } else {
                $sitemapIndex->addSitemap($sitemap);
            }

            $this->writeFiles($sitemapIndex);
        } catch (\Exception $exception) {
            $this->log->error($exception->getMessage());
        } finally {
            Cache::enableAll();
        }
    }

    private function spiltSitemaps(SitemapIndex &$sitemapIndex, Sitemap &$sitemap, int $split, string $filename, string $now): void
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
        /**
         * @var string $sitemapXmlFile
         */
        $sitemapXmlFile = $this->config->get('concrete.sitemap_xml.file');
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
    private function getLink(array $params = []): string
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
