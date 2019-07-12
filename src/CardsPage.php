<?php

namespace Dilling\PostItPrinter;

use TCPDF;
use Dilling\PostItPrinter\Pdf\Page;
use Dilling\PostItPrinter\Card\Card;

class CardsPage extends Page
{
    /** @var float */
    private $pageMargin;

    /** @var float */
    private $space;

    /** @var int */
    private $rows;

    /** @var int */
    private $cols;

    /** @var float */
    private $padding = 1;

    /**
     * @param \Dilling\PostItPrinter\Card\Card[] $cards
     * @param float $pageMargin
     * @param float $space
     * @param int $rows
     * @param int $cols
     */
    public function __construct(
        array $cards,
        float $pageMargin,
        float $space,
        int $rows,
        int $cols
    ) {
        $this->cards = $cards;
        $this->pageMargin = $pageMargin;
        $this->space = $space;
        $this->rows = $rows;
        $this->cols = $cols;
    }

    public function render(TCPDF $pdf)
    {
        $index = 0;

        foreach (\range(1, $this->rows) as $row) {
            foreach (\range(1, $this->cols) as $col) {
                $card = $this->cards[$index] ?? null;

                if (!$card) {
                    continue;
                }

                $this->drawCard($pdf, $card, $col, $row);

                $index++;
            }
        }
    }

    public function drawCard(TCPDF $pdf, Card $card, int $col, int $row)
    {
        $cellBorder = 0;
        $colorBackgroundLight = [247, 250, 252];
        $colorBackgroundDark = [226, 232, 240];
        $colorBorder = [160, 174, 192];
        $colorLabelText = [160, 174, 192];
        $colorGrayText = [113, 128, 150];
        $lineSolid = [
            'width' => 0.30,
            'cap' => 'butt',
            'join' => 'miter',
            'dash' => 0,
            'color' => $colorBorder,
        ];
        $lineDashed = [
            'width' => 0.25,
            'cap' => 'round',
            'join' => 'round',
            'dash' => '5,3',
            'color' => $colorBorder,
        ];

        // Calculate current position
        $x = $this->pageMargin + (($col - 1) * ($card->width() + $this->space));
        $y = $this->pageMargin + (($row - 1) * ($card->height() + $this->space));
        $width = $card->width();
        $height = $card->height();

        // Background
//        $pdf->Rect($x, $y, $width, $height, 'F', [], $colorBackgroundLight);

        // Lines
        $pdf->Line(
            $x,
            $y + $height - 10,
            $x + $width,
            $y + $height - 10,
            $lineSolid
        );
        $pdf->Line(
            $x + 10,
            $y + $height - 10,
            $x + 10,
            $y + $height,
            $lineSolid
        );

        // Points
        $font = 'helvetica';
        $fontSize = 10;
        $fontAlign = 'C';
        $fontStretch = 0;
        $posX = $x;
        $posY = $y + $height - 10;
        $boxWidth = 10;
        $boxHeight = 10;
        $text = $card->points();

        $pdf->SetFont($font, 'B', $fontSize);
        $pdf->SetTextColor(...$colorGrayText);
        $pdf->SetFillColor(0);
        $pdf->SetXY($posX, $posY);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->Cell($boxWidth, $boxHeight, $text, $cellBorder, 0, $fontAlign, false, '', $fontStretch);

        // Type
        $font = 'helvetica';
        $fontSize = 10;
        $fontAlign = 'R';
        $fontStretch = 0;
        $posX = $x + 10;
        $posY = $y + $height - 10;
        $boxWidth = $width - 10 - $this->padding;
        $boxHeight = 10;
        $text = $card->type();

        $pdf->SetFont($font, 'B', $fontSize);
        $pdf->SetTextColor(...$colorGrayText);
        $pdf->SetFillColor(0);
        $pdf->SetXY($posX, $posY);
        $pdf->setCellPaddings(0, 0, 2, 0);
        $pdf->Cell($boxWidth, $boxHeight, $text, $cellBorder, 0, $fontAlign, false, '', $fontStretch);

        // Title
        $font = 'helvetica';
        $fontSize = 12;
        $fontAlign = 'L';
        $fontStretch = 1;
        $posX = $x;
        $posY = $y;
        $boxWidth = $width;
        $boxHeight = 10;
        $text = $card->title();

        $pdf->SetFont($font, 'B', $fontSize);
        $pdf->SetTextColor(0);
        $pdf->SetFillColor(...$colorBackgroundDark);
        $pdf->SetXY($posX, $posY);
        $pdf->setCellPaddings(3, 3, 3, 3);
        $pdf->MultiCell($boxWidth, $boxHeight, $text, $cellBorder, $fontAlign, 1);

        // Labels
        $labels = $card->labels();
        $labelsBottomY = $y + $height - 15;

        foreach ($labels as $lineIndex => $label) {
            // Text
            $font = 'helvetica';
            $fontSize = 8;
            $fontAlign = 'L';
            $fontStretch = 1;
            $posX = $x;
            $posY = $labelsBottomY - ($lineIndex * 5) - 5;
            $boxWidth = $width - ($this->padding * 2);
            $boxHeight = 5;
            $text = '[ ' . $label . ' ]';

            $pdf->SetFont($font, 'B', $fontSize);
            $pdf->SetTextColor(...$colorLabelText);
            $pdf->SetFillColor(0);
            $pdf->SetXY($posX, $posY);
            $pdf->setCellPaddings(3, 0, 0, 0);
            $pdf->Cell($boxWidth, $boxHeight, $text, $cellBorder, 0, $fontAlign, false, '', $fontStretch);
        }

        // Border
//        $pdf->Rect($x, $y, $width, $height, 'D', ['all' => $lineDashed], []);
    }
}
