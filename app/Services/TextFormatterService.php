<?php

namespace App\Services;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use s9e\TextFormatter\Bundles\Forum as ForumBundle;

class TextFormatterService
{
    protected GithubFlavoredMarkdownConverter $markdown;

    public function __construct()
    {
        $this->markdown = new GithubFlavoredMarkdownConverter([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Extract and stash BBCode tags before Markdown processing,
     * then restore and render them via s9e.
     */
    public function renderFromText(string $text): string
    {
        // Handle [lock=N]...[/lock] — render as blurred locked content div
        $text = preg_replace_callback(
            '/\[lock(?:=(\d+))?\](.*?)\[\/lock\]/si',
            function ($m) {
                $cost = $m[1] ?: \App\Models\ForumConfig::get('locked_content_default_cost', '50');
                $inner = htmlspecialchars($m[2]);
                $hash = hash('sha256', $m[2]);
                return sprintf(
                    '<div class="locked-content" data-hash="%s" data-cost="%d"><div class="locked-content-inner">%s</div></div>',
                    $hash, (int)$cost, $inner
                );
            },
            $text
        );

        // 1. Pull out BBCode blocks so CommonMark doesn't mangle them
        $placeholders = [];
        $counter = 0;
        $protected = preg_replace_callback(
            '/\[(?:b|i|u|s|color|size|spoiler|img|media|code|quote|list|\*)[^\]]*\].*?\[\/(?:b|i|u|s|color|size|spoiler|img|media|code|quote|list)[^\]]*\]/si',
            function ($m) use (&$placeholders, &$counter) {
                $key = "\x00BBCODE{$counter}\x00";
                $placeholders[$key] = $m[0];
                $counter++;
                return $key;
            },
            $text
        );

        // 2. Run remaining text through CommonMark (handles ##, **, >, ```, etc.)
        $html = (string) $this->markdown->convert($protected);

        // 3. Restore BBCode placeholders
        foreach ($placeholders as $key => $bbcode) {
            // Render the BBCode via s9e and inline the result
            try {
                $rendered = ForumBundle::render(ForumBundle::parse($bbcode));
            } catch (\Throwable) {
                $rendered = e($bbcode);
            }
            $html = str_replace($key, $rendered, $html);
        }

        // Render @mentions as profile links
        $html = preg_replace(
            '/@(\w+)/',
            '<a href="/profile/$1" class="mention">@$1</a>',
            $html
        );

        return $html;
    }

    /**
     * Raw s9e parse/render — used for pure BBCode content.
     */
    public function render(string $xml): string
    {
        return ForumBundle::render($xml);
    }

    public function parse(string $text): string
    {
        return ForumBundle::parse($text);
    }

    public function unparse(string $xml): string
    {
        return ForumBundle::unparse($xml);
    }
}
