<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusFacturXPlugin\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\InvoicingPlugin\Doctrine\ORM\InvoiceRepositoryInterface;
use Sylius\InvoicingPlugin\Security\Voter\InvoiceVoter;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tests\Tmonier\SyliusFacturXPlugin\Unit\InvoiceBuilderTrait;
use Tmonier\SyliusFacturXPlugin\Controller\Admin\FacturXController;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXGeneratorInterface;
use Tmonier\SyliusFacturXPlugin\Repository\ChannelSettingsRepositoryInterface;
use Tmonier\SyliusFacturXPlugin\Repository\CustomerIdentifiersRepositoryInterface;

/**
 * Proves that downloadXmlAction consults InvoiceVoter::ACCESS before serving the XML.
 *
 * AbstractController::isGranted() delegates to the 'security.authorization_checker' entry
 * of the service-locator container injected via setContainer().
 */
final class DownloadXmlActionAccessControlTest extends TestCase
{
    use InvoiceBuilderTrait;

    private FacturXController $controller;

    /** @var \PHPUnit\Framework\MockObject\MockObject&InvoiceRepositoryInterface */
    private $invoiceRepository;

    /** @var \PHPUnit\Framework\MockObject\MockObject&AuthorizationCheckerInterface */
    private $authChecker;

    /** @var \PHPUnit\Framework\MockObject\MockObject&FacturXGeneratorInterface */
    private $generator;

    protected function setUp(): void
    {
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->generator = $this->createMock(FacturXGeneratorInterface::class);

        $this->controller = new FacturXController(
            $this->createMock(ChannelRepositoryInterface::class),
            $this->createMock(CustomerRepositoryInterface::class),
            $this->invoiceRepository,
            $this->createMock(ChannelSettingsRepositoryInterface::class),
            $this->createMock(FactoryInterface::class),
            $this->createMock(CustomerIdentifiersRepositoryInterface::class),
            $this->createMock(FactoryInterface::class),
            $this->generator,
            $this->createMock(EntityManagerInterface::class),
        );

        // Wire the service locator used by AbstractController::isGranted().
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container
            ->method('get')
            ->willReturnCallback(function (string $id) {
                if ($id === 'security.authorization_checker') {
                    return $this->authChecker;
                }

                throw new \RuntimeException("Unexpected service '$id' requested in test.");
            })
        ;
        $this->controller->setContainer($container);
    }

    /** The voter must be consulted: ACCESS is denied → 403. */
    public function testDownloadXmlActionThrowsAccessDeniedWhenVoterRefuses(): void
    {
        $invoice = $this->invoice([]);
        $this->invoiceRepository->method('find')->willReturn($invoice);

        $this->authChecker
            ->expects(self::once())
            ->method('isGranted')
            ->with(InvoiceVoter::ACCESS, $invoice)
            ->willReturn(false)
        ;

        $this->expectException(AccessDeniedException::class);

        $this->controller->downloadXmlAction($invoice->id());
    }

    /** Non-regression: ACCESS granted → XML is served (no exception). */
    public function testDownloadXmlActionServesXmlWhenVoterGrantsAccess(): void
    {
        $invoice = $this->invoice([]);
        $this->invoiceRepository->method('find')->willReturn($invoice);

        $this->authChecker
            ->expects(self::once())
            ->method('isGranted')
            ->with(InvoiceVoter::ACCESS, $invoice)
            ->willReturn(true)
        ;

        $this->generator->method('generateXml')->willReturn('<xml/>');

        $response = $this->controller->downloadXmlAction($invoice->id());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
    }
}
