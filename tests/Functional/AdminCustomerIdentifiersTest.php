<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiers;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface;

final class AdminCustomerIdentifiersTest extends WebTestCase
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

    public function testTheCustomerPageShowsTheB2bIdentifiersCard(): void
    {
        $customer = $this->findCustomer();

        $this->client->request('GET', sprintf('/admin/customers/%d', $this->idOf($customer)));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test-facturx-customer-identifiers]', 'No B2B identifiers');
        self::assertSelectorExists(sprintf('[data-test-edit-facturx-identifiers][href="/admin/customers/%d/factur-x"]', $this->idOf($customer)));
    }

    public function testTheAdminSavesAndRemovesTheIdentifiersOfACustomer(): void
    {
        $customer = $this->findCustomer();
        $url = sprintf('/admin/customers/%d/factur-x', $this->idOf($customer));

        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('#tmonier-facturx-customer-identifiers')->form([
            'tmonier_facturx_customer_identifiers[companyName]' => 'Martin Conseil SARL',
            'tmonier_facturx_customer_identifiers[siren]' => '552081317',
            'tmonier_facturx_customer_identifiers[vatNumber]' => 'FR03552081317',
            'tmonier_facturx_customer_identifiers[electronicAddress]' => '552081317',
            'tmonier_facturx_customer_identifiers[electronicAddressScheme]' => '0225',
        ]));

        self::assertResponseRedirects(sprintf('/admin/customers/%d', $this->idOf($customer)));
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test-facturx-customer-identifiers]', '552081317');
        self::assertSelectorTextContains('[data-test-facturx-customer-identifiers]', 'Martin Conseil SARL');
        self::assertInstanceOf(CustomerIdentifiersInterface::class, $this->findIdentifiers($customer));

        // Emptying every field removes the identifiers.
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->filter('#tmonier-facturx-customer-identifiers')->form([
            'tmonier_facturx_customer_identifiers[companyName]' => '',
            'tmonier_facturx_customer_identifiers[siren]' => '',
            'tmonier_facturx_customer_identifiers[vatNumber]' => '',
            'tmonier_facturx_customer_identifiers[electronicAddress]' => '',
            'tmonier_facturx_customer_identifiers[electronicAddressScheme]' => '',
        ]));
        self::assertResponseRedirects();
        self::assertNull($this->findIdentifiers($customer));
    }

    public function testAnInvalidSirenIsRejected(): void
    {
        $customer = $this->findCustomer();

        $crawler = $this->client->request('GET', sprintf('/admin/customers/%d/factur-x', $this->idOf($customer)));
        $this->client->submit($crawler->filter('#tmonier-facturx-customer-identifiers')->form([
            'tmonier_facturx_customer_identifiers[siren]' => '552081318',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('This SIREN is not valid', (string) $this->client->getResponse()->getContent());
        self::assertNull($this->findIdentifiers($customer));
    }

    private function findCustomer(): CustomerInterface
    {
        $customer = $this->entityManager()->getRepository(CustomerInterface::class)->findOneBy([], ['id' => 'ASC']);
        self::assertInstanceOf(CustomerInterface::class, $customer);

        return $customer;
    }

    private function findIdentifiers(CustomerInterface $customer): ?CustomerIdentifiersInterface
    {
        $this->entityManager()->clear();
        $identifiers = $this->entityManager()->getRepository(CustomerIdentifiers::class)->findOneBy(['customer' => $customer->getId()]);

        return $identifiers instanceof CustomerIdentifiersInterface ? $identifiers : null;
    }
}
