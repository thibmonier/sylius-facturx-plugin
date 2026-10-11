<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Functional;

use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettings;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;

final class AdminChannelSettingsTest extends WebTestCase
{
    use FacturXTestTrait;

    protected function setUp(): void
    {
        $this->setUpClient();
        $this->loginAdmin();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testTheChannelPagesLinkToTheFacturXSettings(): void
    {
        $channel = $this->findChannel();
        $url = sprintf('/admin/channels/%d/factur-x', $this->idOf($channel));

        $this->client->request('GET', sprintf('/admin/channels/%d/edit', $this->idOf($channel)));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('[data-test-facturx-settings-link][href="%s"]', $url));

        $this->client->request('GET', '/admin/channels/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(sprintf('href="%s"', $url), (string) $this->client->getResponse()->getContent());
    }

    public function testTheAdminSavesTheSettingsOfAChannel(): void
    {
        $channel = $this->findChannel();

        $crawler = $this->client->request('GET', sprintf('/admin/channels/%d/factur-x', $this->idOf($channel)));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test-facturx-settings-help]');

        $form = $crawler->filter('#tmonier-facturx-channel-settings')->form([
            'tmonier_facturx_channel_settings[enabled]' => true,
            'tmonier_facturx_channel_settings[legalName]' => 'Sylius Test SAS',
            'tmonier_facturx_channel_settings[siren]' => '732 829 320',
            'tmonier_facturx_channel_settings[siret]' => '73282932000017',
            'tmonier_facturx_channel_settings[vatNumber]' => 'fr44732829320',
            'tmonier_facturx_channel_settings[electronicAddress]' => '732829320',
            'tmonier_facturx_channel_settings[electronicAddressScheme]' => '0225',
            'tmonier_facturx_channel_settings[operationCategory]' => 'mixed',
            'tmonier_facturx_channel_settings[vatOnDebits]' => true,
            'tmonier_facturx_channel_settings[zeroVatCategory]' => 'Z',
            'tmonier_facturx_channel_settings[paymentDueDays]' => '45',
            'tmonier_facturx_channel_settings[iban]' => 'FR76 3000 6000 0112 3456 7890 189',
            'tmonier_facturx_channel_settings[bic]' => 'AGRIFRPP',
            'tmonier_facturx_channel_settings[recoveryIndemnityNote]' => 'Indemnité forfaitaire : 40 €.',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/admin/channels/%d/factur-x', $this->idOf($channel)));
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test-sylius-flash-message-type="success"]', 'The Factur-X settings have been saved.');

        $this->entityManager()->clear();
        $settings = $this->entityManager()->getRepository(ChannelSettings::class)->findOneBy(['channel' => $channel->getId()]);
        self::assertInstanceOf(ChannelSettingsInterface::class, $settings);
        self::assertTrue($settings->isEnabled());
        self::assertSame('732829320', $settings->getSiren());
        self::assertSame('FR44732829320', $settings->getVatNumber());
        self::assertSame('FR7630006000011234567890189', $settings->getIban());
        self::assertSame('mixed', $settings->getOperationCategory());
        self::assertTrue($settings->isVatOnDebits());
        self::assertSame('Z', $settings->getZeroVatCategory());
        self::assertSame(45, $settings->getPaymentDueDays());
        self::assertSame('Indemnité forfaitaire : 40 €.', $settings->getRecoveryIndemnityNote());
        self::assertNull($settings->getEarlyPaymentDiscountNote());
    }

    public function testInvalidIdentifiersAreRejected(): void
    {
        $channel = $this->findChannel();

        $crawler = $this->client->request('GET', sprintf('/admin/channels/%d/factur-x', $this->idOf($channel)));
        $form = $crawler->filter('#tmonier-facturx-channel-settings')->form([
            'tmonier_facturx_channel_settings[siren]' => '732829321',
            'tmonier_facturx_channel_settings[siret]' => '73282932000018',
            'tmonier_facturx_channel_settings[vatNumber]' => 'FR45732829320',
            'tmonier_facturx_channel_settings[iban]' => 'FR76 0000',
            'tmonier_facturx_channel_settings[vatExemptionReasonCode]' => 'FRANCHISE',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('This SIREN is not valid', $content);
        self::assertStringContainsString('This SIRET is not valid', $content);
        self::assertStringContainsString('This VAT number is not valid', $content);
        self::assertStringContainsString('not a valid International Bank Account Number (IBAN).', $content);
        self::assertNull($this->entityManager()->getRepository(ChannelSettings::class)->findOneBy(['channel' => $channel]));
    }

    public function testThePageIsTranslated(): void
    {
        $channel = $this->findChannel();

        $this->client->request('GET', sprintf('/admin/channels/%d/factur-x?_locale=fr_FR', $this->idOf($channel)));

        self::assertResponseIsSuccessful();
    }

    public function testAnUnknownChannelIsNotFound(): void
    {
        $this->client->request('GET', '/admin/channels/999999/factur-x');

        self::assertResponseStatusCodeSame(404);
    }

    private function findChannel(): ChannelInterface
    {
        $channel = $this->entityManager()->getRepository(ChannelInterface::class)->findOneBy([], ['id' => 'ASC']);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }
}
