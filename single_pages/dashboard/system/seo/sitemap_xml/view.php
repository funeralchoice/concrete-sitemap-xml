<?php
defined('C5_EXECUTE') or die(_("Access Denied."));
use Concrete\Core\View\View;
use Concrete\Core\Page\Page;
use Concrete\Core\Search\Pagination\Pagination;
use Concrete\Package\SitemapXml\Entity\SitemapXml;

assert(isset($baseUrl, $search, $form, $controller));
View::element('flash_messages', null, 'sitemap_xml');
echo $controller->get('viewTemplate');
