<?php

namespace Concrete\Package\SitemapXml\Command\Task\Controller;

use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Runner\ProcessTaskRunner;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Core\Command\Task\Controller\AbstractController;
use Concrete\Package\SitemapXml\Command\GenerateSitemapCommand;

class GenerateSitemapController extends AbstractController
{
    public function getName(): string
    {
        /**
         * @phpstan-ignore-next-line
         */
        return t('Generate Sitemap Index');
    }

    public function getDescription(): string
    {
        /**
         * @phpstan-ignore-next-line
         */
        return t('Creates sitemap.xml at the root of your site');
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        return new ProcessTaskRunner(
            $task,
            new GenerateSitemapCommand(),
            $input,
            /**
             * @phpstan-ignore-next-line
             */
            t('Generation of sitemap.xml started.')
        );
    }
}
