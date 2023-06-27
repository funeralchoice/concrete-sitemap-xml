<?php

namespace Concrete\Package\SitemapXml\Controller\SinglePage\Dashboard\System\Seo\SitemapXml;

use Application\Helpers\ServiceHelper;
use Application\Services\RedirectService;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Page\Page;
use Concrete\Package\SitemapXml\Entity\SitemapXml;
use Concrete\Package\SitemapXml\Form\SitemapXmlType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig\Environment;

/**
 * Class Manage
 * @package Concrete\Package\SitemapXml\Controller\SinglePage\Dashboard\System\Seo\SitemapXml
 */
class Manage extends DashboardPageController
{
    public const PARENT_CONTROLLER_PATH = '/dashboard/system/seo/sitemap_xml';
    private Environment $twig;
    private FormFactory $formFactory;
    private RedirectService $redirectService;
    private Session $session;
    private Repository $config;

    public function __construct(Page $c, Environment $twig, FormFactory $formFactory, RedirectService $redirectService, Session $session, Repository $config)
    {
        parent::__construct($c);
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->redirectService = $redirectService;
        $this->session = $session;
        $this->config = $config;
    }

    public function on_start(): void
    {
        parent::on_start();
        $this->set('baseUrl', $this->getControllerActionPath());
    }

    public function view(?int $id = null): void
    {
        /**
         * @var EntityManagerInterface $entityManager
         */
        $entityManager = $this->app->make(EntityManagerInterface::class);
        $flashbag = $this->session->getFlashBag();
        if ($id) {
            $sitemapIndex = $entityManager->getRepository(SitemapXml::class)->find($id);
            if ($sitemapIndex == null) {
                $flashbag->set('danger', 'Sitemap document ID #' . $id . ' not found');
                $this->redirectService->redirect(self::PARENT_CONTROLLER_PATH);
                return;
            }
        } else {
            $sitemapIndex = new SitemapXml();
        }
        $handlers = array_flip($this->config->get('app.sitemap_xml.handlers', []));
        $form = $this->formFactory->create(SitemapXmlType::class, $sitemapIndex, ['handlers' => $handlers]);
        $request = Request::createFromGlobals();
        $form->handleRequest($request);

        $data = $request->request->all();

        if ($request->getMethod() === 'POST' && isset($data['sitemap_xml']) && isset($data['sitemap_xml']['delete'])) {
            $entityManager->remove($sitemapIndex);
            $entityManager->flush();
            ServiceHelper::redirect('/dashboard/system/seo/sitemap_xml/');
            return;
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /**
             * @var SitemapXml $sitemapIndex
             */
            $sitemapIndex = $form->getData();
            $entityManager->persist($sitemapIndex);
            $entityManager->flush();
            $flashbag->set('success', 'Sitemap Index saved');
            $this->redirectService->redirect(self::PARENT_CONTROLLER_PATH . '/manage/' . $sitemapIndex->getId());
            return;
        }

        $viewTemplate = $this->twig->render('packages/sitemap_xml/single_pages/dashboard/system/seo/sitemap_xml/manage.html.twig', [
            'form' => $form->createView(),
            'sitemapIndex' => $sitemapIndex,
        ]);
        $this->set('viewTemplate', $viewTemplate);
    }
}
