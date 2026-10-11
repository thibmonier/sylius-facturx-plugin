<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Command;

use Sylius\InvoicingPlugin\Doctrine\ORM\InvoiceRepositoryInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Sylius\InvoicingPlugin\Generator\InvoicePdfFileGeneratorInterface;
use Sylius\InvoicingPlugin\Manager\InvoiceFileManagerInterface;
use Sylius\InvoicingPlugin\Model\InvoicePdf;
use Sylius\PdfGenerationBundle\Core\Filesystem\Manager\PdfFileManagerInterface;
use Sylius\PdfGenerationBundle\Core\Model\PdfFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tmonier\SyliusFacturXPlugin\Generator\FacturXGeneratorInterface;

/**
 * The Invoicing Plugin renders the PDF of an invoice once and stores it. This command renders
 * again, as Factur-X, the stored PDFs of the channels where Factur-X is enabled, e.g. after the
 * plugin is installed or the seller data is fixed.
 */
#[AsCommand(
    name: 'tmonier:facturx:regenerate-pdfs',
    description: 'Renders again, as Factur-X PDF/A-3, the stored invoice PDFs of the channels where Factur-X is enabled',
)]
final class RegenerateInvoicePdfsCommand extends Command
{
    private const STORAGE_CONTEXT = 'sylius_invoicing';

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly InvoicePdfFileGeneratorInterface $invoicePdfFileGenerator,
        private readonly InvoiceFileManagerInterface|PdfFileManagerInterface $invoiceFileManager,
        private readonly FacturXGeneratorInterface $facturXGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Only the invoices of this channel code')
            ->addOption('invoice', null, InputOption::VALUE_REQUIRED, 'Only the invoice with this number')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the invoices without rendering them')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $channelCode = $input->getOption('channel');
        $number = $input->getOption('invoice');
        $dryRun = (bool) $input->getOption('dry-run');

        $criteria = [];
        if (is_string($number)) {
            $criteria['number'] = $number;
        }

        $count = 0;
        /** @var InvoiceInterface $invoice */
        foreach ($this->invoiceRepository->findBy($criteria, ['issuedAt' => 'ASC']) as $invoice) {
            if (is_string($channelCode) && $invoice->channel()->getCode() !== $channelCode) {
                continue;
            }
            if (!$this->facturXGenerator->isEnabledFor($invoice)) {
                continue;
            }

            ++$count;
            $io->writeln(sprintf('Invoice %s (channel %s)', $invoice->number(), (string) $invoice->channel()->getCode()), OutputInterface::VERBOSITY_VERBOSE);
            if ($dryRun) {
                continue;
            }

            $invoicePdf = $this->invoicePdfFileGenerator->generate($invoice);
            $this->store($invoicePdf);
        }

        $io->success(sprintf($dryRun ? '%d invoice PDF(s) would be rendered again.' : '%d invoice PDF(s) rendered again.', $count));

        return Command::SUCCESS;
    }

    /** The file managers do not overwrite: the stored PDF is removed first. */
    private function store(InvoicePdf $invoicePdf): void
    {
        if ($this->invoiceFileManager instanceof PdfFileManagerInterface) {
            if ($this->invoiceFileManager->has($invoicePdf->filename(), self::STORAGE_CONTEXT)) {
                $this->invoiceFileManager->remove($invoicePdf->filename(), self::STORAGE_CONTEXT);
            }
            $this->invoiceFileManager->save(new PdfFile($invoicePdf->filename(), $invoicePdf->content()), self::STORAGE_CONTEXT);

            return;
        }

        try {
            $this->invoiceFileManager->remove($invoicePdf);
        } catch (\Throwable) {
            // not stored yet
        }
        $this->invoiceFileManager->save($invoicePdf);
    }
}
