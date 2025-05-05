<?php
namespace MagoArab\PdfInvoice\Block\Order;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Invoice;

class PrintInvoice extends Template
{
    /**
     * @var Registry
     */
    protected $registry;
    
    /**
     * @var Invoice
     */
    protected $invoice;
    
    /**
     * Constructor
     * 
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $data);
    }
    
    /**
     * Get current invoice
     * 
     * @return Invoice
     */
    public function getInvoice()
    {
        if ($this->invoice === null) {
            if ($this->registry->registry('current_invoice')) {
                $this->invoice = $this->registry->registry('current_invoice');
            } elseif ($this->hasInvoice()) {
                $this->invoice = $this->getData('invoice');
            }
        }
        
        return $this->invoice;
    }
    
    /**
     * Set invoice
     * 
     * @param Invoice $invoice
     * @return $this
     */
    public function setInvoice($invoice)
    {
        $this->invoice = $invoice;
        return $this;
    }
    
    /**
     * Format price
     * 
     * @param float $price
     * @return string
     */
    public function formatPrice($price)
    {
        return $this->getInvoice()->getOrder()->formatPrice($price);
    }
    
    /**
     * Get order
     * 
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->getInvoice()->getOrder();
    }
}