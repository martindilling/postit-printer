<?php

namespace Dilling\PostItPrinter\Pdf;

use TCPDF;

class Document
{
    /** @var TCPDF */
    private $pdf;

    /** @var Page[] */
    private $pages = [];

    /**
     * @param TCPDF $pdf
     */
    public function __construct(TCPDF $pdf)
    {
        $this->pdf = $pdf;
    }

    public function addPage(Page $page) : void
    {
        $this->pages[] = $page;
    }

    public function getContent() : string
    {
        // create new PDF document
//        $pdf = new \TCPDF($orientation = 'L', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false);

        $pageMargin = 15;

        // set document information
        $this->pdf->SetCreator(PDF_CREATOR);
        $this->pdf->SetAuthor('Martin Dilling-Hansen');
        $this->pdf->SetTitle('Post-It Printer Template');
        $this->pdf->SetSubject('Default size');
        $this->pdf->SetKeywords('Post-It, Printer, Template');

        // remove default header/footer
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        // set default monospaced font
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $this->pdf->SetMargins($pageMargin, $pageMargin, $pageMargin);

        // set auto page breaks
        $this->pdf->SetAutoPageBreak(true, $pageMargin);

        // set image scale factor
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $this->pdf->SetDisplayMode('real', 'SinglePage', 'UseNone');

        $this->pdf->setViewerPreferences([
//            'HideToolbar' => true,
//            'HideMenubar' => true,
//            'HideWindowUI' => true,
//            'FitWindow' => true,
//            'CenterWindow' => true,
//            'DisplayDocTitle' => true,
//            'NonFullScreenPageMode' => 'UseNone', // UseNone, UseOutlines, UseThumbs, UseOC
//            'ViewArea' => 'CropBox', // CropBox, BleedBox, TrimBox, ArtBox
//            'ViewClip' => 'CropBox', // CropBox, BleedBox, TrimBox, ArtBox
//            'PrintArea' => 'CropBox', // CropBox, BleedBox, TrimBox, ArtBox
//            'PrintClip' => 'CropBox', // CropBox, BleedBox, TrimBox, ArtBox
            'PrintScaling' => 'None', // None, AppDefault
//            'Duplex' => 'DuplexFlipLongEdge', // Simplex, DuplexFlipShortEdge, DuplexFlipLongEdge
//            'PickTrayByPDFSize' => true,
//            'PrintPageRange' => array(1,1,2,3),
//            'NumCopies' => 2
        ]);

        foreach ($this->pages as $page) {
            $this->pdf->AddPage('L', 'A4');
            $page->render($this->pdf);
        }

        $this->pdf->lastPage();

        return $this->pdf->Output('tmp.pdf', 'S');
    }
}
