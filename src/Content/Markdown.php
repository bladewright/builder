<?php

namespace Bladewright\Content;

use Illuminate\Contracts\Config\Repository as Config;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

/**
 * Turn body Markdown into HTML.
 *
 * Rich text wrecks a design because the shape is "anything goes, and we
 * forbid things afterwards". With Markdown, **the block decides what may
 * be written into a field.** The room to break something belongs to the
 * design, not to the writer.
 *
 * The safe choices are not switchable. Make them settings and somebody
 * will open them. If you need HTML, use an html field rather than markdown
 * (developers only).
 */
class Markdown
{
    /** The syntax that can be declared. */
    public const FEATURES = [
        'heading', 'link', 'image', 'list', 'quote', 'code', 'table', 'rule', 'bold', 'italic',
    ];

    public function __construct(private readonly Config $config) {}

    /**
     * @param  array<int, string>|null  $allow  Syntax to permit; null takes the configured default
     * @param  int|null  $headingOffset  How far to push headings down
     */
    public function render(string $markdown, ?array $allow = null, ?int $headingOffset = null): string
    {
        $allow = $allow ?? $this->config->get('bladewright.markdown.allow', self::FEATURES);
        $headingOffset = $headingOffset ?? (int) $this->config->get('bladewright.markdown.heading_offset', 2);

        $environment = new Environment([
            // Strip raw HTML. The default lets it through, and an onerror
            // attribute would run.
            'html_input' => 'strip',
            // Disable javascript: links and their friends. The default
            // allows them.
            'allow_unsafe_links' => false,
            // Deep nesting breaks the container.
            'max_nesting_level' => (int) $this->config->get('bladewright.markdown.max_nesting_level', 4),
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);

        if (in_array('table', $allow, true)) {
            $environment->addExtension(new TableExtension);
        }

        $document = (new MarkdownParser($environment))->parse($markdown);

        $this->constrain($document, $allow, $headingOffset);

        return (new HtmlRenderer($environment))->renderDocument($document)->getContent();
    }

    /**
     * Drop syntax that is not allowed, and push the headings down.
     *
     * **The words stay; only the decoration goes.** To the writer, losing
     * the bold and losing the sentence are not the same size of accident.
     */
    private function constrain(Node $document, array $allow, int $headingOffset): void
    {
        $walker = $document->walker();
        $pending = [];

        while ($event = $walker->next()) {
            if (! $event->isEntering()) {
                continue;
            }

            $node = $event->getNode();
            $feature = $this->featureOf($node);

            if ($feature === null) {
                continue;
            }

            if (! in_array($feature, $allow, true)) {
                $pending[] = $node;

                continue;
            }

            if ($node instanceof Heading && $headingOffset > 0) {
                // Keeps a page from ending up with two h1s. The block
                // decides "body text starts at h3" and everything is pushed
                // down, whatever level was written.
                $node->setLevel(min(6, $node->getLevel() + $headingOffset));
            }
        }

        foreach ($pending as $node) {
            $this->strip($node);
        }
    }

    private function featureOf(Node $node): ?string
    {
        return match (true) {
            $node instanceof Heading => 'heading',
            $node instanceof Link => 'link',
            $node instanceof Image => 'image',
            $node instanceof ListBlock => 'list',
            $node instanceof BlockQuote => 'quote',
            $node instanceof FencedCode, $node instanceof IndentedCode, $node instanceof Code => 'code',
            $node instanceof ThematicBreak => 'rule',
            $node instanceof Strong => 'bold',
            $node instanceof Emphasis => 'italic',
            default => null,
        };
    }

    /**
     * Take the decoration off and keep what was inside.
     */
    private function strip(Node $node): void
    {
        if ($node->parent() === null) {
            return;
        }

        if ($node instanceof Image) {
            // An image leaves only its alt text behind, and vanishes
            // entirely when it has none.
            $text = $this->textOf($node);
            $node->replaceWith($text === '' ? new Text('') : new Text($text));

            return;
        }

        if ($node instanceof ThematicBreak) {
            $node->detach();

            return;
        }

        if ($node instanceof Heading || $node instanceof FencedCode || $node instanceof IndentedCode) {
            $paragraph = new Paragraph;
            $paragraph->appendChild(new Text($this->textOf($node)));
            $node->replaceWith($paragraph);

            return;
        }

        // Inline decoration, and containers such as lists and quotes, spill
        // their children in place and step out themselves.
        foreach ($node->children() as $child) {
            $node->insertBefore($child);
        }

        $node->detach();
    }

    private function textOf(Node $node): string
    {
        if ($node instanceof Image) {
            $parts = [];
            foreach ($node->children() as $child) {
                $parts[] = $this->textOf($child);
            }

            return trim(implode('', $parts));
        }

        if ($node instanceof Text) {
            return $node->getLiteral();
        }

        if ($node instanceof FencedCode || $node instanceof IndentedCode) {
            return $node->getLiteral();
        }

        $parts = [];

        foreach ($node->children() as $child) {
            $parts[] = $this->textOf($child);
        }

        return implode('', $parts);
    }
}
