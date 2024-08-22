# laravel-pdf-ext

This Laravel package provides a helper/extension for managing PDF activities.

It relies on:

 - [typesetsh/laravel-wrapper](https://packagist.org/packages/typesetsh/laravel-wrapper)
 - [mikehaertl/php-pdftk](https://packagist.org/packages/mikehaertl/php-pdftk)

Because Typeset.sh is a paid product, you will need to do some configuration in the `composer.json` file.  

You must add the repositories for this extension, and for Typeset.  This also requires you add the license info (in the form of basic HTTP auth) for the Typeset repo

```json
{
    ...
    "repositories": {
        "typesetsh": {
            "type": "composer",
            "url": "https://packages.typeset.sh"
        },
        "0": {
            "type": "vcs",
            "url": "https://github.com/fastrans/laravel-pdf-ext"
        }
    },
    ...
    "config": {
        ...
        "http-basic": {
            "packages.typeset.sh": {
                "username": "<username from typeset>",
                "password": "<password from typeset>"
            }
        }
        ...   
    }
}
```

Then, a `composer require fastrans/laravel-pdf-ext` should do it.

Use it like:
```php
use Fastrans\LaravelPdfExt\Pdf

$pdf = Pdf::fromHtml('<b>Hello</b> World!');
$pdf->save("/tmp/output.pdf");
```
