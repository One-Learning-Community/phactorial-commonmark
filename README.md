<h1>
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://github.com/One-Learning-Community/phactorial/raw/main/site/src/.vuepress/public/phactorial-wordmark-dark.svg">
    <img alt="Phactorial" src="https://github.com/One-Learning-Community/phactorial/raw/main/site/src/.vuepress/public/phactorial-wordmark.svg" height="56">
  </picture>
</h1>

## phactorial/commonmark

[![phactorial/commonmark](https://img.shields.io/packagist/v/phactorial/commonmark?label=phactorial/commonmark)](https://packagist.org/packages/phactorial/commonmark) [![commonmark](https://github.com/One-Learning-Community/phactorial/actions/workflows/commonmark.yml/badge.svg?branch=main)](https://github.com/One-Learning-Community/phactorial/actions/workflows/commonmark.yml)

GitHub's math syntax for [league/commonmark](https://commonmark.thephpleague.com/), typeset on the server as SVG or
MathML with [Phactorial](https://github.com/One-Learning-Community/phactorial). The page needs no JavaScript and no
math font.

## Install

```
composer require phactorial/commonmark
```

It needs PHP 8.4 or newer.

## Use

```php
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Phactorial\CommonMark\MathExtension;

$environment = new Environment(['math' => ['output' => 'svg']]);
$environment->addExtension(new CommonMarkCoreExtension());
$environment->addExtension(new MathExtension());

echo (new MarkdownConverter($environment))->convert('Area is $\pi r^2$.');
```

Using CommonMark in Laravel? See the dedicated
[Laravel](https://one-learning-community.github.io/phactorial/laravel.html) page.

## Syntax

The rules follow GitHub's renderer, so Markdown that renders math on GitHub renders it here.

| Markdown | Result |
|---|---|
| `$x^2$` | Inline math |
| ``$`e^{i\pi}`$`` | Inline math that may hold a `$` |
| `$$` on its own lines around TeX | A display block |
| ```` ```math ```` fence | A display block |
| `\$` | A literal dollar sign |

Prices stay prices: `costs $5 and $10` is plain text. Code spans and link text are never math.

## Options

All options live under the `math` key.

| Key | Values | Default | Meaning |
|---|---|---|---|
| `output` | `svg`, `mathml` | `svg` | SVG looks the same in every browser. MathML is smaller, selectable, and drawn by the browser. |
| `size` | a number above 0 | `16` | Font size in pixels for SVG output. |
| `on_error` | `source`, `render` | `source` | A formula with an unknown command shows its Markdown in `<code class="math math-error">`, or with `render`, is drawn from what could be read. |
| `latex_delimiters` | `true`, `false` | `false` | Also read `\(…\)` and `\[…\]`, the way LaTeX and chatbots write math. Off by default because `\[citation needed\]` would become a formula. |

To center display math written inside a paragraph, add:

```css
.math-display { display: block; text-align: center; }
```

## Accessibility

Each SVG formula carries `role="img"` and an `aria-label` that spells the formula out as text, so screen readers speak
it. MathML output is text already, and screen readers read it directly.

## Documentation

The full guide lives at [one-learning-community.github.io/phactorial](https://one-learning-community.github.io/phactorial/commonmark.html).
