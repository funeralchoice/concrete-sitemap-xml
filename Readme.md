## Install

In your `.gitignore` add `service/packages/sitemap_xml`

In your `composer.json` file, add:

```json
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/rawnet/concrete-sitemap-xml"
  }
],
```

```json
"extra": {
  ...
  "installer-paths": {
    "packages/{$name}": ["type:concrete5-package"],
    "application/blocks/{$name}": ["type:concrete5-block"],
    "application/src/{$name}": ["type:concrete5-core"]
  }
}
```

Then run:

`composer require rawnet/concrete-sitemap-xml`

## Upgrading 
If you are upgrading to version two, your existing records need to be assigned to the correct site, it's most likely that you will need to run
update pkRawnetSitemapXml set site_locale_id = 1;

## Instructions

1. Ensure you have a folder created in called `sitemaps` in the service directory
2. Go to /dashboard/system/seo/sitemap_xml/ to create sitemap xml indexes
3. When creating a sitemap xml index you can choose a handler for when you want to add entities managed by doctrine or from an external api into the sitemap that are not c5 pages, this can be achieved by creating a custom handler as follows; 

```php
<?php

namespace Concrete\Package\SitemapXml\Handler;

use Application\Entity\Retailer;
use Application\Helpers\ServiceHelper;
use Concrete\Core\Page\Page;
use Concrete\Package\SitemapXml\Page\Element\SitemapPage;
use DateTime;

class EntityHandler extends AbstractHandler
{
    /**
     * @param Page $parent
     * @param SitemapPage[] $sitemapPages
     * @param callable|null $pulse
     * @return void
     */
    public function generate(Page $parent, array &$sitemapPages, ?callable $pulse = null): void
    {
        $retailers = ServiceHelper::entityManager()->getRepository(Retailer::class)->findAll();
        foreach ($retailers as $retailer) {
            $sitemapPage = (new SitemapPage())
                ->setLoc($this->sitemapGenerator->getPageLink($parent, [$retailer->getSlug()]))
                ->setLastmod($retailer->getUpdatedAt()?->format(DateTime::W3C) ?: '')
                ->setChangefreq($this->sitemapGenerator->getPageChangeFrequency(null))
                ->setPriority($this->sitemapGenerator->getPagePriority(null));
            $sitemapPages[] = $sitemapPage;
            if ($pulse !== null) {
                $pulse($sitemapPage);
            }
        }
    }
}
``` 

This call will require the generate function with the params listed above, the `$parent` will allow you to create the link to where it is in the sitemap, `$sitemapPages` will add this to the index of sitemap pages created and `$pulse` will allow the c5 job to keep a count of the pages added to the pages when using the task on the command line.  

Ensure that your handler class extends the Concrete\Package\SitemapXml\Handler\AbstractHandler class and is available in application/config/app.php so that this can be selected in the manage interface e.g.

```php
$appSettings = [
    'sitemap_xml' => [
        'handlers' => [
            \Concrete\Package\SitemapXml\Handler\EntityHandler::class => 'Entity Handler'
        ]
    ]
];
```
4. If you are using a multi site setup then you will need to add a route into your app

```php
use Concrete\Package\SitemapXml\Controller\SitemapXmlController;
public const SITEMAP_XML = '/sitemaps/sitemap.xml';

$this->routes[] = (new Route(self::SITEMAP_XML))
   ->setAction(SitemapXmlController::class . '::index')
   ->setMethods(Request::METHOD_GET); 
```   
