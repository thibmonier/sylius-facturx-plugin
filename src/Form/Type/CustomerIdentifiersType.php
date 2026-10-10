<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Tmonier\SyliusFacturXPlugin\Validator\Constraints\CompanyIdentifier;

final class CustomerIdentifiersType extends AbstractType
{
    public function __construct(private readonly string $dataClass)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, [
                'label' => 'tmonier_facturx.form.customer.company_name',
                'help' => 'tmonier_facturx.form.customer.company_name_help',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('siren', TextType::class, [
                'label' => 'tmonier_facturx.form.siren',
                'required' => false,
                'constraints' => [new CompanyIdentifier(CompanyIdentifier::TYPE_SIREN)],
            ])
            ->add('vatNumber', TextType::class, [
                'label' => 'tmonier_facturx.form.vat_number',
                'required' => false,
                'constraints' => [new CompanyIdentifier(CompanyIdentifier::TYPE_VAT_NUMBER)],
            ])
            ->add('electronicAddress', TextType::class, [
                'label' => 'tmonier_facturx.form.electronic_address',
                'help' => 'tmonier_facturx.form.electronic_address_help',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('electronicAddressScheme', TextType::class, [
                'label' => 'tmonier_facturx.form.electronic_address_scheme',
                'help' => 'tmonier_facturx.form.electronic_address_scheme_help',
                'required' => false,
                'constraints' => [new Assert\Regex(pattern: '/^\s*[0-9A-Za-z]{2,4}\s*$/')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->dataClass,
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_facturx_customer_identifiers';
    }
}
