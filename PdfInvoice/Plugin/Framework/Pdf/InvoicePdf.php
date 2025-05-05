<?php
namespace MagoArab\PdfInvoice\Plugin\Framework\Pdf;

use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\Order\Pdf\Invoice;
use Magento\Framework\App\Filesystem\DirectoryList;

class InvoicePdf
{
    /**
     * @var Invoice
     */
    protected $pdfInvoice;
    
    /**
     * @var FileFactory
     */
    protected $fileFactory;
    
    /**
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepository;
    
    /**
     * @var DateTime
     */
    protected $dateTime;
    
    /**
     * @var RequestInterface
     */
    protected $request;
    
    /**
     * Constructor
     * 
     * @param Invoice $pdfInvoice
     * @param FileFactory $fileFactory
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param DateTime $dateTime
     * @param RequestInterface $request
     */
    public function __construct(
        Invoice $pdfInvoice,
        FileFactory $fileFactory,
        InvoiceRepositoryInterface $invoiceRepository,
        DateTime $dateTime,
        RequestInterface $request
    ) {
        $this->pdfInvoice = $pdfInvoice;
        $this->fileFactory = $fileFactory;
        $this->invoiceRepository = $invoiceRepository;
        $this->dateTime = $dateTime;
        $this->request = $request;
    }
    
    /**
     * Around plugin to intercept PDF generation
     * 
     * @param mixed $subject
     * @param callable $proceed
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function aroundExecute($subject, callable $proceed)
    {
        try {
            $invoiceId = $this->request->getParam('invoice_id');
            
            if ($invoiceId) {
                $invoice = $this->invoiceRepository->get($invoiceId);
                if ($invoice) {
                    // Get PDF content
                    $pdfContent = $this->pdfInvoice->getPdf([$invoice]);
                    
                    // Create filename
                    $date = $this->dateTime->date('Y-m-d_H-i-s');
                    $fileName = 'invoice_' . $invoice->getIncrementId() . '_' . $date . '.pdf';
                    
                    // Return file for download
                    return $this->fileFactory->create(
                        $fileName,
                        $pdfContent,
                        DirectoryList::VAR_DIR,
                        'application/pdf'
                    );
                }
            }
        } catch (\Exception $e) {
            // Log error but continue to original action
        }
        
        // If the custom PDF generation fails, proceed with the original action
        return $proceed();
    }
}