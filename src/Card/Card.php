<?php

namespace Dilling\PostItPrinter\Card;

class Card
{
    /** @var CardTemplate */
    protected $template;

    /** @var string */
    protected $title;

    /** @var string */
    protected $type;

    /** @var string[] */
    protected $labels;

    /** @var int */
    protected $points;

    /**
     * @param CardTemplate $template
     * @param string $title
     * @param string $type
     * @param string[] $labels
     * @param int $points
     */
    public function __construct(CardTemplate $template, string $title, string $type, array $labels, int $points)
    {
        $this->template = $template;
        $this->title = $title;
        $this->type = $type;
        $this->labels = $labels;
        $this->points = $points;
    }

    public function template() : CardTemplate
    {
        return $this->template;
    }

    public function title() : string
    {
        return $this->title;
    }

    public function type() : string
    {
        return $this->type;
    }

    /**
     * @return string[]
     */
    public function labels() : array
    {
        return $this->labels;
    }

    public function points() : int
    {
        return $this->points;
    }

    public function width() : float
    {
        return $this->template->width();
    }

    public function height() : float
    {
        return $this->template->height();
    }
}
