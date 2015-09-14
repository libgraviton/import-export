[![Build Status](https://travis-ci.org/libgraviton/import-export.png?branch=develop)](https://travis-ci.org/libgraviton/import-export) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/libgraviton/import-export/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/libgraviton/import-export/?branch=develop) [![Code Coverage](https://scrutinizer-ci.com/g/libgraviton/import-export/badges/coverage.png?b=develop)](https://scrutinizer-ci.com/g/libgraviton/import-export/?branch=develop) [![Latest Stable Version](https://poser.pugx.org/graviton/import-export/v/stable.svg)](https://packagist.org/packages/graviton/import-export) [![Total Downloads](https://poser.pugx.org/graviton/import-export/downloads.svg)](https://packagist.org/packages/graviton/import-export) [![License](https://poser.pugx.org/graviton/import-export/license.svg)](https://packagist.org/packages/graviton/import-export)

Simple graviton client to help importing and exporting data.

## Installation

```bash
composer require graviton/import-export
```

## Usage

Get help:

```bash
./vendor/bin/graviton-import-export help
```

Load from dir:

```bash
./vendor/bin/graviton-import-export graviton:import http://localhost:8000 ./test/fixtures
```

## File format

The files to be loaded must contain yaml with additional yaml frontmatt (yo dawg...).

The frontmatter part defines what target path a file is to be loaded to.

```yml
---
target: /core/app/test
---
{ "id": "test", "name": { "en": "Test" }, "showInMenu": true, "order": 100 }
```

## Building phar package

Run phar build:

```bash
composer build
```

## TODO

* [x] implement importer
* [ ] implement exporter
* [x] build phar 
* [ ] deploy and document phar usage

