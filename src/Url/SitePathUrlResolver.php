<?php

namespace Concrete\Package\SitemapXml\Url;

use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\ApplicationAwareTrait;
use Concrete\Core\Application\Service\Dashboard;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Entity\Site\Site;
use Concrete\Core\Page\Page;
use Concrete\Core\Url\Components\Path;
use Concrete\Core\Url\Resolver\UrlResolverInterface;
use League\Url\Url;
use League\Url\UrlInterface;

class SitePathUrlResolver implements UrlResolverInterface, ApplicationAwareInterface
{
    use ApplicationAwareTrait;

    private Site $site;

    public function __construct(protected readonly Repository $config, protected readonly SiteCanonicalResolver $canonical, protected readonly Dashboard $dashboard)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Url\Resolver\UrlResolverInterface::resolve()
     */
    public function resolve(array $arguments, $resolved = null): ?UrlInterface
    {
        if ($resolved) {
            // We don't need to do any post processing on urls.
            return $resolved;
        }

        $page = null;
        foreach ($arguments as $key => $argument) {
            if ($argument instanceof Page) {
                $page = $argument;
                unset($arguments[$key]);
                break;
            }
        }

        /**
         * @var array<string|int|array<int|string>> $args
         */
        $args = $arguments;
        /**
         * @var string|null|object $path
         */
        $path = array_shift($args);

        if (is_scalar($path) || (is_object($path) && method_exists($path, '__toString'))) {
            $path = (string)$path;
            $path = rtrim($path, '/');
            $this->canonical->setSite($this->site);
            /**
             * @var UrlInterface $url
             */
            $url = $this->canonical->resolve([$page]);
            $url = $this->handlePath($url, $path, $args);

            return $url;
        }

        return null;
    }

    /**
     * @param UrlInterface $url
     * @param string $path
     * @param array<string|int|array<string|int>> $args
     *
     * @return UrlInterface
     */
    protected function handlePath(UrlInterface $url, string $path, array $args): UrlInterface
    {
        $path_object = $this->basePath($url, $path, $args);

        /**
         * @var array<string|int> $components
         */
        $components = parse_url($path);

        $reset = false;
        // Were we passed a built URL? If so, just return it.
        if ($string = array_get($components, 'scheme')) {
            try {
                $url = Url::createFromUrl($path);
                $path_object = $url->getPath();
                $reset = true;
            } catch (\Exception $e) {
            }
        }

        if (!$reset) {
            if ($string = array_get($components, 'path')) {
                /**
                 * @phpstan-ignore-next-line
                 */
                $path_object->append($string);
            }
            if ($string = array_get($components, 'query')) {
                /**
                 * @phpstan-ignore-next-line
                 */
                $url = $url->setQuery($string);
            }
            if ($string = array_get($components, 'fragment')) {
                /**
                 * @phpstan-ignore-next-line
                 */
                $url = $url->setFragment($string);
            }
        }

        foreach ($args as $segment) {
            if (!is_array($segment)) {
                $segment = (string)$segment; // sometimes integers foul this up when we pass them in as URL arguments.
            }
            $path_object->append($segment);
        }

        if (!$reset) {
            $url_path = $url->getPath();
            $url_path->append($path_object);
        } else {
            $url_path = $path_object;
        }

        return $url->setPath($url_path);
    }

    /**
     * @param UrlInterface $url
     * @param string $path
     * @param array<string|int|array<string|int>> $args
     *
     * @return \Concrete\Core\Url\Components\Path
     */
    protected function basePath(UrlInterface $url, string $path, array $args)
    {
        $config = $this->config;
        $path_object = new Path('');

        $rewriting = $config->get('concrete.seo.url_rewriting');
        $rewrite_all = $config->get('concrete.seo.url_rewriting_all');
        $in_dashboard = $this->dashboard->inDashboard($path);

        // If rewriting is disabled, or all_rewriting is disabled and we're
        // in the dashboard, add the dispatcher.
        if (!$rewriting || (!$rewrite_all && $in_dashboard)) {
            /**
             * @phpstan-ignore-next-line
             */
            $path_object->prepend(DISPATCHER_FILENAME);
        }

        return $path_object;
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


}
