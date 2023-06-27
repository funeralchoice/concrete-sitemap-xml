<?php

namespace Concrete\Package\SitemapXml\Page\Sitemap;

use Concrete\Core\Application\Service\Dashboard;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Entity\Site\Site;
use Concrete\Core\Page\PageList;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Access\Entity\GroupEntity as GroupPermissionAccessEntity;
use Concrete\Core\Permission\Key\Key as PermissionKey;
use Concrete\Core\User\Group\Group;
use Concrete\Core\User\Group\GroupRepository;
use DateTime;
use Concrete\Core\Site\Service as SiteService;

class PageListGenerator
{
    private Dashboard $dashboard;
    private ?PermissionKey $permissionViewPage;
    private ?Group $guestGroup;
    /**
     * @var Group[] $guestGroupAE
     */
    private array $guestGroupAE = [];
    private DateTime $now;
    private Connection $connection;
    private SiteService $siteService;
    private ?Site $site = null;

    public function __construct(Dashboard $dashboard, GroupRepository $groupRepository, Connection $connection, SiteService $siteService)
    {
        $this->dashboard = $dashboard;
        $this->permissionViewPage = PermissionKey::getByHandle('view_page');
        /**
         * @phpstan-ignore-next-line
         */
        $this->guestGroup = $groupRepository->getGroupByID(GUEST_GROUP_ID);
        if ($this->guestGroup) {
            $this->guestGroupAE = [GroupPermissionAccessEntity::getOrCreate($this->guestGroup)];
        }
        $this->now = new DateTime();
        $this->connection = $connection;
        $this->siteService = $siteService;
    }

    /**
     * @param Page $page
     * @return Page[]
     */
    public function getPagesByParent(Page $page): array
    {
        $pageList = new PageList();
        $pageList->ignorePermissions();
        if ($page->getCollectionID()) {
            $pageList->filterByParentID($page->getCollectionID());
        }
        /**
         * @var Page[] $pages
         */
        $pages = $pageList->getResults();
        return $pages;
    }

    /**
     * @param array<int> $excludeIds
     * @return Page[]
     */
    public function getGeneralPages(array $excludeIds = []): array
    {
        $pageList = new PageList();
        $pageList->ignorePermissions();
        $qb = $pageList->getQueryObject();
        if (count($excludeIds)) {
            $qb->andWhere($qb->expr()->notIn('p.cID', $excludeIds));
        }
        /**
         * @var Page[] $pages
         */
        $pages = $pageList->getResults();
        return $pages;
    }

    public function canIncludePageInSitemap(Page $page): bool
    {
        if ($page->isSystemPage()) {
            return false;
        }
        if ($page->isExternalLink()) {
            return false;
        }
        if ($this->dashboard->inDashboard($page)) {
            return false;
        }
        if ($page->isInTrash()) {
            return false;
        }
        $pageVersion = $page->getVersionObject();
        if ($pageVersion && !$pageVersion->isApproved()) {
            return false;
        }
        $pubDate = new DateTime($page->getCollectionDatePublic());
        if ($pubDate > $this->now) {
            return false;
        }
        if ($page->getAttribute('exclude_sitemapxml')) {
            return false;
        }
        if ($this->permissionViewPage) {
            $this->permissionViewPage->setPermissionObject($page);
            $pa = $this->permissionViewPage->getPermissionAccessObject();
            if (!is_object($pa)) {
                return false;
            }
            /**
             * @phpstan-ignore-next-line
             */
            if (!$pa->validateAccessEntities($this->guestGroupAE)) {
                return false;
            }
        }
        return true;
    }

    public function getApproximatePageCount(): int
    {
        $siteTreeIDList = array_merge([0], $this->getSiteTreesIDList());
        /**
         * @var int|null $result
         */
        $result = $this->connection->fetchOne('select count(*) from Pages where siteTreeID is null or siteTreeID in (' . implode(', ', $siteTreeIDList) . ')');

        return $result ?: 0;
    }

    /**
     * @return int[]
     */
    protected function getSiteTreesIDList(): array
    {
        $result = [];
        $site = $this->getSite();
        if ($site !== null) {
            foreach ($site->getLocales() as $siteLocale) {
                $siteTreeID = $siteLocale->getSiteTreeID();
                if ($siteTreeID) {
                    $result[] = $siteTreeID;
                }
            }
        }

        return $result;
    }

    public function getSite(): ?Site
    {
        if ($this->site === null) {
            $this->site = $this->siteService->getDefault();
        }
        return $this->site;
    }
}
