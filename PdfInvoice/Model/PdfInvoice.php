<?php
namespace MagoArab\PdfInvoice\Model;

use MagoArab\PdfInvoice\Api\PdfInvoiceInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mpdf\Mpdf;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Translate\Inline\StateInterface;

class PdfInvoice implements PdfInvoiceInterface
{
    /**
     * @var Filesystem
     */
    protected $filesystem;
    
    /**
     * @var DateTime
     */
    protected $dateTime;
    
    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var StateInterface
     */
    protected $inlineTranslation;
    
    /**
     * Constructor
     * 
     * @param Filesystem $filesystem
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param StateInterface $inlineTranslation
     */
    public function __construct(
        Filesystem $filesystem,
        DateTime $dateTime,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        StateInterface $inlineTranslation
    ) {
        $this->filesystem = $filesystem;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->inlineTranslation = $inlineTranslation;
    }
    
    /**
     * {@inheritdoc}
     */
    public function generatePdf($invoice)
    {
        return $this->generatePdfs([$invoice]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function generatePdfs(array $invoices)
    {
        if (empty($invoices)) {
            return '';
        }
        
        try {
            // تعطيل الترجمة المضمنة لتجنب مشاكل في الـ PDF
            $this->inlineTranslation->suspend();
            
            // إنشاء PDF مع دعم اللغة العربية
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'tempDir' => $this->filesystem->getDirectoryRead(DirectoryList::TMP)->getAbsolutePath(),
                'fontDir' => [
                    $this->filesystem->getDirectoryRead(DirectoryList::ROOT)->getAbsolutePath() . '/lib/internal/fonts'
                ],
                'default_font' => 'dejavusans',
                'autoScriptToLang' => true,
                'autoLangToFont' => true
            ]);
            
            // تحديد اتجاه الصفحة حسب اللغة المختارة
            $storeId = $invoices[0]->getStoreId();
            $store = $this->storeManager->getStore($storeId);
            $locale = $store->getConfig('general/locale/code');
            
            // تحديد اتجاه اللغة (RTL للعربية والعبرية وما شابه)
            $isRtl = (strpos($locale, 'ar') === 0 || strpos($locale, 'he') === 0);
            if ($isRtl) {
                $mpdf->SetDirectionality('rtl');
            }
            
            // اقرأ معلومات من الفواتير مباشرة
            $html = $this->generateInvoiceHtml($invoices, $isRtl, $storeId);
            
            // اكتب المحتوى إلى PDF
            $mpdf->WriteHTML($html);
            
            // إعادة تفعيل الترجمة المضمنة
            $this->inlineTranslation->resume();
            
            // أرجع محتوى PDF كسلسلة
            return $mpdf->Output('', 'S');
            
        } catch (\Exception $e) {
            // إعادة تفعيل الترجمة المضمنة في حالة الخطأ
            $this->inlineTranslation->resume();
            
            // سجل الخطأ
            $this->logger->critical('MagoArab PDF Error: ' . $e->getMessage());
            $this->logger->critical($e->getTraceAsString());
            
            return '';
        }
    }
    
    /**
     * ترجمة نص
     * 
     * @param string $text
     * @return string
     */
    protected function __($text)
    {
        return __($text);
    }
    
    /**
     * إنشاء محتوى HTML للفاتورة بتنسيق ماجنتو الأصلي
     * 
     * @param array $invoices
     * @param bool $isRtl
     * @param int $storeId
     * @return string
     */
    protected function generateInvoiceHtml(array $invoices, $isRtl = false, $storeId = null)
    {
        // إعداد CSS حسب نفس تنسيق ماجنتو
        $css = '
            body {
                font-family: "DejaVu Sans", "Helvetica", "Arial", sans-serif;
                color: #000;
                font-size: 9pt;
                margin: 0;
                padding: 0;
            }
            table {
                border-collapse: collapse;
                width: 100%;
            }
            th, td {
                padding: 5px;
            }
            .header {
                background-color: #7b7b7b;
                color: #fff;
                padding: 10px;
            }
            .address-block {
                width: 50%;
                float: left;
            }
            .method-block {
                width: 50%;
                float: left;
            }
            .clear {
                clear: both;
            }
            .items-table th {
                background-color: #f0f0f0;
                border: 1px solid #ddd;
            }
            .items-table td {
                border: 1px solid #ddd;
            }
            .totals-table {
                width: auto;
                float: right;
                margin-top: 20px;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
            }
        ';
        
        // إضافة تعديلات لدعم RTL إذا كانت اللغة عربية
        if ($isRtl) {
            $css .= '
                body {
                    direction: rtl;
                }
                .address-block {
                    float: right;
                }
                .method-block {
                    float: right;
                }
                .totals-table {
                    float: left;
                }
            ';
        }
        
        $html = '<!DOCTYPE html>
        <html ' . ($isRtl ? 'dir="rtl"' : '') . '>
        <head>
            <meta charset="utf-8">
            <title>' . $this->__('Invoice') . '</title>
            <style>' . $css . '</style>
        </head>
        <body>';
        
        foreach ($invoices as $invoice) {
            $order = $invoice->getOrder();
            
            // معلومات الفاتورة الأساسية
            $html .= '<div class="header">
                <p>' . $this->__('Invoice #') . ' ' . $invoice->getIncrementId() . '</p>
                <p>' . $this->__('Order #') . ' ' . $order->getIncrementId() . '</p>
                <p>' . $this->__('Order Date:') . ' ' . date('M d, Y', strtotime($order->getCreatedAt())) . '</p>
            </div>';
            
            // بيانات البائع والشحن
            $html .= '<div class="addresses">
                <div class="address-block">
                    <h3>' . $this->__('Sold to:') . '</h3>';
            
            if ($order->getBillingAddress()) {
                $html .= '<p>' . $order->getBillingAddress()->getName() . '</p>';
                if ($order->getBillingAddress()->getCompany()) {
                    $html .= '<p>' . $order->getBillingAddress()->getCompany() . '</p>';
                }
                
                $html .= '<p>' . implode('<br>', $order->getBillingAddress()->getStreet()) . '</p>';
                $html .= '<p>' . $order->getBillingAddress()->getCity() . ', ' . 
                    $order->getBillingAddress()->getRegion() . ' ' . 
                    $order->getBillingAddress()->getPostcode() . '</p>';
                $html .= '<p>' . $order->getBillingAddress()->getCountryId() . '</p>';
                
                if ($order->getBillingAddress()->getTelephone()) {
                    $html .= '<p>T: ' . $order->getBillingAddress()->getTelephone() . '</p>';
                }
            }
            
            $html .= '</div>';
            
            $html .= '<div class="address-block">
                <h3>' . $this->__('Ship to:') . '</h3>';
            
            if (!$order->getIsVirtual() && $order->getShippingAddress()) {
                $html .= '<p>' . $order->getShippingAddress()->getName() . '</p>';
                if ($order->getShippingAddress()->getCompany()) {
                    $html .= '<p>' . $order->getShippingAddress()->getCompany() . '</p>';
                }
                
                $html .= '<p>' . implode('<br>', $order->getShippingAddress()->getStreet()) . '</p>';
                $html .= '<p>' . $order->getShippingAddress()->getCity() . ', ' . 
                    $order->getShippingAddress()->getRegion() . ' ' . 
                    $order->getShippingAddress()->getPostcode() . '</p>';
                $html .= '<p>' . $order->getShippingAddress()->getCountryId() . '</p>';
                
                if ($order->getShippingAddress()->getTelephone()) {
                    $html .= '<p>T: ' . $order->getShippingAddress()->getTelephone() . '</p>';
                }
            } elseif ($order->getBillingAddress()) {
                // إذا كان الطلب افتراضياً، استخدم عنوان الفواتير للشحن
                $html .= '<p>' . $order->getBillingAddress()->getName() . '</p>';
                if ($order->getBillingAddress()->getCompany()) {
                    $html .= '<p>' . $order->getBillingAddress()->getCompany() . '</p>';
                }
                
                $html .= '<p>' . implode('<br>', $order->getBillingAddress()->getStreet()) . '</p>';
                $html .= '<p>' . $order->getBillingAddress()->getCity() . ', ' . 
                    $order->getBillingAddress()->getRegion() . ' ' . 
                    $order->getBillingAddress()->getPostcode() . '</p>';
                $html .= '<p>' . $order->getBillingAddress()->getCountryId() . '</p>';
                
                if ($order->getBillingAddress()->getTelephone()) {
                    $html .= '<p>T: ' . $order->getBillingAddress()->getTelephone() . '</p>';
                }
            }
            
            $html .= '</div>
            </div>';
            
            // طرق الدفع والشحن
            $html .= '<div class="methods">
                <div class="method-block">
                    <h3>' . $this->__('Payment Method:') . '</h3>
                    <p>' . $order->getPayment()->getMethodInstance()->getTitle() . '</p>
                </div>';
            
            if (!$order->getIsVirtual()) {
                $html .= '<div class="method-block">
                    <h3>' . $this->__('Shipping Method:') . '</h3>
                    <p>' . $order->getShippingDescription() . '</p>';
                
                if ($invoice->getShippingAmount() > 0) {
                    $html .= '<p>(' . $this->__('Total Shipping Charges') . ' $' . number_format($invoice->getShippingAmount(), 2) . ')</p>';
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div>
            <div class="clear"></div>';
            
            // جدول المنتجات
            $html .= '<table class="items-table">
                <thead>
                    <tr>
                        <th>' . $this->__('Products') . '</th>
                        <th>' . $this->__('SKU') . '</th>
                        <th>' . $this->__('Price') . '</th>
                        <th>' . $this->__('Qty') . '</th>
                        <th>' . $this->__('Tax') . '</th>
                        <th>' . $this->__('Subtotal') . '</th>
                    </tr>
                </thead>
                <tbody>';
            
            // إضافة المنتجات للجدول
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
            
            // جدول المجاميع
            $html .= '<table class="totals-table">
                <tr>
                    <th>' . $this->__('Subtotal:') . '</th>
                    <td>$' . number_format($invoice->getSubtotal(), 2) . '</td>
                </tr>';
            
            if ((float)$invoice->getDiscountAmount() != 0) {
                $html .= '<tr>
                    <th>' . $this->__('Discount:') . '</th>
                    <td>$' . number_format(abs($invoice->getDiscountAmount()), 2) . '</td>
                </tr>';
            }
            
            if ((float)$invoice->getShippingAmount() != 0) {
                $html .= '<tr>
                    <th>' . $this->__('Shipping & Handling:') . '</th>
                    <td>$' . number_format($invoice->getShippingAmount(), 2) . '</td>
                </tr>';
            }
            
            if ((float)$invoice->getTaxAmount() != 0) {
                $html .= '<tr>
                    <th>' . $this->__('Tax:') . '</th>
                    <td>$' . number_format($invoice->getTaxAmount(), 2) . '</td>
                </tr>';
            }
            
            $html .= '<tr>
                    <th>' . $this->__('Grand Total:') . '</th>
                    <td>$' . number_format($invoice->getGrandTotal(), 2) . '</td>
                </tr>
            </table>
            <div class="clear"></div>';
        }
        
        $html .= '</body></html>';
        
        return $html;
    }
}