<?php

/**
 * This file is part of FPDI
 *
 * @package   JPD_Order_Documents_Vendor\setasign\Fpdi
 * @copyright Copyright (c) 2026 Setasign GmbH & Co. KG (https://www.setasign.com)
 * @license   http://opensource.org/licenses/mit-license The MIT License
 */

namespace JPD_Order_Documents_Vendor\setasign\Fpdi;

use JPD_Order_Documents_Vendor\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use JPD_Order_Documents_Vendor\setasign\Fpdi\PdfParser\PdfParserException;
use JPD_Order_Documents_Vendor\setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use JPD_Order_Documents_Vendor\setasign\Fpdi\PdfParser\Type\PdfNull;

/**
 * Class Fpdi
 *
 * This class let you import pages of existing PDF documents into a reusable structure for FPDF.
 */
class Fpdi extends FpdfTpl
{
    use FpdiTrait;
    use FpdfTrait;

    /**
     * FPDI version
     *
     * @string
     */
    const VERSION = '2.6.8';
}
