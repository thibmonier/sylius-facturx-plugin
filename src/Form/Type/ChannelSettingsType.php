<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Validator\Constraints\CompanyIdentifier;

final class ChannelSettingsType extends AbstractType
{
    public function __construct(private readonly string $dataClass)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'tmonier_facturx.form.channel.enabled',
                'help' => 'tmonier_facturx.form.channel.enabled_help',
                'required' => false,
            ])
            ->add('legalName', TextType::class, [
                'label' => 'tmonier_facturx.form.channel.legal_name',
                'help' => 'tmonier_facturx.form.channel.legal_name_help',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('siren', TextType::class, [
                'label' => 'tmonier_facturx.form.siren',
                'help' => 'tmonier_facturx.form.channel.siren_help',
                'required' => false,
                'constraints' => [new CompanyIdentifier(CompanyIdentifier::TYPE_SIREN)],
            ])
            ->add('siret', TextType::class, [
                'label' => 'tmonier_facturx.form.channel.siret',
                'required' => false,
                'constraints' => [new CompanyIdentifier(CompanyIdentifier::TYPE_SIRET)],
            ])
            ->add('vatNumber', TextType::class, [
                'label' => 'tmonier_facturx.form.vat_number',
                'help' => 'tmonier_facturx.form.channel.vat_number_help',
                'required' => false,
                'constraints' => [new CompanyIdentifier(CompanyIdentifier::TYPE_VAT_NUMBER)],
            ])
            ->add('legalForm', TextType::class, [
                'label' => 'tmonier_facturx.form.channel.legal_form',
                'help' => 'tmonier_facturx.form.channel.legal_form_help',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('tradeRegister', TextType::class, [
                'label' => 'tmonier_facturx.form.channel.trade_register',
                'help' => 'tmonier_facturx.form.channel.trade_register_help',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
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
            ->add('operationCategory', ChoiceType::class, [
                'label' => 'tmonier_facturx.form.channel.operation_category',
                'help' => 'tmonier_facturx.form.channel.operation_category_help',
                'choices' => array_combine(
                    array_map(static fn (string $category): string => 'tmonier_facturx.operation_category.' . $category, ChannelSettingsInterface::OPERATION_CATEGORIES),
                    ChannelSettingsInterface::OPERATION_CATEGORIES,
                ),
                'constraints' => [new Assert\Choice(choices: ChannelSettingsInterface::OPERATION_CATEGORIES)],
            ])
            ->add('vatOnDebits', CheckboxType::class, [
                'label' => 'tmonier_facturx.form.channel.vat_on_debits',
                'help' => 'tmonier_facturx.form.channel.vat_on_debits_help',
                'required' => false,
            ])
            ->add('zeroVatCategory', ChoiceType::class, [
                'label' => 'tmonier_facturx.form.channel.zero_vat_category',
                'help' => 'tmonier_facturx.form.channel.zero_vat_category_help',
                'choices' => array_combine(
                    array_map(static fn (string $category): string => 'tmonier_facturx.vat_category.' . $category, ChannelSettingsInterface::ZERO_VAT_CATEGORIES),
                    ChannelSettingsInterface::ZERO_VAT_CATEGORIES,
                ),
                'constraints' => [new Assert\Choice(choices: ChannelSettingsInterface::ZERO_VAT_CATEGORIES)],
            ])
            ->add('vatExemptionReason', TextType::class, [
                'label' => 'tmonier_facturx.form.channel.vat_exemption_reason',
                'help' => 'tmonier_facturx.form.channel.vat_exemption_reason_help',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('vatExemptionReasonCode', TextType::class, [
                'label' => 'tmonier_facturx.form.channel.vat_exemption_reason_code',
                'help' => 'tmonier_facturx.form.channel.vat_exemption_reason_code_help',
                'required' => false,
                'constraints' => [new Assert\Regex(pattern: '/^\s*VATEX-[A-Za-z0-9-]+\s*$/')],
            ])
            ->add('paymentTerms', TextareaType::class, [
                'label' => 'tmonier_facturx.form.channel.payment_terms',
                'required' => false,
                'attr' => ['rows' => 2, 'style' => 'height: auto'],
                'constraints' => [new Assert\Length(max: 1000)],
            ])
            ->add('paymentDueDays', IntegerType::class, [
                'label' => 'tmonier_facturx.form.channel.payment_due_days',
                'help' => 'tmonier_facturx.form.channel.payment_due_days_help',
                'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 365)],
            ])
            ->add('iban', TextType::class, [
                'label' => 'tmonier_facturx.form.channel.iban',
                'required' => false,
                'constraints' => [new Assert\Iban()],
            ])
            ->add('bic', TextType::class, [
                'label' => 'tmonier_facturx.form.channel.bic',
                'required' => false,
                'constraints' => [new Assert\Bic()],
            ])
            ->add('latePaymentPenaltiesNote', TextareaType::class, [
                'label' => 'tmonier_facturx.form.channel.late_payment_penalties_note',
                'help' => 'tmonier_facturx.form.channel.late_payment_penalties_note_help',
                'required' => false,
                'attr' => ['rows' => 2, 'style' => 'height: auto'],
                'constraints' => [new Assert\Length(max: 1000)],
            ])
            ->add('recoveryIndemnityNote', TextareaType::class, [
                'label' => 'tmonier_facturx.form.channel.recovery_indemnity_note',
                'help' => 'tmonier_facturx.form.channel.recovery_indemnity_note_help',
                'required' => false,
                'attr' => ['rows' => 2, 'style' => 'height: auto'],
                'constraints' => [new Assert\Length(max: 1000)],
            ])
            ->add('earlyPaymentDiscountNote', TextareaType::class, [
                'label' => 'tmonier_facturx.form.channel.early_payment_discount_note',
                'help' => 'tmonier_facturx.form.channel.early_payment_discount_note_help',
                'required' => false,
                'attr' => ['rows' => 2, 'style' => 'height: auto'],
                'constraints' => [new Assert\Length(max: 1000)],
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
        return 'tmonier_facturx_channel_settings';
    }
}
