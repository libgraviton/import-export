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

## TODO

* [x] implement importer
* [ ] implement exporter
* [ ] build, deploy and document phar usage

