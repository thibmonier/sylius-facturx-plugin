<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Tmonier\SyliusFacturXPlugin\FacturXAssertionsTrait;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettings;

final class RegenerateInvoicePdfsCommandTest extends KernelTestCase
{
    use FacturXAssertionsTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testNothingIsRenderedWhenFacturXIsNotEnabled(): void
    {
        $tester = $this->execute(['--dry-run' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('0 invoice PDF(s) would be rendered again.', $tester->getDisplay());
    }

    public function testItRendersTheStoredPdfAgainAsFacturX(): void
    {
        $invoice = $this->entityManager->getRepository(InvoiceInterface::class)->findOneBy([], ['number' => 'ASC']);
        self::assertInstanceOf(InvoiceInterface::class, $invoice);
        $channel = $invoice->channel();
        self::assertInstanceOf(ChannelInterface::class, $channel);
        $settings = new ChannelSettings();
        $settings->setChannel($channel);
        $settings->setSiren('732829320');
        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        /** @var string $directory */
        $directory = self::getContainer()->getParameter('sylius_invoicing.invoice_save_path');
        $path = rtrim($directory, '/') . '/' . str_replace('/', '_', $invoice->number()) . '.pdf';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, '%PDF-1.4 plain');

        $tester = $this->execute(['--invoice' => $invoice->number(), '--channel' => $channel->getCode()]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('1 invoice PDF(s) rendered again.', $tester->getDisplay());
        self::assertFacturXPdf((string) file_get_contents($path));
    }

    /** @param array<string, mixed> $input */
    private function execute(array $input): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $tester = new CommandTester((new Application($kernel))->find('tmonier:facturx:regenerate-pdfs'));
        $tester->execute($input);

        return $tester;
    }
}
