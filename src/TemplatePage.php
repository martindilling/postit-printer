<?php

namespace Dilling\PostItPrinter;

use TCPDF;
use Dilling\PostItPrinter\Pdf\Page;
use Dilling\PostItPrinter\Card\CardTemplate;

class TemplatePage extends Page
{
    /** @var CardTemplate */
    private $template;

    /** @var float */
    private $pageMargin;

    /** @var float */
    private $space;

    /** @var int */
    private $rows;

    /** @var int */
    private $cols;

    /**
     * @param CardTemplate $template
     * @param float $pageMargin
     * @param float $space
     * @param int $rows
     * @param int $cols
     */
    public function __construct(
        CardTemplate $template,
        float $pageMargin,
        float $space,
        int $rows,
        int $cols
    ) {
        $this->template = $template;
        $this->pageMargin = $pageMargin;
        $this->space = $space;
        $this->rows = $rows;
        $this->cols = $cols;
    }

    public function render(TCPDF $pdf)
    {
        $borderStyle = [
            'width' => 0.25,
            'cap' => 'round',
            'join' => 'round',
            'dash' => '5,3',
            'color' => $this->template->colorBorder(),
        ];

        foreach (\range(1, $this->rows) as $row) {
            foreach (\range(1, $this->cols) as $col) {
                // Calculate current position
                $x = $this->pageMargin + (($col - 1) * ($this->template->width() + $this->space));
                $y = $this->pageMargin + (($row - 1) * ($this->template->height() + $this->space));

                // Background
                $pdf->Rect($x, $y, $this->template->width(), $this->template->height(), 'F', [], $this->template->colorBackgroundLight());
                // Top bar
                $pdf->Rect($x, $y, $this->template->width(), 10, 'F', [], $this->template->colorBackgroundDark());
                // Border
                $pdf->Rect($x, $y, $this->template->width(), $this->template->height(), 'D', ['all' => $borderStyle], []);
            }
        }
    }
}
