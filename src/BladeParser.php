<?php
namespace Lubed\BladeParser;

use Lubed\Template\TemplateParser;

final class BladeParser implements TemplateParser
{
    const BEGIN='{{';
    const END='}}';
    public function parse(string $content, $handler) : string {
        $result = $this->parseBaseTags($content,$handler);
        $result = $this->parseNotes($result);
        $result = $this->parseOthers($result);
        return $result;
    }

    private function parseBaseTags(string $content, $handler)
    {
        $fnCallback=function($match) use ($handler) {
            $cmd=isset($match[1]) ? $match[1] : '';
            $paras=isset($match[3]) ? $match[3] : '';
            $result=sprintf('<?php %s %s :?>', $cmd, $paras);
            if (false !== in_array($cmd, ['layout', 'section', 'load', 'place', 'url'])) {
                $method=$cmd;
                $prefix='';
                if ('layout' === $cmd || 'load' === $cmd) {
                    $method='load';
                }
                if ('section' === $cmd) {
                    $method='beginBlock';
                }
                if ('url' === $cmd) {
                    $prefix='echo ';
                }
                return sprintf('<?php %s%s->%s%s;?>', $prefix, $handler, $method, $paras);
            }
            if ('end' === substr($cmd, 0, 3)) {
                if ('endsection' === $cmd) {
                    return sprintf('<?php %s->endBlock();?>', $handler);
                }
                $result=sprintf('<?php %s%s;?>', $cmd, $paras);
            }
            return $result;
        };
        $pattern = '/\B@(\w+)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x';
        return preg_replace_callback($pattern, $fnCallback, $content);
    }

    private function parseNotes(string $value)
    {
        $pattern = sprintf('/%s--((.|\s)*?)--%s/', self::BEGIN, self::END);
        return preg_replace($pattern, '<?php /*$1*/ ?>', $value);
    }

    private function parseOthers(string $value) {
        $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', self::BEGIN,  self::END);
        $callback = function ($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];
            $wrapped = (isset($matches[3]) ? '' : 'echo ') . preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/s', 'isset($1) ? $1 : $2', $matches[2]);
            return $matches[1] ? substr($matches[0], 1) : '<?php ' . $wrapped . '; ?>' . $whitespace;
        };

        return preg_replace_callback($pattern, $callback, $value);
    }
}
