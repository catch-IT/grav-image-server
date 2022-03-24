# Image server plugin

The image server plugin allows you to add responsive images via config without extra elements.

## Features

* WebP Images
* Lazy loading via attribute
* Responsive images
* Retina images in different quality levels
* Adds width and height
* Add default class (often needed for responsive images)
* Configure sets
    * Allows adding different breakpoints and max width per set
    * Add a ratio e.g. header or teaser image
    * Overwrite loading and class
    * Used via parameter or twig/template
* Works in Page Content (Markdown), templates (via twig or helper **template**)
* All that is helpful for faster websites especially on mobile
* Add a standard class and loading to SVG (option)
* Remove `<p>`-wrapper (option)
* Log or/and show error image for development and finding missing images (optional)

## Links

- [Documentation](https://docs.catchit.xyz/grav-plugins/image-server/base/)
- [Frontend demo](https://image-server.grav.catchit.xyz/)

### Markdown example

```markdown
![alt text](test.jpg "title text")
```

### Result

```html

<picture>
    <source media="(min-width: 769px)" srcset="image.webp 1x, image.webp 2x, image.webp 3x" type="image/webp">
    <source media="(min-width: 481px)" srcset="image.webp 1x, image.webp 2x, image.webp 3x" type="image/webp">
    <source srcset="image.webp 1x, image.webp 2x, image.webp 3x" type="image/webp">
    <img alt="alt text" title="title text" loading="lazy" src="image.jpg" class="img-fluid" width="1296" height="864">
</picture>
```

## Support

For **live chatting**, please use [Discord Chat Room](https://getgrav.org/discord) - username xf for discussions
directly related to the plugin.

For **bugs, features, improvements**, please ensure
you [create issues in the GitHub repository](https://github.com/catch-IT/grav-image-server/issues).

Take a look into [Documentation](https://docs.catchit.xyz/grav-plugins/image-server/base/).

## Installation

Like any other normal plugin, and it uses internal image processing.
