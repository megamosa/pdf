<?php
namespace MagoArab\PdfInvoice\Api;

interface PdfInvoiceInterface
{
    /**
     * Generate PDF invoice for a specific invoice
     * 
     * @param mixed $invoice
     * @return string
     */
    public function generatePdf($invoice);
    
    /**
     * Generate PDF invoice for multiple invoices
     * 
     * @param array $invoices
     * @return string
     */
    public function generatePdfs(array $invoices);
}