# arche-lib-ontology

An API for the ACDH ontology stored in an ARCHE repository.

## Installation

`composer require arche-lib-schema`

## Usage

```php
$conn = new PDO('pgsql: repo db connection details');
$baseUrl = 'https://repository.base/url';

$ontology = new \acdhOeaw\arche\Ontology($conn, $baseUrl);

$class = $ontology->getClass('https://vocabs.acdh.oeaw.ac.at/schema#RepoObject');
print_r($class);
```
