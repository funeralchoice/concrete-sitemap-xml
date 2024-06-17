<?php

namespace Concrete\Package\SitemapXml\Console\Command;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Console\Command;
use Concrete\Core\Site\Service;
use Concrete\Package\SitemapXml\Page\Element\SitemapPage;
use Concrete\Package\SitemapXml\Page\Sitemap\SitemapWriter;
use Concrete\Core\Support\Facade\Application;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSitemapCommand extends Command
{
    protected function configure(): void
    {
        $okExitCode = static::SUCCESS;
        $errExitCode = static::FAILURE;
        $this
            ->setName('c5:sitemap:generate')
            ->setDescription('Generate the sitemap index in XML format.')
            ->addEnvOption()
            ->setCanRunAsRoot(true);
        $this->setHelp(<<<EOT
Returns codes:
  {$okExitCode} operation completed successfully
  {$errExitCode} errors occurred
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = Application::getFacadeApplication();
        /**
         * @var SitemapWriter $writer
         */
        $writer = $app->make(SitemapWriter::class);
        $generator = $writer->getSitemapGenerator();
        $progressBar = new ProgressBar($output, $generator->getPageListGenerator()->getApproximatePageCount());
        $progressBar->setMessage('Adding pages to sitemap');
        $progressBar->display();
        $numPages = 0;
        $writer->generate(function (SitemapPage $element) use ($progressBar, &$numPages) {
            $progressBar->advance();
            ++$numPages;
        });
        $progressBar->clear();
        $output->writeln('');

        /**
         * @var Service $siteService
         */
        $siteService = $app->make(Service::class);
        /**
         * @var Repository $config
         */
        $config = $app->make(Repository::class);
        $sites = $siteService->getList();
        /**
         * @var string $sitemapXmlFile
         */
        $sitemapXmlFile = $config->get('concrete.sitemap_xml.file');
        foreach ($sites as $site) {
            $writer->setSiteResolver($site);
            $output->writeln(sprintf('Sitemap visible at: %s', $writer->getLink([$sitemapXmlFile])));
        }

        $output->writeln(sprintf('Number of pages included in sitemap: %s', $numPages));
        return static::SUCCESS;
    }
}
