<?php
namespace MagoArab\PdfInvoice\Controller\Order;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use MagoArab\PdfInvoice\Api\PdfInvoiceInterface;
use MagoArab\PdfInvoice\Helper\Data as PdfHelper;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\RequestInterface;

class PrintAction extends Action
{
    /**
     * @var PdfInvoiceInterface
     */
    protected $pdfInvoice;
    
    /**
     * @var PdfHelper
     */
    protected $pdfHelper;
    
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
     * Constructor
     * 
     * @param Context $context
     * @param PdfInvoiceInterface $pdfInvoice
     * @param PdfHelper $pdfHelper
     * @param FileFactory $fileFactory
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        PdfInvoiceInterface $pdfInvoice,
        PdfHelper $pdfHelper,
        FileFactory $fileFactory,
        InvoiceRepositoryInterface $invoiceRepository,
        DateTime $dateTime
    ) {
        $this->pdfInvoice = $pdfInvoice;
        $this->pdfHelper = $pdfHelper;
        $this->fileFactory = $fileFactory;
        $this->invoiceRepository = $invoiceRepository;
        $this->dateTime = $dateTime;
        parent::__construct($context);
    }
    
    /**
     * Execute action
     * 
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $invoiceId = $this->getRequest()->getParam('invoice_id');
        
        if ($invoiceId) {
            try {
                $invoice = $this->invoiceRepository->get($invoiceId);
                $pdf = $this->pdfInvoice->generatePdf($invoice);
                $date = $this->dateTime->date('Y-m-d_H-i-s');
                $fileName = 'invoice_' . $date . '.pdf';
                
                return $this->fileFactory->create(
                    $fileName,
                    $pdf,
                    \Magento\Framework\Controller\Result\Raw::class,
                    'application/pdf'
                );
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('There was an error generating the PDF invoice.'));
                $this->_redirect('sales/order/view', ['order_id' => $invoice->getOrderId()]);
                return;
            }
        }
        
        $this->messageManager->addErrorMessage(__('Invoice not found.'));
        $this->_redirect('sales/order/history');
    }
}
