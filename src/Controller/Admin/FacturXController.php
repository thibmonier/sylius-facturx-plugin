<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\InvoicingPlugin\Doctrine\ORM\InvoiceRepositoryInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface;
use Tmonier\SyliusFacturXPlugin\Exception\FacturXException;
use Tmonier\SyliusFacturXPlugin\Form\Type\ChannelSettingsType;
use Tmonier\SyliusFacturXPlugin\Form\Type\CustomerIdentifiersType;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXGeneratorInterface;
use Tmonier\SyliusFacturXPlugin\Repository\ChannelSettingsRepositoryInterface;
use Tmonier\SyliusFacturXPlugin\Repository\CustomerIdentifiersRepositoryInterface;

/**
 * Admin pages of the plugin: Factur-X settings of a channel, B2B identifiers of a customer and
 * the download of the factur-x.xml of an invoice.
 */
final class FacturXController extends AbstractController
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
     * @param FactoryInterface<ChannelSettingsInterface> $channelSettingsFactory
     * @param FactoryInterface<CustomerIdentifiersInterface> $customerIdentifiersFactory
     */
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly ChannelSettingsRepositoryInterface $channelSettingsRepository,
        private readonly FactoryInterface $channelSettingsFactory,
        private readonly CustomerIdentifiersRepositoryInterface $customerIdentifiersRepository,
        private readonly FactoryInterface $customerIdentifiersFactory,
        private readonly FacturXGeneratorInterface $facturXGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function channelSettingsAction(Request $request, int $id): Response
    {
        $channel = $this->channelRepository->find($id);
        if (!$channel instanceof ChannelInterface) {
            throw new NotFoundHttpException('Channel not found.');
        }

        $settings = $this->channelSettingsRepository->findOneByChannel($channel);
        if (!$settings instanceof \Tmonier\SyliusFacturXPlugin\Entity\ChannelSettingsInterface) {
            $settings = $this->channelSettingsFactory->createNew();
            $settings->setChannel($channel);
        }

        $form = $this->createForm(ChannelSettingsType::class, $settings);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($settings);
            $this->entityManager->flush();
            $this->addFlash('success', 'tmonier_facturx.channel_settings_saved');

            return $this->redirectToRoute('tmonier_facturx_admin_channel_settings', ['id' => $id]);
        }

        return $this->render('@TmonierSyliusFacturXPlugin/admin/channel/settings.html.twig', [
            'resource' => $channel,
            'channel' => $channel,
            'settings' => $settings,
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    public function customerIdentifiersAction(Request $request, int $id): Response
    {
        $customer = $this->customerRepository->find($id);
        if (!$customer instanceof CustomerInterface) {
            throw new NotFoundHttpException('Customer not found.');
        }

        $identifiers = $this->customerIdentifiersRepository->findOneByCustomer($customer);
        if (!$identifiers instanceof \Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface) {
            $identifiers = $this->customerIdentifiersFactory->createNew();
            $identifiers->setCustomer($customer);
        }

        $form = $this->createForm(CustomerIdentifiersType::class, $identifiers);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($identifiers->isEmpty()) {
                if (null !== $identifiers->getId()) {
                    $this->entityManager->remove($identifiers);
                }
            } else {
                $this->entityManager->persist($identifiers);
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'tmonier_facturx.customer_identifiers_saved');

            return $this->redirectToRoute('sylius_admin_customer_show', ['id' => $id]);
        }

        return $this->render('@TmonierSyliusFacturXPlugin/admin/customer/identifiers.html.twig', [
            'resource' => $customer,
            'customer' => $customer,
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    public function downloadXmlAction(string $id): Response
    {
        $invoice = $this->invoiceRepository->find($id);
        if (!$invoice instanceof InvoiceInterface) {
            throw new NotFoundHttpException('Invoice not found.');
        }

        try {
            $xml = $this->facturXGenerator->generateXml($invoice);
        } catch (FacturXException $exception) {
            $this->addFlash('error', ['message' => 'tmonier_facturx.cannot_generate_xml', 'parameters' => ['%reason%' => $exception->getMessage()]]);

            return $this->redirectToRoute('sylius_invoicing_admin_invoice_show', ['id' => $id]);
        }

        $response = new Response($xml, Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            self::xmlFilename($invoice),
        ));

        return $response;
    }

    public static function xmlFilename(InvoiceInterface $invoice): string
    {
        return sprintf('%s-factur-x.xml', (string) preg_replace('/[^A-Za-z0-9_-]+/', '_', $invoice->number()));
    }
}
