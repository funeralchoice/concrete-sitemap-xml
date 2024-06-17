<?php

namespace Concrete\Package\SitemapXml\Controller\SinglePage\Dashboard\System\Seo;

use Concrete\Core\Http\Request;
use Concrete\Core\Page\Controller\DashboardSitePageController;
use Concrete\Core\Page\Page;
use Concrete\Package\SitemapXml\Entity\SitemapXml as SitemapXmlEntity;
use Concrete\Package\SitemapXml\Form\SitemapXmlSearchType;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Form\FormFactory;
use Twig\Environment;

/**
 * Class SitemapXml
 * @package Concrete\Package\SitemapXml\Controller\SinglePage\Dashboard\System\Seo\SitemapXml
 */
class SitemapXml extends DashboardSitePageController
{

    public function __construct(Page $c, private readonly Environment $twig, private readonly FormFactory $formFactory)
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
        /**
         * @phpstan-ignore-next-line
         */
        $repo = $this->entityManager->getRepository(SitemapXmlEntity::class);

        $request = Request::getInstance();
        $form = $this->formFactory->create(SitemapXmlSearchType::class);
        $form->handleRequest($request);

        /**
         * @var array<array-key, string> $data
         */
        $data = $form->getData();

        if (!isset($data['locale'])) {
            $data['locale'] = $this->site->getDefaultLocale();
        }

        $sitemaps = $repo->createQueryBuilder('e');
        $sitemaps->andWhere('e.locale = :locale')
            ->setParameter('locale', $data['locale']);

        if ($search = $request->query->get('search')) {
            $sitemaps
                ->andWhere('e.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        $sitemaps->orderBy('e.title, e.title');

        $pagination = new Pagerfanta(new QueryAdapter($sitemaps->getQuery()));
        $pagination->setMaxPerPage(10);
        $pagination->setCurrentPage($request->query->getInt('page', 1));

        $viewTemplate = $this->twig->render('packages/sitemap_xml/single_pages/dashboard/system/seo/sitemap_xml/view.html.twig', [
            'sitemaps' => $pagination->getCurrentPageResults(),
            'pagination' => $pagination,
            'search' => $search,
            'form' => $form->createView()
        ]);
        $this->set('viewTemplate', $viewTemplate);
    }
}
