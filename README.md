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

This will show you all available commands.

### Load data to a Graviton instance through REST (using the HTTP interface)

Load from dir:

```bash
./vendor/bin/graviton-import-export graviton:import http://localhost:8000 ./test/fixtures
```

### Load data into the core database of a Graviton instance

Besides loading data via the HTTP interface, there are *core* commands available that allow you to load data into
the database backend (MongoDB) of a Graviton instance.

You can import a set of existing files via the `graviton:core:import` command:

```bash
./vendor/bin/graviton-import-export graviton:core:import ./test/data
```

The *core* commands file format is slightly different from the normal import format as we need to preserve certain class types.
Thus, it's best to insert data into MongoDB and export that into the necessary format. This can be done using the export command:

```bash
./vendor/bin/graviton-import-export graviton:core:export ./test/dump-dir
```

This will dump all the data in the default database. The `graviton:core:export` has more options, refer to the `--help` print 
 for more details.
 
Additionally, we have a *purge* command that allows you to easily purge (meaning *delete!*) all collections inside a 
MongoDB database. You need to pass 'yes' as an only parameter to show that you're sure about that action.

```bash
./vendor/bin/graviton-import-export graviton:core:purge yes
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

### Importing Files to /file/

* Files cannot be overwritten, an attempt to update a file-dataset with the tool will throw an error.

Example for a File-Upload, i.e. swisscom.png with additional link to be added to the file dataset:
```yml
--- 
target: /file/swisscom 
file: swisscom.png 
--- 
id: swisscom 
links: 
    - 
      type: "accountType" 
      $ref: "http://localhost/entity/code/accountType-1" 
```
## Validate Files
Files are not native YML or JSON file so using this command you can easily check if they are ok.
```php
php ./bin/graviton-import-export graviton:validate:import /{full path to}/initialdata/data/
```
You can also check for any subfolder directly. 
Output (single error sample):
```bash
Validation will be done for: 60047 files 
 60047/60047 [============================] 100%
Finished

With: 1 Errors
/initialdata/data/param/0_general/event/action/error_file.yml: Malformed inline YAML string ("error_file) at line 1 (near "id: "error_file").
```

## Authentication and Headers on "put" import
Some endpoints may require basic auth or some cookie or header to be sent. -a and -c can be used in combination.
```php
-a is for "basic auth", this will do a base 64 encode header. -a{user:passw}
-c is for Customer Header(s), -c{key:value}  just add more if needed, -c is array enabled.
php ./bin/graviton-import-export graviton:import http://... /{full path to}/initialdata/data/ -ajohn:doe -cheader1:value1 -cheader2:value2
```

## Building Docker Runtime

```bash
docker build -t graviton/import-export .
```

## Docker Runtime

```bash
docker run --rm -ti -v `pwd`:/data graviton/import-export
```

## Building phar package

Run phar build:

```bash
composer build
```

### Deploying phar host

```bash
cf push <name-of-host>
```

Or use [deploy-scripts](https://github.com/libgraviton/deploy-scripts) to deploy in automated blue/green fashion.

## TODO

* [x] implement importer
* [ ] implement exporter
* [x] build phar 
* [x] deploy phar
* [ ] automate phar deployment
* [ ] document phar usage

