<?php
namespace MagoArab\PdfInvoice\Model\Order\Pdf;

use Magento\Sales\Model\Order\Pdf\Invoice as MagentoPdfInvoice;
use Magento\Payment\Helper\Data as PaymentData;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem;
use Magento\Sales\Model\Order\Pdf\Config;
use Magento\Sales\Model\Order\Pdf\Total\Factory;
use Magento\Sales\Model\Order\Pdf\ItemsFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\App\Emulation;
use Mpdf\Mpdf;
use Magento\Framework\App\Filesystem\DirectoryList;

class Invoice extends MagentoPdfInvoice
{
    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $fileSystem;

    /**
     * Constructor
     *
     * @param PaymentData $paymentData
     * @param StringUtils $string
     * @param ScopeConfigInterface $scopeConfig
     * @param Filesystem $filesystem
     * @param Config $pdfConfig
     * @param Factory $pdfTotalFactory
     * @param ItemsFactory $pdfItemsFactory
     * @param TimezoneInterface $localeDate
     * @param StateInterface $inlineTranslation
     * @param Renderer $addressRenderer
     * @param StoreManagerInterface $storeManager
     * @param Emulation $appEmulation
     * @param array $data
     */
    public function __construct(
        PaymentData $paymentData,
        StringUtils $string,
        ScopeConfigInterface $scopeConfig,
        Filesystem $filesystem,
        Config $pdfConfig,
        Factory $pdfTotalFactory,
        ItemsFactory $pdfItemsFactory,
        TimezoneInterface $localeDate,
        StateInterface $inlineTranslation,
        Renderer $addressRenderer,
        StoreManagerInterface $storeManager,
        Emulation $appEmulation,
        array $data = []
    ) {
        $this->fileSystem = $filesystem;
        parent::__construct(
            $paymentData,
            $string,
            $scopeConfig,
            $filesystem,
            $pdfConfig,
            $pdfTotalFactory,
            $pdfItemsFactory,
            $localeDate,
            $inlineTranslation,
            $addressRenderer,
            $storeManager,
            $appEmulation,
            $data
        );
    }

    /**
     * Return PDF document
     *
     * @param array $invoices
     * @return string
     */
    public function getPdf($invoices = [])
    {
        // If no invoices, use parent method
        if (empty($invoices)) {
            return parent::getPdf($invoices);
        }
        
        try {
            // Create mPDF with minimal settings
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'tempDir' => $this->fileSystem->getDirectoryRead(DirectoryList::TMP)->getAbsolutePath()
            ]);
            
            // Always set RTL direction - this is key for proper Arabic support
            $mpdf->SetDirectionality('rtl');
            
            // Create HTML content
            $html = $this->getSimpleHtml($invoices);
            
            // Add content to PDF
            $mpdf->WriteHTML($html);
            
            // Output PDF as string
            return $mpdf->Output('', 'S');
            
        } catch (\Exception $e) {
            // Fallback to parent method
            return parent::getPdf($invoices);
        }
    }
    
    /**
     * Generate simple HTML with basic styling for invoice
     * 
     * @param array $invoices
     * @return string
     */
    protected function getSimpleHtml($invoices)
    {
        // Always use RTL
        $html = '<!DOCTYPE html>
        <html dir="rtl">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>' . __('Invoice') . '</title>
            <style>
                body {
                    font-family: Tahoma, Tahoma, sans-serif;
                    direction: rtl;
                    margin: 0;
                    padding: 0;
                    font-size: 12px;
                }
                
                /* Header styles */
                .header {
                    background-color: #7b7b7b;
                    color: #fff;
                    padding: 10px;
                    text-align: left;
                }
                
                /* Table styles */
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 10px;
                }
                
                /* Address tables */
                .address-table {
                    border: 1px solid #ddd;
                }
                .address-table td {
                    padding: 10px;
                    vertical-align: top;
                    border: 1px solid #ddd;
                }
                
                /* Method tables */
                .method-table {
                    border: 1px solid #ddd;
                }
                .method-table td {
                    padding: 10px;
                    vertical-align: top;
                    border: 1px solid #ddd;
                }
                
                /* Items table */
                .items-table {
                    margin-top: 20px;
                    border: 1px solid #ddd;
                }
                .items-table th {
                    background-color: #f2f2f2;
                    font-weight: bold;
                    padding: 8px;
                    text-align: right;
                    border: 1px solid #ddd;
                }
                .items-table td {
                    padding: 8px;
                    text-align: right;
                    border: 1px solid #ddd;
                }
                
                /* Totals table */
                .totals-table {
                    width: 40%;
                    margin-left: auto;
                    margin-right: 0;
                    margin-top: 20px;
                }
                .totals-table th {
                    text-align: right;
                    padding: 5px;
                }
                .totals-table td {
                    text-align: left;
                    padding: 5px;
                }
            </style>
        </head>
        <body>';
        
        foreach ($invoices as $invoice) {
            $order = $invoice->getOrder();
            
            // Header section with translatable text
            $html .= '<div class="header">
                <p>' . __('Invoice') . ' # ' . $invoice->getIncrementId() . '</p>
                <p>' . __('Order') . ' # ' . $order->getIncrementId() . '</p>
                <p>' . __('Order Date') . ': ' . date('M d, Y', strtotime($order->getCreatedAt())) . '</p>
            </div>';
            
            // Address section - with translatable headings
            $html .= '<table class="address-table">
                <tr>
                    <td style="width: 50%;">
                        <h3>' . __('Sold to') . ':</h3>';
            
            if ($order->getBillingAddress()) {
                $html .= '<p>' . $order->getBillingAddress()->getName() . '</p>';
                if ($order->getBillingAddress()->getCompany()) {
                    $html .= '<p>' . $order->getBillingAddress()->getCompany() . '</p>';
                }
                $html .= '<p>' . implode('<br />', $order->getBillingAddress()->getStreet()) . '</p>';
                $html .= '<p>' . $order->getBillingAddress()->getCity() . ', ' . 
                    $order->getBillingAddress()->getRegion() . ' ' . 
                    $order->getBillingAddress()->getPostcode() . '</p>';
                $html .= '<p>' . $order->getBillingAddress()->getCountryId() . '</p>';
                if ($order->getBillingAddress()->getTelephone()) {
                    $html .= '<p>T: ' . $order->getBillingAddress()->getTelephone() . '</p>';
                }
            }
            
            $html .= '</td>
                    <td style="width: 50%;">
                        <h3>' . __('Ship to') . ':</h3>';
                        
            if (!$order->getIsVirtual() && $order->getShippingAddress()) {
                $html .= '<p>' . $order->getShippingAddress()->getName() . '</p>';
                if ($order->getShippingAddress()->getCompany()) {
                    $html .= '<p>' . $order->getShippingAddress()->getCompany() . '</p>';
                }
                $html .= '<p>' . implode('<br />', $order->getShippingAddress()->getStreet()) . '</p>';
                $html .= '<p>' . $order->getShippingAddress()->getCity() . ', ' . 
                    $order->getShippingAddress()->getRegion() . ' ' . 
                    $order->getShippingAddress()->getPostcode() . '</p>';
                $html .= '<p>' . $order->getShippingAddress()->getCountryId() . '</p>';
                if ($order->getShippingAddress()->getTelephone()) {
                    $html .= '<p>T: ' . $order->getShippingAddress()->getTelephone() . '</p>';
                }
            } elseif ($order->getBillingAddress()) {
                $html .= '<p>' . $order->getBillingAddress()->getName() . '</p>';
                if ($order->getBillingAddress()->getCompany()) {
                    $html .= '<p>' . $order->getBillingAddress()->getCompany() . '</p>';
                }
                $html .= '<p>' . implode('<br />', $order->getBillingAddress()->getStreet()) . '</p>';
                $html .= '<p>' . $order->getBillingAddress()->getCity() . ', ' . 
                    $order->getBillingAddress()->getRegion() . ' ' . 
                    $order->getBillingAddress()->getPostcode() . '</p>';
                $html .= '<p>' . $order->getBillingAddress()->getCountryId() . '</p>';
                if ($order->getBillingAddress()->getTelephone()) {
                    $html .= '<p>T: ' . $order->getBillingAddress()->getTelephone() . '</p>';
                }
            }
            
            $html .= '</td>
                </tr>
            </table>';
            
            // Payment & shipping methods - with translatable headings
            $html .= '<table class="method-table">
                <tr>
                    <td style="width: 50%;">
                        <h3>' . __('Payment Method') . ':</h3>
                        <p>' . $order->getPayment()->getMethodInstance()->getTitle() . '</p>
                    </td>';
                    
            if (!$order->getIsVirtual()) {
                $html .= '<td style="width: 50%;">
                        <h3>' . __('Shipping Method') . ':</h3>
                        <p>' . $order->getShippingDescription() . '</p>';
                        
                if ($invoice->getShippingAmount() > 0) {
                    $html .= '<p>(' . __('Total Shipping Charges') . ' $' . number_format($invoice->getShippingAmount(), 2) . ')</p>';
                }
                
                $html .= '</td>';
            }
            
            $html .= '</tr>
            </table>';
            
            // Items table - with translatable column headers
            $html .= '<table class="items-table">
                <thead>
                    <tr>
                        <th>' . __('Products') . '</th>
                        <th>' . __('SKU') . '</th>
                        <th>' . __('Price') . '</th>
                        <th>' . __('Qty') . '</th>
                        <th>' . __('Tax') . '</th>
                        <th>' . __('Subtotal') . '</th>
                    </tr>
                </thead>
                <tbody>';
                
            foreach ($invoice->getAllItems() as $item) {
                if (!$item->getOrderItem()->getParentItem()) {
                    $html .= '<tr>
                        <td>' . $item->getName() . '</td>
                        <td>' . $item->getSku() . '</td>
                        <td>$' . number_format($item->getPrice(), 2) . '</td>
                        <td>' . (int)$item->getQty() . '</td>
                        <td>$' . number_format($item->getTaxAmount(), 2) . '</td>
                        <td>$' . number_format($item->getRowTotal(), 2) . '</td>
                    </tr>';
                }
            }
            
            $html .= '</tbody>
            </table>';
            
            // Totals table - with translatable labels
            $html .= '<div style="text-align: left; margin-top: 20px;">
                <table class="totals-table">
                    <tr>
                        <th>' . __('Subtotal') . ':</th>
                        <td>$' . number_format($invoice->getSubtotal(), 2) . '</td>
                    </tr>';
                    
                if ((float)$invoice->getDiscountAmount() != 0) {
                    $html .= '<tr>
                        <th>' . __('Discount') . ':</th>
                        <td>$' . number_format(abs($invoice->getDiscountAmount()), 2) . '</td>
                    </tr>';
                }
                
                if ((float)$invoice->getShippingAmount() != 0) {
                    $html .= '<tr>
                        <th>' . __('Shipping & Handling') . ':</th>
                        <td>$' . number_format($invoice->getShippingAmount(), 2) . '</td>
                    </tr>';
                }
                
                if ((float)$invoice->getTaxAmount() != 0) {
                    $html .= '<tr>
                        <th>' . __('Tax') . ':</th>
                        <td>$' . number_format($invoice->getTaxAmount(), 2) . '</td>
                    </tr>';
                }
                
                $html .= '<tr>
                        <th>' . __('Grand Total') . ':</th>
                        <td>$' . number_format($invoice->getGrandTotal(), 2) . '</td>
                    </tr>
                </table>
            </div>';
        }
        
        $html .= '</body></html>';
        
        return $html;
    }
}