<?php
namespace JPD_Order_Documents_Vendor\Dompdf\Css\Content;

final class NoCloseQuote extends ContentPart
{
    public function __toString(): string
    {
        return "no-close-quote";
    }
}
