# arche-lib-schema

[![Latest Stable Version](https://poser.pugx.org/acdh-oeaw/arche-lib-schema/v/stable)](https://packagist.org/packages/acdh-oeaw/arche-lib-schema)
![Build status](https://github.com/acdh-oeaw/arche-lib-schema/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/acdh-oeaw/arche-lib-schema/badge.svg?branch=master)](https://coveralls.io/github/acdh-oeaw/arche-lib-schema?branch=master)
[![License](https://poser.pugx.org/acdh-oeaw/arche-lib-schema/license)](https://packagist.org/packages/acdh-oeaw/arche-lib-schema)


An API for the ACDH ontology stored in an ARCHE repository.

## Installation

`composer require arche-lib-schema`

## Usage

```php
$conn = new PDO('pgsql: repo db connection details');
$cfg = (object) [
    'ontologyNamespace' => 'https://vocabs.acdh.oeaw.ac.at/schema#',
    'parent'            => 'https://vocabs.acdh.oeaw.ac.at/schema#isPartOf',
    'label'             => 'https://vocabs.acdh.oeaw.ac.at/schema#hasTitle',
];

$ontology = new \acdhOeaw\arche\Ontology($conn, $cfg);

$class = $ontology->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Person');
print_r($class);

$property = $ontology->getProperty('https://vocabs.acdh.oeaw.ac.at/schema#RepoObject', 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact');
print_r($property);

$property = $ontology->getProperty(null, 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact');
print_r($property);

$property = $ontology->getProperty(null, 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicense');
print_r($property->vocabsValues);
echo $property->vocabsValues['https://vocabs.acdh.oeaw.ac.at/archelicenses/cc-by-4-0']->getLabel('de');

```
