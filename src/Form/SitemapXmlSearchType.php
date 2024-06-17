<?php

namespace Concrete\Package\SitemapXml\Form;

use Concrete\Core\Entity\Site\Locale;
use Concrete\Core\Site\Service;
use Concrete\Core\Support\Facade\Application;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SitemapXmlSearchType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param mixed[] $options
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $app = Application::getFacadeApplication();
        /**
         * @var Service $service
         */
        $service = $app->make(Service::class);
        $site = $service->getActiveSiteForEditing();
        $builder
            ->add('locale', EntityType::class, [
                'class' => Locale::class,
                'attr' => [
                    'data-behaviour' => 'locale-selector',
                ],
                'query_builder' => function (EntityRepository $repository) use ($site) {
                    return $repository->createQueryBuilder('l')
                        ->andWhere('l.site = :site')
                        ->setParameter('site', $site)
                        ->orderBy('l.siteLocaleID');
                },
                'choice_value' => function (?Locale $entity): ?int {
                    return $entity?->getLocaleID();
                },
                'choice_label' => function (Locale $locale): string {
                    return Countries::getName($locale->getCountry());
                }
            ])
            ->add('search', SearchType::class, [
                'attr' => ['placeholder' => 'Insert search query'],
                'required' => false
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Search'
            ])
            ->setMethod('GET');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }
}
