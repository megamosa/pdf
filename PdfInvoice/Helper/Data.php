<?php
namespace MagoArab\PdfInvoice\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\LayoutFactory;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    /**
     * Configuration paths
     */
    const XML_PATH_ENABLED = 'sales_pdf/invoice/enable';
    const XML_PATH_PAPER_ORIENTATION = 'sales_pdf/invoice/paper_orientation';
    const XML_PATH_PAPER_SIZE = 'sales_pdf/invoice/paper_size';
    const XML_PATH_USE_CUSTOM_TEMPLATE = 'sales_pdf/invoice/use_custom_template';
    
    /**
     * @var LayoutInterface
     */
    protected $layout;
    
    /**
     * @var LayoutFactory
     */
    protected $layoutFactory;
    
    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * Constructor
     * 
     * @param Context $context
     * @param LayoutFactory $layoutFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        LayoutFactory $layoutFactory,
        LoggerInterface $logger
    ) {
        $this->layoutFactory = $layoutFactory;
        $this->logger = $logger;
        parent::__construct($context);
    }
    
    /**
     * Get logger instance
     * 
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }
    
    /**
     * Check if module is enabled
     * 
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get paper orientation
     * 
     * @param int|null $storeId
     * @return string
     */
    public function getPaperOrientation($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PAPER_ORIENTATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get paper size
     * 
     * @param int|null $storeId
     * @return string
     */
    public function getPaperSize($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PAPER_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Check if custom template should be used
     * 
     * @param int|null $storeId
     * @return bool
     */
    public function useCustomTemplate($storeId = null)
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_USE_CUSTOM_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get invoice HTML
     * 
     * @param Invoice $invoice
     * @return string
     */
    public function getInvoiceHtml(Invoice $invoice)
    {
        if (!$this->layout) {
            $this->layout = $this->layoutFactory->create();
        }
        
        $block = $this->layout->createBlock(
            \MagoArab\PdfInvoice\Block\Order\PrintInvoice::class
        );
        
        $block->setInvoice($invoice);
        return $block->toHtml();
    }
}
