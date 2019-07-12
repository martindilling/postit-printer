<?php

namespace Dilling\PostItPrinter\Card;

class CardTemplate
{
    /** @var float */
    protected $width;

    /** @var float */
    protected $height;

    /** @var int[] */
    protected $colorBackgroundLight = [247, 250, 252];

    /** @var int[] */
    protected $colorBackgroundDark = [226, 232, 240];

    /** @var int[] */
    protected $colorBorder = [160, 174, 192];

    /**
     * @param float $width
     * @param float $height
     */
    public function __construct(float $width, float $height)
    {
        $this->width = $width;
        $this->height = $height;
    }

    public function width() : float
    {
        return $this->width;
    }

    public function height() : float
    {
        return $this->height;
    }

    /**
     * @return int[]
     */
    public function colorBackgroundLight() : array
    {
        return $this->colorBackgroundLight;
    }

    /**
     * @return int[]
     */
    public function colorBackgroundDark() : array
    {
        return $this->colorBackgroundDark;
    }

    /**
     * @return int[]
     */
    public function colorBorder() : array
    {
        return $this->colorBorder;
    }


}
