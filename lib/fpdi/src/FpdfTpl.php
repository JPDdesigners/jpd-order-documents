<?php

/**
 * This file is part of FPDI
 *
 * @package   JPD_Order_Documents_Vendor\setasign\Fpdi
 * @copyright Copyright (c) 2026 Setasign GmbH & Co. KG (https://www.setasign.com)
 * @license   http://opensource.org/licenses/mit-license The MIT License
 */

namespace JPD_Order_Documents_Vendor\setasign\Fpdi;

/**
 * Class FpdfTpl
 *
 * This class adds a templating feature to FPDF.
 */
class FpdfTpl extends \JPD_Order_Documents_Vendor\FPDF
{
    use FpdfTplTrait;
}
