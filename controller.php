<?php

namespace Concrete\Package\SitemapXml;

use Concrete\Core\Command\Task\Manager;
use Concrete\Core\Package\Package;
use Concrete\Core\Page\Single as SinglePage;
use Concrete\Core\Support\Facade\Application as ApplicationFacade;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Entity\Package as PackageEntity;
use Concrete\Package\SitemapXml\Command\Task\Controller\GenerateSitemapController;
use Concrete\Package\SitemapXml\Console\Command\GenerateSitemapCommand;
use Concrete\Core\Console\Command\GenerateSitemapCommand as CoreGenerateSitemapCommand;

/**
 * Class Controller
 * @package Concrete\Package\SitemapXml
 */
class Controller extends Package
{
    protected string $pkgHandle = 'sitemap_xml';
    /** @var string */
    protected $appVersionRequired = '8.0.0';
    protected string $pkgVersion = '1.0.0';
    /** @var string[] */
    protected $pkgAutoloaderRegistries = ['src/' => '\Concrete\Package\SitemapXml'];
    protected PackageEntity $pkg;

    /**
     * @return string
     */
    public function getPackageName(): string
    {
        /**
         * @phpstan-ignore-next-line
         */
        return t('Sitemap XML');
    }

    /**
     * @return string
     */
    public function getPackageDescription(): string
    {
        /**
         * @phpstan-ignore-next-line
         */
        return t('Sitemap XML definitions');
    }

    public function on_start(): void
    {
        $this->registerTasks();
    }

    private function registerTasks(): void
    {
        /**
         * @var Manager $manager
         */
        $manager = $this->app->make(Manager::class);
        $manager->extend('generate_sitemap', function () {
            return $this->app->make(GenerateSitemapController::class);
        });

        // overwrite default command which is used on cli
        $this->app->bind(CoreGenerateSitemapCommand::class, function () {
            /**
             * @var GenerateSitemapCommand $sitemapCommand
             */
            $sitemapCommand = $this->app->make(GenerateSitemapCommand::class);
            return $sitemapCommand;
        });
    }

    /**
     * @return PackageEntity
     */
    public function install(): PackageEntity
    {
        $this->pkg = parent::install();
        $this->packageSetup();
        $this->installContentFile('tasks.xml');
        return $this->pkg;
    }

    public function upgrade(): void
    {
        $app = ApplicationFacade::getFacadeApplication();
        /**
         * @var PackageService $pkgService
         */
        $pkgService = $app->make(PackageService::class);
        $package = $pkgService->getByHandle($this->pkgHandle);
        if ($package) {
            $this->pkg = $package;
            $this->packageSetup();
        }
        $this->installContentFile('tasks.xml');
        parent::upgrade();
    }

    protected function packageSetup(): void
    {
        $page = SinglePage::add('/dashboard/system/seo/' . $this->pkgHandle, $this->pkg);
        /**
         * @phpstan-ignore-next-line
         */
        $page->updateCollectionName(t('Sitemap XML'));

        $page = SinglePage::add('/dashboard/system/seo/' . $this->pkgHandle . '/manage', $this->pkg);
        /**
         * @phpstan-ignore-next-line
         */
        $page->updateCollectionName(t('Sitemap XML manage'));
        $page->setAttribute('exclude_nav', 1);
    }
}
