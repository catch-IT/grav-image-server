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

## Example/Demo
Frontend only - Includes different examples and output.
https://image-server.grav.catchit.xyz/

### Markdown

```markdown
![alt text](test.jpg "title text")
```

### Result
```html
<picture>
    <source media="(min-width: 769px)" srcset="image.webp 1x, image.webp 2x, image.webp 3x" type="image/webp">
    <source media="(min-width: 481px)" srcset="image.webp 1x, image.webp 2x, image.webp 3x" type="image/webp">
    <source srcset="image.webp 1x, image.webp 2x, image.webp 3x" type="image/webp">
    <img alt="alt text" title="title text" loading="lazy" src="images.jpeg" class="img-fluid" width="1296" height="864">
</picture>
```

## Support

For **live chatting**, please use [Discord Chat Room](https://getgrav.org/discord) - username xf for discussions directly related to the plugin.

For **bugs, features, improvements**, please ensure you [create issues in the GitHub repository](https://github.com/catch-IT/grav-image-server/issues).

## Installation

Like any other normal plugin, and it uses internal image processing.

## FAQ

### Why is the Quality so low for higher density (jpeg)?

A modern phone open uses a multiplier for fonts and other sizes because, normal 16px size (basic Browser 1rem size). Binary images can't be upscaled, compared to vector font. As result 1 pixel web would be displayed on e.g. 2x2 pixel on a high density display (phone). The image would look not that good. `source` supports higher density's won't show 2x2 or 3x3 blocks, it will use edges according to the image and high density makes the low quality invisible. Sometimes looks better, because high quality looks over-sharpen. (Hope that helps without going into jpeg algorithm.)

### Breakpoint options and max width

The image width is always BEFORE the breakpoint is hit and last value is max Width.

### My framework sues rem for max width and breakpoint

Images are pixel based and rem is simply a flexible browser value that is converted into pixel. Default is `16px` is `1rem` and can be changed in your browser settings (font, not scale). Binary image won't get more pixel based on that browser attribute.

### What are the best settings?

Hard to answer question. Every image needs time to be generated (normally tiny files). So density 3 x 3 breakpoints (+ 1 x 3 default webp + 1 fallback) end up with 13 images that need to be generated,

### Is webp safe to use?

Yes, you have a fallback img for ancient browsers, but is similar to picture in next FAQ entire.

https://caniuse.com/?search=webp

### !No! IE Support out of box

You need IE, you need a picture polyfill and maybe different projects

https://caniuse.com/?search=picture

### Not every browser supports loading

Yes, but most of them do. Even Safari as last major browser has it in technical preview. If a browser doesn't support it, it will load instantly. JS would need additional bandwidth and execution time on exactly the problem old devices and also added to any new device (browser).

https://caniuse.com/?search=loading

### Class handling

Default class is only set, if nothing else is used. Create a new advanced set for class combinations.


### What can I do with ratio?

Force a certain aspect ratio like 16:9 from a lot of screens. nice for same news header image size. It uses cropZoom from grav.

### My source image is small...

Every density and breakpoint checks the source fiel size. The image won't upscale images. Binary can't really be upscaled without some kind of neural network trained with a lot of images.

### Is Windows server supported

No, and I think it will fail. Windows Filesystem is messed up nightmare, and I have no device to test it (and also no interest). Linux and macOS should work without any problems.
