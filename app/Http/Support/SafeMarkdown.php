<?php

namespace App\Http\Support;

/**
 * Renders AI-generated text as HTML. Everything is escaped first; only bold, simple lists,
 * paragraphs and http(s) links are re-introduced, so the output is safe to print unescaped.
 */
class SafeMarkdown
{
    public static function render(?string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
        if ($text === '') {
            return '';
        }

        $html = [];
        $list = null;
        $paragraph = [];

        $flushParagraph = function () use (&$html, &$paragraph) {
            if ($paragraph) {
                $html[] = '<p>'.implode('<br>', $paragraph).'</p>';
                $paragraph = [];
            }
        };
        $closeList = function () use (&$html, &$list) {
            if ($list) {
                $html[] = "</{$list}>";
                $list = null;
            }
        };

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                $flushParagraph();
                $closeList();

                continue;
            }

            $type = null;
            $content = null;
            if (preg_match('/^[-*•]\s+(.+)$/u', $line, $m)) {
                [$type, $content] = ['ul', $m[1]];
            } elseif (preg_match('/^\d{1,2}[.)]\s+(.+)$/u', $line, $m)) {
                [$type, $content] = ['ol', $m[1]];
            }

            if ($type) {
                $flushParagraph();
                if ($list !== $type) {
                    $closeList();
                    $html[] = "<{$type}>";
                    $list = $type;
                }
                $html[] = '<li>'.self::inline($content).'</li>';

                continue;
            }

            $closeList();
            $paragraph[] = self::inline(preg_replace('/^#{1,6}\s+/', '', $line));
        }

        $flushParagraph();
        $closeList();

        return implode('', $html);
    }

    private static function inline(string $text): string
    {
        $text = e($text);

        $text = preg_replace_callback(
            '/\[([^\]]{1,200})\]\((https?:\/\/[^\s)<>"\']{1,2000})\)/i',
            fn ($m) => '<a href="'.$m[2].'" target="_blank" rel="nofollow noopener noreferrer ugc">'.$m[1].'</a>',
            $text,
        );

        return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    }
}
