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
use Twig_SimpleFilter;
use Twig_SimpleFunction;

class ImageServerPlugin extends Plugin
{
    // Constants as Fallbacks, if the plugin isn't configured
    public const DEFAULT_BREAKPOINTS = [768, 480];
    public const DEFAULT_MAX_WIDTH = 1920;
    public const DEFAULT_LOADING = 'auto';

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


    private function generatePictureMarkup($file, string $alt = '', ?float $ratio = null, ?array $breakpoints = null, ?int $maxWidth = null, ?string $loading = null, ?string $class = null, ?string $title = null): string
    {
        $maxDensity = $this->config->get('plugins.image-server.density');
        $maxWidth = $maxWidth ?: $this->config->get('plugins.image-server.maxPageWidth', self::DEFAULT_MAX_WIDTH);
        $breakpoints = $breakpoints ?: $this->config->get('plugins.image-server.breakpoints', self::DEFAULT_BREAKPOINTS);

        $file = ltrim($file, '/');

        try {
            [$width, $height] = getimagesize($file);
        } catch (Exception) {
            return '<img class="error--missing-image" src="' . $file . '" />';
        }

        $ratio = $ratio ?? (float)($height / $width);
        $maxWidth = min($maxWidth, $width);
        $maxCalHeight = round($maxWidth * $ratio);

        $val = '<picture>';
        $val .= '<source media="(min-width: ' . reset($breakpoints) + 1 . 'px)"  srcset="' . $this->generateSourceMarkup($file, $width, $maxWidth, $maxCalHeight, $maxDensity) . '" type="image/webp">';

        foreach ($breakpoints as $imageWidth => $pageWidth) {
            if ($imageWidth <= $width) {
                $calHeight = round($imageWidth * $ratio);
                $val .= '<source ' . (next($breakpoints) ? ' media="(min-width: ' . current($breakpoints) + 1 . 'px)"' : '') . 'srcset="' . $this->generateSourceMarkup($file, $width, $imageWidth, $calHeight, $maxDensity) . '" type="image/webp">';
                $maxWidth = max($imageWidth, $maxWidth);
                $maxCalHeight = max($maxCalHeight, $calHeight);
            }
        }

        $val .= '<img'
            . ($alt ? ' alt="' . htmlspecialchars($alt) . '"' : '')
            . ($title ? ' title="' . htmlspecialchars($title) . '"' : '')
            . '  loading="' . ($loading ?? $this->config->get('plugins.image-server.loading', self::DEFAULT_LOADING)) . '" width="' . $maxWidth . '" height="' . $maxCalHeight . '" src="' . self::getImage($file, 'guess', $this->config->get('system.images.default_image_quality', 82), $maxWidth, $maxCalHeight) . '" class="' . ($class ?: $this->config->get('plugins.image-server.class', '')) . '">';
        return $val . '</picture>';
    }

    private function generateSourceMarkup(string $file, int $width, int $maxWidth, int $maxCalHeight, int $maxDensity): string
    {
        $val = self::getImage($file, 'webp', $this->config->get('system.images.default_image_quality', 82), $maxWidth, $maxCalHeight) . ' 1x';
        for ($density = 2; $density <= $maxDensity; $density++) {
            if ($maxWidth * $density <= $width) {
                $val .= ', ' . self::getImage($file, 'webp', $this->config->get('plugins.image-server.quality.' . $density . 'x'), $maxWidth * $density, $maxCalHeight * $density) . ' ' . $density . 'x';
            }
        }

        return $val;
    }

    public function picture($imageName, string $alt = '', ?string $title = null, ?string $loading = null, ?string $preset = null, ?float $ratio = null, ?array $breakpoints = null, ?int $maxWidth = null, ?string $class = null): string
    {
        $this->getPreset($preset, $ratio, $breakpoints, $maxWidth, $loading, $class);
        return $this->generatePictureMarkup($imageName, $alt, $ratio, $breakpoints, $maxWidth, $loading, $class, $title);
    }

    /**
     * Unfortunately the 'derivatives' function in markdown does not set the size attribute to the right value. This is done here after all the page is fully rendered to html.
     */
    public function onOutputGenerated(): void
    {
        $raw = $this->grav->output;

        $raw = preg_replace('/<p>(<img[\w\W]+?\/?>)<\/p>/iU', '$1', $raw);

        $raw = preg_replace_callback('/<(?<start>img.*)src=".*(?<image>user\/.*\.jpe?g|.*\.png)(?<query_string>\?[\w=&]+)?"(?<end>.*)>/iU', function ($match) {
            $alt = null;
            $title = null;

            preg_match_all('/(?<name>title|alt|class)=[\'"](?<value>.*)[\'"]/Um', $match['start'] . $match['end'], $attrs, PREG_SET_ORDER);

            foreach ($attrs as $attr) {
                ${$attr['name']} = $attr['value'];
            }

            parse_str($match['query_string'], $result);
            if (!empty($result['preset'])) {
                $this->getPreset($result['preset'], $ratio, $breakpoints, $maxWidth, $loading, $class);
            }

            return $this->generatePictureMarkup($match['image'], $alt ?? null, $ratio ?? null, $breakpoints ?? null, $maxWidth ?? null, $loading ?? null, $class ?? null, $title ?? null);
        }, $raw);

        $this->grav->output = $raw;
    }

    public static function getImage(string $file, string $format, int $quality, int $width, int $height): string
    {
        $locator = Grav::instance()['locator'];
        $cacheDir = $locator->findResource('cache://images', true) ?: $locator->findResource('cache://images', true, true);

        $image = (ImageFile::open($file))
            ->setCacheDir($cacheDir)
            ->setActualCacheDir($cacheDir);
        $image->zoomCrop($width, $height);
        return str_replace($cacheDir, 'images', $image->cacheFile($format, $quality));
    }

    private function getPreset($preset, &$ratio, &$breakpoints, &$maxWidth, &$loading, &$class): void
    {
        if ($preset && $config = $this->config->get('plugins.image-server.sets.' . $preset, false)) {
            if (!$breakpoints && !empty($config["breakpoints"])) {
                $breakpoints = $config["breakpoints"];
            }

            if (!$maxWidth && !empty($config["maxWidth"])) {
                $maxWidth = $config["maxWidth"];
            }

            if (!$loading && !empty($config["loading"]) && $config["loading"] !== "unset") {
                $loading = $config["loading"];
            }

            if (!$ratio && !empty($config["ratio"])) {
                $ratio = $config["ratio"];
            }

            if (!$class && !empty($config["class"])) {
                $class = $config["class"];
            }
        }
    }
}
