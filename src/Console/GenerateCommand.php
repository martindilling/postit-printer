<?php

namespace Dilling\PostItPrinter\Console;

use PhpSchool\CliMenu\CliMenu;
use Dilling\PostItPrinter\Card\Card;
use Dilling\PostItPrinter\CardsPage;
use Dilling\PostItPrinter\Pdf\Document;
use Dilling\PostItPrinter\TemplatePage;
use Dilling\PostItPrinter\Pivotal\Client as PivotalClient;
use PhpSchool\CliMenu\Action\GoBackAction;
use PhpSchool\CliMenu\MenuItem\StaticItem;
use GuzzleHttp\Exception\RequestException;
use Dilling\PostItPrinter\Card\CardTemplate;
use PhpSchool\CliMenu\MenuItem\MenuMenuItem;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\MenuItem\LineBreakItem;
use PhpSchool\CliMenu\MenuItem\SelectableItem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PhpSchool\CliMenu\Exception\InvalidTerminalException;

class GenerateCommand extends Command
{
    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    /** @var string */
    private $basePath;

    /** @var PivotalClient */
    private $client;

    /** @var string */
    private $token;

    /** @var int */
    private $projectId;

    /** @var int */
    private $afterStoryId;

    /** @var int */
    private $beforeStoryId;

    /** @var string */
    private $size;

    /** @var array */
    private $_projects_cache;

    /** @var array */
    private $_stories_cache;

    const OUTPUT_PATH = '/';
    const SETTINGS_PATH = '/.settings';
    const NOT_DEFINED = '[not defined]';

    const SIZE = [
        '76x76' => [
            'width' => 76,
            'height' => 76,
            'rows' => 2,
            'cols' => 3,
        ],
        '127x76' => [
            'width' => 127,
            'height' => 76,
            'rows' => 2,
            'cols' => 2,
        ],
    ];

    const LABEL = [
        'size' => 'Size:',
        'token' => 'Token:',
        'project' => 'Project:',
        'after_story' => 'After story:',
        'before_story' => 'Before story:',
    ];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        parent::__construct();
    }

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('generate')
            ->setDescription('Create pdf with the Pivotal tasks')
            ->addArgument('name', InputArgument::OPTIONAL)
            ->addOption('size', 's', InputOption::VALUE_OPTIONAL, 'What size of post-its is used')
            ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'Pivotal api token')
            ->addOption('project', 'p', InputOption::VALUE_OPTIONAL, 'Pivotal project id')
            ->addOption('after', 'a', InputOption::VALUE_OPTIONAL, 'Stories after this id')
            ->addOption('before', 'b', InputOption::VALUE_OPTIONAL, 'Stories before this id');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     * @throws InvalidTerminalException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $settings = [];
        if (file_exists($this->basePath . self::SETTINGS_PATH)) {
            $settings = json_decode(\file_get_contents($this->basePath . self::SETTINGS_PATH), true);
        }

        $this->token = (string) $input->getOption('token') ?: $settings['token'] ?? null; // 'fe1495d14eb0d263b42fd9199f203568'
        $this->projectId = (int) $input->getOption('project') ?: $settings['projectId'] ?? null; // 738091
        $this->afterStoryId = (int) $input->getOption('after') ?: $settings['afterStoryId'] ?? null; // 166930136
        $this->beforeStoryId = (int) $input->getOption('before') ?: $settings['beforeStoryId'] ?? null; //166268119
        $this->size = (string) $input->getOption('size') ?: $settings['size'] ?? null; // '76x76'
        $this->client = $this->token ? new PivotalClient($this->token) : null;

        $this->buildMenu();
    }

    /**
     * @throws InvalidTerminalException
     */
    public function buildMenu()
    {
        $self = $this;

        $menuBuilder = ($builder = new CliMenuBuilder)
            ->setTitle($this->getApplication()->getName() . ' ' . $this->getApplication()->getVersion());

        $sizeSubmenu = function (CliMenuBuilder $b) use ($self) {
            $b->setTitle('Select size');

            foreach (self::SIZE as $size => $details) {
                $b->addItem($self->presentSize($details), function (CliMenu $menu) use ($self, $size) {
                    $self->size = $size;

                    $self->updateItemsWithData($menu->getParent(), $menu);
                });
            }
            $b->addLineBreak('-');
        };
        $tokenCallback = function (CliMenu $menu) {
            $this->token = $menu->askText()
                                ->setValidator(function ($value) {
                                    return true;
                                })
                                ->setPromptText('Enter your token')
                                ->setPlaceholderText($this->token ?? 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                                ->ask()
                                ->fetch();
            $this->client = $this->token ? new PivotalClient($this->token) : null;
            $this->projectId = null;
            $this->afterStoryId = null;
            $this->beforeStoryId = null;

            $this->updateItemsWithData($menu);
        };
        $projectSubmenu = function (CliMenuBuilder $b) use ($self) {
            $b->setTitle('Select project');
            // This submenu will be by `$this->buildProjectsMenu()`
        };
        $afterCallback = function (CliMenu $menu) {
            if (!$this->token) {
                return $this->errorFlash($menu, 'No token defined.');
            }
            if (!$this->projectId) {
                return $this->errorFlash($menu, 'No project defined.');
            }

            $after = $menu->askText()
                          ->setValidator(function ($value) {
                              return true;
                          })
                          ->setPromptText('Enter story id')
                          ->setPlaceholderText((string) $this->afterStoryId)
                          ->ask()
                          ->fetch();
            $this->afterStoryId = \ltrim($after, '#') ?: null;

            $this->updateItemsWithData($menu);
        };
        $beforeCallback = function (CliMenu $menu) {
            if (!$this->token) {
                return $this->errorFlash($menu, "               No token defined.               ");
            }
            if (!$this->projectId) {
                return $this->errorFlash($menu, "               No project defined.               ");
            }

            $before = $menu->askText()
                           ->setValidator(function ($value) {
                               return true;
                           })
                           ->setPromptText('Enter story id')
                           ->setPlaceholderText((string) $this->beforeStoryId)
                           ->ask()
                           ->fetch();
            $this->beforeStoryId = \ltrim($before, '#') ?: null;

            $this->updateItemsWithData($menu);
        };

        $menuBuilder->addMenuItem($selected = new SelectableItem('Generate Cards', function (CliMenu $menu) {
            $result = $this->generatePdf();
            if (\is_string($result)) {
                return $this->errorFlash($menu, $result);
            };
        }));
        $menuBuilder->addMenuItem(new SelectableItem('Generate Template', function (CliMenu $menu) {
            $this->generateTemplate();
        }));

        $menuBuilder->addLineBreak('-');

        // Make items without data to start with
        $menuBuilder->addSubMenu(self::LABEL['size'], $sizeSubmenu);
        $menuBuilder->addMenuItem(new SelectableItem(self::LABEL['token'], $tokenCallback));
        $menuBuilder->addSubMenu(self::LABEL['project'], $projectSubmenu);
        $menuBuilder->addMenuItem(new SelectableItem(self::LABEL['after_story'], $afterCallback));
        $menuBuilder->addMenuItem(new SelectableItem(self::LABEL['before_story'], $beforeCallback));

        $menuBuilder->addLineBreak('-');

        $menuBuilder->addMenuItem(new SelectableItem('Save settings', function () {
            file_put_contents($this->basePath . self::SETTINGS_PATH, \json_encode([
                'size' => $this->size,
                'token' => $this->token,
                'projectId' => $this->projectId,
                'afterStoryId' => $this->afterStoryId,
                'beforeStoryId' => $this->beforeStoryId,
            ], \JSON_PRETTY_PRINT));

            $this->output->writeln(
                '<comment>Settings saved. Will be loaded on next run.</comment>'
            );
        }));

        $menuBuilder->addLineBreak('-');
        $menuBuilder->setWidth($builder->getTerminal()->getWidth());
        $menuBuilder->setMarginAuto();
        $menuBuilder->setPadding(1, 2);
        $menuBuilder->setBorder(1, 2, 'white');
        $menu = $menuBuilder->build();
        // Update texts with our data
        $this->updateItemsWithData($menu, null, false);
        $menu->setSelectedItem($selected);
        $menu->open();
    }

    private function generateTemplate()
    {
        $pdf = new \TCPDF(
            $orientation = 'L',
            $unit = 'mm',
            $format = 'A4',
            $unicode = true,
            $encoding = 'UTF-8',
            $diskcache = false,
            $pdfa = false
        );
        $doc = new Document($pdf);

        $size = self::SIZE[$this->size];
        $filename = "template_{$this->size}.pdf";
        $template = new CardTemplate($size['width'], $size['height']);
        $rows = $size['rows'];
        $cols = $size['cols'];

        $doc->addPage(new TemplatePage(
            $template,
            $pageMargin = 15,
            $space = 10,
            $rows,
            $cols
        ));

        file_put_contents(getcwd() . self::OUTPUT_PATH . $filename, $doc->getContent());

        $this->output->writeln(
            '<comment>Generated the pdf template for ' .
            $this->size . ': ' .
            $filename .
            '</comment>'
        );
    }

    private function generatePdf()
    {
        if (!$this->token) {
            return 'No token defined';
        }
        if (!$this->projectId) {
            return 'No project selected';
        }

        try {
            $stories = $this->client->getStoriesBetween($this->projectId, $this->afterStoryId, $this->beforeStoryId);
        } catch (RequestException $e) {
            $response = \json_decode($e->getResponse()->getBody(), true);
            return "Failed getting stories: " . $response['error'] ?? 'unknown error';
        }

        $pdf = new \TCPDF(
            $orientation = 'L',
            $unit = 'mm',
            $format = 'A4',
            $unicode = true,
            $encoding = 'UTF-8',
            $diskcache = false,
            $pdfa = false
        );
        $doc = new Document($pdf);

        $timestamp = (new \DateTime())->format('Ymd-His');
        $size = self::SIZE[$this->size];
        $filename = "project-{$this->projectId}_{$timestamp}_{$this->size}.pdf";
        $template = new CardTemplate($size['width'], $size['height']);
        $rows = $size['rows'];
        $cols = $size['cols'];

        $cards = $stories->map(function ($story) use ($template) {
            return new Card(
                $template,
                $story['name'],
                \ucwords($story['story_type']),
                \array_map(function ($label) {
                    return $label['name'];
                }, $story['labels']),
                $story['estimate'] ?? 0
            );
        })->toArray();

        $chunked = \array_chunk($cards, $rows * $cols);

        foreach ($chunked as $chunk) {
            $doc->addPage(new CardsPage(
                $chunk,
                $pageMargin = 15,
                $space = 10,
                $rows,
                $cols
            ));
        }

        file_put_contents(getcwd() . self::OUTPUT_PATH . $filename, $doc->getContent());

        $this->output->writeln(
            '<comment>Generated the pdf with ' .
            \count($cards) . ' Pivotal tasks in ' .
            \count($chunked) . ' pages: ' .
            $filename .
            '</comment>'
        );

        return true;
    }

    private function updateItemsWithData(CliMenu $menu, ?CliMenu $activeSubMenu = null, bool $redraw = true)
    {
        foreach ($menu->getItems() as $item) {
            $menu->removeItem($item);
            switch (true) {
                case self::matchLabel(self::LABEL['size'], $item->getText()):
                    $item->setText(self::label('size', $this->presentSize(self::SIZE[$this->size])));
                    break;
                case self::matchLabel(self::LABEL['token'], $item->getText()):
                    $item->setText(self::label('token', $this->token));
                    break;
                case self::matchLabel(self::LABEL['project'], $item->getText()):
                    /** @var MenuMenuItem $item */
                    $item->setText(self::label('project', $this->presentProject($this->projectId)));
                    $this->buildProjectsMenu($item->getSubMenu());
                    break;
                case self::matchLabel(self::LABEL['after_story'], $item->getText()):
                    $item->setText(self::label('after_story', $this->presentStory($this->afterStoryId)));
                    break;
                case self::matchLabel(self::LABEL['before_story'], $item->getText()):
                    $item->setText(self::label('before_story', $this->presentStory($this->beforeStoryId)));
                    break;
            }
            $menu->addItem($item);
        }

        if ($activeSubMenu) {
            (new GoBackAction)($activeSubMenu);
        }

        if ($redraw) {
            $menu->redraw(true);
        }
    }

    private function buildProjectsMenu(CliMenu $menu)
    {
        $items = $menu->getItems();
        $exit = \array_pop($items);
        $goBack = \array_pop($items);

        foreach ($menu->getItems() as $item) {
            $menu->removeItem($item);
        }

        if (!$this->token) {
            $menu->addItem(new StaticItem(''));
            $menu->addItem(new StaticItem('No token defined'));
            $menu->addItem(new StaticItem(''));
        } else {
            foreach ($this->client->getProjects() as $project) {
                $projectId = $project['id'];
                $menu->addItem(new SelectableItem($this->presentProject($project['id']), function (CliMenu $menu) use ($projectId) {
                    $this->projectId = $projectId;
                    $this->afterStoryId = null;
                    $this->beforeStoryId = null;

                    $this->updateItemsWithData($menu->getParent(), $menu);
                }));
            }
        }

        $menu->addItem(new LineBreakItem('-'));

        $menu->addItem($goBack);
        $menu->addItem($exit);
        $menu->setSelectedItem($goBack);
    }

    private function presentStory($storyId)
    {
        if (!\is_numeric($storyId)) {
            return self::NOT_DEFINED;
        }
        if (!$this->token) {
            return 'No token defined so can\'t look for story';
        }
        if (!$this->projectId) {
            return 'No project defined so can\'t look for story';
        }

        try {
            $story = $this->_storys_cache[$storyId] ?? null;
            if (!$story) {
                $story = $this->_stories_cache[$storyId] = $this->client->getStory($this->projectId, $storyId);
            }
        } catch (RequestException $e) {
            $response = \json_decode($e->getResponse()->getBody(), true);
            return "Failed getting story id {$storyId}: " . $response['error'] ?? 'unknown error';
        }

        return "[{$story['id']}] {$story['name']}";
    }

    private function presentProject($projectId)
    {
        if (!\is_int($projectId)) {
            return self::NOT_DEFINED;
        }

        try {
            $project = $this->_projects_cache[$projectId] ?? null;
            if (!$project) {
                $project = $this->_projects_cache[$projectId] = $this->client->getProject($projectId);
            }
        } catch (RequestException $e) {
            $response = \json_decode($e->getResponse()->getBody(), true);
            return "Failed getting project id {$projectId}: " . $response['error'] ?? 'unknown error';
        }

        return "[{$project['id']}] {$project['name']}";
    }

    private function presentSize($details)
    {
        if (!\is_array($details)) {
            return self::NOT_DEFINED;
        }

        $count = $details['rows'] * $details['cols'];

        return "{$details['width']}x{$details['height']} - {$count} on each page";
    }

    private function errorFlash(CliMenu $menu, string $message)
    {
        $flash = $menu->flash('               ' . $message . '               ');
        $flash->getStyle()->setBg('red')->setFg('white');
        $flash->display();
    }

    private static function label($key, $value, $pad = 15)
    {
        return \str_pad(self::LABEL[$key], $pad) . ($value ?: self::NOT_DEFINED);
    }

    private static function matchLabel($needle, $haystack)
    {
        return \substr($haystack, 0, strlen($needle)) === $needle;
    }
}
