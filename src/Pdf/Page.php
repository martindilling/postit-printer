<?php

namespace Dilling\PostItPrinter\Pdf;

use TCPDF;

abstract class Page
{
    abstract public function render(TCPDF $pdf);
}
