---
layout: page
title: "Library installation"
date: 2013-02-09 00:40
comments: true
sharing: true
footer: true
---

Go! library can be installed with composer or manually with git submodules. Installation is quite easy.

### Method 1: Download go-aop-php using composer

The simplest way to install Go! AOP library is through Composer. Just add the library to your composer.json:

```js
{
    "require": {
        "lisachenko/go-aop-php": "*"
    }
}
```

Now ask the composer to download the library with its dependencies by running the command:

```bash
$ php composer.phar install
```

Composer will install the library to your project's `vendor/lisachenko/go-aop-php` directory.

### Method 2: Download with Git

Alternative way for installing the library is to use git submodules:

```bash
git submodule add https://github.com/lisachenko/go-aop-php vendor/lisachenko/go-aop-php
```

Dependencies should be also installed as submodules:
```bash
git submodule add https://github.com/Andrewsville/PHP-Token-Reflection vendor/andrewsville/php-token-reflection
git submodule add https://github.com/doctrine/common vendor/doctrine/common
```

<a class="next" href="../initial-configuration/">Initial configuration &rarr;</a>