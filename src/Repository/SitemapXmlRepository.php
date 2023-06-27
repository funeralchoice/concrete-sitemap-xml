<?php

namespace Concrete\Package\SitemapXml\Repository;

use Concrete\Package\SitemapXml\Entity\SitemapXml;
use Doctrine\ORM\EntityRepository;

class SitemapXmlRepository extends EntityRepository
{
    /**
     * @return SitemapXml[]
     */
    public function findAll(): array
    {
        /**
         * @var SitemapXml[] $results
         */
        $results = $this->findBy([], ['title' => 'ASC']);
        return $results;
    }
}
