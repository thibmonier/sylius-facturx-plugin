<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettings;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;

/**
 * Tests run against the test application loaded with the Sylius fixtures, whose orders get an
 * invoice from the Invoicing Plugin. The kernel is not rebooted between requests and every test
 * runs in a transaction rolled back at the end.
 *
 * The invoice PDFs are files: the tests that need a fresh PDF remove the stored one, so that
 * the Invoicing Plugin renders it again through the Factur-X decorator.
 */
trait FacturXTestTrait
{
    private KernelBrowser $client;

    private function setUpClient(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->connection()->beginTransaction();
    }

    private function tearDownClient(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
    }

    private function loginAdmin(): AdminUserInterface
    {
        $admin = $this->entityManager()->getRepository(AdminUserInterface::class)->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $this->client->loginUser($admin, 'admin');

        return $admin;
    }

    private function findInvoice(bool $withShopUser = false): InvoiceInterface
    {
        $queryBuilder = $this->entityManager()
            ->createQueryBuilder()
            ->select('i')
            ->from(InvoiceInterface::class, 'i')
            ->innerJoin('i.order', 'o')
            ->orderBy('i.number', 'ASC')
            ->setMaxResults(1)
        ;
        if ($withShopUser) {
            $queryBuilder->innerJoin('o.customer', 'c')->innerJoin('c.user', 'u');
        }

        $invoice = $queryBuilder->getQuery()->getOneOrNullResult();
        if (!$invoice instanceof InvoiceInterface) {
            self::markTestSkipped('The fixtures contain no invoice.');
        }

        return $invoice;
    }

    /** @return list<InvoiceInterface> */
    private function findAllInvoices(): array
    {
        /** @var list<InvoiceInterface> $invoices */
        $invoices = $this->entityManager()->getRepository(InvoiceInterface::class)->findBy([], ['number' => 'ASC']);

        return $invoices;
    }

    /** Seller data that satisfies EN 16931 and the French mentions. */
    private function enableFacturX(ChannelInterface $channel): ChannelSettingsInterface
    {
        $settings = $this->entityManager()->getRepository(ChannelSettings::class)->findOneBy(['channel' => $channel]);
        if (!$settings instanceof ChannelSettingsInterface) {
            $settings = new ChannelSettings();
            $settings->setChannel($channel);
        }

        $settings->setEnabled(true);
        $settings->setLegalName('Sylius Test SAS');
        $settings->setSiren('732829320');
        $settings->setVatNumber('FR44732829320');
        $settings->setLegalForm('SAS au capital de 10 000 €');
        $settings->setTradeRegister('RCS Nantes 732 829 320');
        $settings->setPaymentDueDays(30);
        $settings->setIban('FR7630006000011234567890189');
        $settings->setBic('AGRIFRPP');
        $settings->setLatePaymentPenaltiesNote('Pénalités de retard : trois fois le taux d\'intérêt légal.');
        $settings->setRecoveryIndemnityNote('Indemnité forfaitaire pour frais de recouvrement : 40 €.');
        $settings->setEarlyPaymentDiscountNote('Pas d\'escompte pour paiement anticipé.');
        $this->entityManager()->persist($settings);
        $this->entityManager()->flush();

        return $settings;
    }

    private function channelOf(InvoiceInterface $invoice): ChannelInterface
    {
        $channel = $invoice->channel();
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }

    private function shopUserOf(InvoiceInterface $invoice): ShopUserInterface
    {
        $customer = $invoice->order()->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);
        $user = $customer->getUser();
        self::assertInstanceOf(ShopUserInterface::class, $user);

        return $user;
    }

    /** Removes the stored PDF so that the next download renders it again. */
    private function removeStoredPdf(InvoiceInterface $invoice): void
    {
        /** @var string $directory */
        $directory = self::getContainer()->getParameter('sylius_invoicing.invoice_save_path');
        $path = rtrim($directory, '/') . '/' . str_replace('/', '_', $invoice->number()) . '.pdf';
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** Keeps a generated file for the validators run by the CI (etc/build is git-ignored). */
    private static function keepForValidation(string $filename, string $content): void
    {
        file_put_contents(__DIR__ . '/../../etc/build/' . $filename, $content);
    }

    private function idOf(ResourceInterface $resource): int
    {
        $id = $resource->getId();
        self::assertIsInt($id);

        return $id;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
