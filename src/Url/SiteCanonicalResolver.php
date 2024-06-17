<?php

namespace Concrete\Package\SitemapXml\Url;


use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Entity\Site\Site;
use Concrete\Core\Http\Request;
use Concrete\Core\Page\Page;
use Concrete\Core\Url\Resolver\UrlResolverInterface;
use Concrete\Core\Url\Url;
use Concrete\Core\Url\UrlImmutable;
use League\Url\UrlInterface;

class SiteCanonicalResolver implements UrlResolverInterface
{

    private Site $site;

    /**
     * @var \Concrete\Core\Http\Request
     */
    protected $request;

    /**
     * @var \Concrete\Core\Application\Application
     */
    protected $app;

    /**
     * CanonicalUrlResolver constructor.
     *
     * @param \Concrete\Core\Application\Application $app
     * @param \Concrete\Core\Http\Request $request
     */
    public function __construct(Application $app, Request $request)
    {
        $this->app = $app;
        $this->request = $request;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function setSite(Site $site): self
    {
        $this->site = $site;
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Url\Resolver\UrlResolverInterface::resolve()
     */
    public function resolve(array $arguments, $resolved = null)
    {
        // Get the site from the current site tree
        $site = $this->site;

        // Determine trailing slash setting
        /**
         * @var Repository $config
         */
        $config = $this->app->make('config');
        $trailing_slashes = $config->get('concrete.seo.trailing_slash') ? Url::TRAILING_SLASHES_ENABLED : Url::TRAILING_SLASHES_DISABLED;

        $url = UrlImmutable::createFromUrl('', $trailing_slashes);

        if ($configUrl = $site->getSiteCanonicalURL()) {
            $requestScheme = strtolower($this->request->getScheme());

            $canonical = UrlImmutable::createFromUrl($configUrl, $trailing_slashes);

            $canonicalToUse = $canonical;

            if ($configUrlAlternative = $site->getSiteAlternativeCanonicalURL()) {
                $canonical_alternative = UrlImmutable::createFromUrl($configUrlAlternative, $trailing_slashes);
                if (
                    strtolower($canonical->getScheme()) !== $requestScheme &&
                    strtolower($canonical_alternative->getScheme()) === $requestScheme
                ) {
                    $canonicalToUse = $canonical_alternative;
                }
            }

            $url = $url->setScheme($canonicalToUse->getScheme());
            $url = $url->setHost($canonicalToUse->getHost());
            if ((int)$canonicalToUse->getPort()->get() > 0) {
                $url = $url->setPort($canonicalToUse->getPort());
            }
        } else {
            // This fallthrough is dangerous. Make sure that you define your canonical URL so that we don't have to guess!
            $host = $this->request->getHost();
            $scheme = $this->request->getScheme();
            if ($scheme && $host) {
                $url = $url->setScheme($scheme)
                    ->setHost($host);
                $port = $this->request->getPort();
                if ($port) {
                    $url->setPort($port);
                }
            }
        }

        /**
         * @var string|null $relative_path
         */
        $relative_path = $this->app['app_relative_path'];
        if ($relative_path) {
            $url = $url->setPath($relative_path);
        }

        return $url;
    }
}
