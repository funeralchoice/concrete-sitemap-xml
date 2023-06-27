<?php

namespace Concrete\Package\SitemapXml\Controller\SinglePage\Dashboard\System\Seo;

use Concrete\Core\Http\Request;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Page\Page;
use Concrete\Package\SitemapXml\Entity\SitemapXml as SitemapXmlEntity;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Twig\Environment;

/**
 * Class SitemapXml
 * @package Concrete\Package\SitemapXml\Controller\SinglePage\Dashboard\System\Seo\SitemapXml
 */
class SitemapXml extends DashboardPageController
{

    public function __construct(Page $c, private readonly Environment $twig)
    {
        parent::__construct($c);
    }

    public function on_start(): void
    {
        parent::on_start();
        $this->set('baseUrl', $this->getControllerActionPath());
    }

    public function view(): void
    {
        $repo = $this->entityManager->getRepository(SitemapXmlEntity::class);
        $sitemaps = $repo->createQueryBuilder('e');

        $request = Request::getInstance();
        if ($search = $request->query->get('search')) {
            $sitemaps
                ->where('e.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        $sitemaps->orderBy('e.title, e.title');

        $pagination = new Pagerfanta(new QueryAdapter($sitemaps->getQuery()));
        $pagination->setMaxPerPage(5);
        $pagination->setCurrentPage($request->query->getInt('ccm_paging_p', 1));

        $viewTemplate = $this->twig->render('packages/sitemap_xml/single_pages/dashboard/system/seo/sitemap_xml/view.html.twig', [
            'sitemaps' => $pagination->getCurrentPageResults(),
            'pagination' => $pagination,
            'search' => $search
        ]);
        $this->set('viewTemplate', $viewTemplate);
    }
}
