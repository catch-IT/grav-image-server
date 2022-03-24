<?php
/**
 * This plugin sets the srcset and size attribute of images using Grav internal methods.
 *
 * Licensed under MIT, see LICENSE.
 */

namespace Grav\Plugin;

use Exception;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageFile;
use Grav\Common\Plugin;
use JetBrains\PhpStorm\ArrayShape;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

class ImageServerPlugin extends Plugin
{
    public const FALLBACK = [
        'breakpoints' => [],
        'densitySet' => [
            [
                'density' => 1,
                'quality' => 82
            ]
        ],
        'maxWidth' => 1920,
        'loading' => null,
        'class' => null
    ];

    #[ArrayShape(['onPluginsInitialized' => "array"])]
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        $this->enable([
            'onOutputGenerated' => ['onOutputGenerated', 1],
            'onTwigInitialized' => ['onTwigInitialized', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
        ]);
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig()->addFilter(
            new Twig_SimpleFilter('picture', [$this, 'picture'])
        );
        $this->grav['twig']->twig()->addFunction(
            new Twig_SimpleFunction('picture', [$this, 'picture'])
        );
    }


    private function generatePictureMarkup($file, string $alt = '', ?float $ratio = null, ?array $breakpoints = null, ?int $maxWidth = null, ?string $loading = null, ?string $class = null, ?string $title = null, ?array $densitySet = null): string
    {
        if (!empty($breakpoints)) {
            $sortColumn = array_column($breakpoints, 'breakpoint');
            array_multisort($sortColumn, SORT_DESC, SORT_NUMERIC, $breakpoints);
        }

        $file = ltrim($file, '/');

        try {
            [$width, $height] = getimagesize($file);
        } catch (Exception) {
            if ($this->config->get('plugins.image-server.log', true)) {
                $this->grav['log']->notice('Image missing: ' . $file . ' Page: ' . $this->grav['uri']->url());
            }

            if ($this->config->get('plugins.image-server.errorImage', false)) {
                return '<img class="error--missing-image ' . $class . '" src=\'data:image/svg+xml;utf8,<svg width="100%" height="100%" viewBox="0 0 86.591003 53.525999" xmlns="http://www.w3.org/2000/svg"><rect x="0" y="0" width="100%" height="100%" fill="white" stroke="red" /><text xml:space="preserve" style="line-height:125%;text-align:center;fill:black" x="86.31" y="17.66" font-weight="300" font-size="10.58" text-anchor="middle"><tspan style="text-align:center" x="43.12" y="17.66">Error</tspan><tspan x="43.12" y="30.89">missing image</tspan><tspan x="43.12" y="43.89" font-size="3.58">' . htmlspecialchars($file) . '</tspan></text></svg>\' />';
            }

            return '';
        }

        $ratio = $ratio ?? (float)($height / $width);
        $maxWidth = min($maxWidth, $width);
        $maxCalHeight = round($maxWidth * $ratio);

        $val = '<picture>';
        $val .= '<source' . ($breakpoints ? ' media="(min-width: ' . current($breakpoints)['breakpoint'] + 1 . 'px)"' : '') . ' srcset="' . $this->generateSourceMarkup($file, $width, $maxWidth, $maxCalHeight, $densitySet) . '" type="image/webp">';

        foreach ($breakpoints as $imageWidth) {
            if ($imageWidth['imageWidth'] <= $width) {
                $calHeight = round($imageWidth['imageWidth'] * $ratio);
                $val .= '<source ' . (next($breakpoints) ? ' media="(min-width: ' . current($breakpoints)['breakpoint'] + 1 . 'px)"' : '') . ' srcset="' . $this->generateSourceMarkup($file, $width, $imageWidth['imageWidth'], $calHeight, $densitySet) . '" type="image/webp" />';
                $maxWidth = max($imageWidth['imageWidth'], $maxWidth);
                $maxCalHeight = max($maxCalHeight, $calHeight);
            }
        }

        $val .= '<img src="' . self::getImage($file, 'guess', $this->config->get('system.images.default_image_quality', 82), $maxWidth, $maxCalHeight) . '"';
        foreach (['class', 'alt', 'title', 'width', 'height', 'loading'] as $attr) {
            if (isset(${$attr})) {
                $val .= ' ' . $attr . '="' . htmlspecialchars(${$attr}, ENT_COMPAT, 'UTF-8') . '"';
            }
        }
        return $val . ' /></picture>';
    }

    private function generateSourceMarkup(string $file, int $width, int $maxWidth, int $maxCalHeight, array $densitySets): string
    {
        $val = '';
        foreach ($densitySets as $densitySet) {
            if ($maxWidth * $densitySet['density'] <= $width) {
                $val .= (!empty($val) ? ', ' : '') .
                    self::getImage($file, 'webp', $densitySet['quality'], $maxWidth * $densitySet['density'], $maxCalHeight * $densitySet['density']) . ' ' . $densitySet['density'] . 'x';
            }
        }
        return $val;
    }

    public function picture($imageName, string $alt = '', ?string $title = null, ?string $preset = null, ?string $loading = null, ?float $ratio = null, ?array $breakpoints = null, ?int $maxWidth = null, ?string $class = null, ?array $densitySet = null): string
    {
        $this->applyPreset($preset, $loading, $ratio, $breakpoints, $maxWidth, $class, $densitySet);
        $this->applyDefault($loading, $breakpoints, $maxWidth, $class, $densitySet);

        if (str_ends_with($imageName, 'svg')) {
            $val = '<picture><img src="' . $imageName . '"';
            foreach (['class', 'alt', 'title', 'width', 'height', 'loading'] as $attr) {
                if (isset(${$attr})) {
                    $val .= ' ' . $attr . '="' . htmlspecialchars(${$attr}, ENT_COMPAT, 'UTF-8') . '"';
                }
            }
            return $val . ' /></picture>';
        }

        return $this->generatePictureMarkup($imageName, $alt, $ratio, $breakpoints, $maxWidth, $loading, $class, $title, $densitySet);
    }

    /**
     * Unfortunately the 'derivatives' function in markdown does not set the size attribute to the right value. This is done here after all the page is fully rendered to html.
     */
    public function onOutputGenerated(): void
    {
        if (!$this->config->get('plugins.image-server.enabledMarkdown', true)) {
            return;
        }

        $raw = $this->grav->output;

        if ($this->config->get('plugins.image-server.removeWrapper', true)) {
            $raw = preg_replace('/<p>\s*(<img[\w\W]+?\/?>|<picture>.*<\/picture>)\s*<\/p>/', '$1', $raw);
        }

        $raw = preg_replace_callback('/<(?<start>img.*)src="(?<image>' . ($this->config->get('plugins.image-server.filterFolder', true) ? '(?!\/themes\/|\/images\/)' : '') . '.*\.(?<image_format>jpe?g|png' . ($this->config->get('plugins.image-server.svg', false) ? '|svg' : '') . '))\??(?<query_string>(?!\?)\S+)?"(?<end>[^>]*)>(?!<\/picture>)/iU', function ($match) {
            $alt = '';
            $title = null;

            preg_match_all('/(?<name>title|alt|class|width|height)=[\'"](?<value>.*)[\'"]/Um', $match['start'] . $match['end'], $attrs, PREG_SET_ORDER);

            foreach ($attrs as $attr) {
                ${$attr['name']} = htmlspecialchars_decode($attr['value'], ENT_COMPAT);
            }

            parse_str($match['query_string'], $queryString);

            if (!empty($queryString['preset'])) {
                if (strtolower($queryString['preset']) === 'noimageserver') {
                    return str_ireplace(['?preset=noImageServer', 'preset=noImageServer'], '', $match[0]);
                }

                $this->applyPreset($queryString['preset'], $loading, $ratio, $breakpoints, $maxWidth, $class, $densitySet);
            }

            $this->applyDefault($loading, $breakpoints, $maxWidth, $class, $densitySet);

            if ($match['image_format'] === 'svg') {
                $val = '<picture><img src="' . $match['image'] . '"';
                foreach (['class', 'alt', 'title', 'width', 'height', 'loading'] as $attr) {
                    if (isset(${$attr})) {
                        $val .= ' ' . $attr . '="' . htmlspecialchars(${$attr}, ENT_COMPAT, 'UTF-8') . '"';
                    }
                }
                return $val . ' /></picture>';
            }

            return $this->generatePictureMarkup($match['image'], $alt, $ratio ?? null, $breakpoints ?? null, $maxWidth ?? null, $loading ?? null, $class ?? null, $title, $densitySet ?? null);
        }, $raw);

        $this->grav->output = $raw;
    }

    private static function getImage(string $file, string $format, int $quality, int $width, int $height): string
    {
        $locator = Grav::instance()['locator'];
        $cacheDir = $locator->findResource('cache://images', true) ?: $locator->findResource('cache://images', true, true);

        $image = (ImageFile::open($file))
            ->setCacheDir($cacheDir)
            ->setActualCacheDir($cacheDir);
        $image->zoomCrop($width, $height);
        return str_replace($cacheDir, 'images', $image->cacheFile($format, $quality));
    }

    private function applyPreset($preset, &$loading, &$ratio, &$breakpoints, &$maxWidth, &$class, &$densitySet): void
    {
        if ($preset && $config = $this->config->get('plugins.image-server.sets.' . $preset, false)) {
            if (!$loading && !empty($config["loading"]) && $config["loading"] !== "unset") {
                $loading = $config["loading"];
            }

            foreach (['breakpoints', 'maxWidth', 'ratio', 'class', 'densitySet'] as $attr) {
                if (!${$attr} && !empty($config[$attr])) {
                    ${$attr} = $config[$attr];
                }
            }

            if (empty($breakpoints) && isset($config["breakpointsInherited"]) && !$config["breakpointsInherited"]) {
                $breakpoints = 'breakpointsInherited';
            }
        }
    }

    private function applyDefault(&$loading, &$breakpoints, &$maxWidth, &$class, &$densitySet): void
    {
        foreach (['loading', 'breakpoints', 'maxWidth', 'class', 'densitySet'] as $attr) {
            if (empty(${$attr})) {
                ${$attr} = $this->config->get('plugins.image-server.' . $attr, self::FALLBACK[$attr]);
            }
        }

        if ($breakpoints === 'breakpointsInherited') {
            $breakpoints = self::FALLBACK['breakpoints'];
        }
    }
}
