<?php

namespace Concrete\Package\SitemapXml\Command;

use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;
use Concrete\Package\SitemapXml\Page\Element\SitemapPage;
use Concrete\Package\SitemapXml\Page\Sitemap\SitemapWriter;

class GenerateSitemapCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    protected SitemapWriter $writer;

    /**
     * GenerateSitemapCommandHandler constructor.
     * @param SitemapWriter $writer
     */
    public function __construct(SitemapWriter $writer)
    {
        $this->writer = $writer;
    }

    public function __invoke(GenerateSitemapCommand $command): void
    {
        $numPages = 0;
        $this->writer->generate(function (SitemapPage $data) use (&$numPages) {
            ++$numPages;
        });
        $sitemapUrl = $this->writer->getSitemapUrl();
        /**
         * @phpstan-ignore-next-line
         */
        $this->output->write(t('Sitemap URL available at: %s', $sitemapUrl));
    }
}
