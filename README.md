# arche-lib-ontology

An API for the ACDH ontology stored in an ARCHE repository.

## Installation

`composer require arche-lib-schema`

## Usage

```php
$conn = new PDO('pgsql: repo db connection details');
$cfg = (object) [
    'skipNamespace' => 'https://repository.base/url/%', // don't forget the '%' at the end!
    'order'         => 'https://vocabs.acdh.oeaw.ac.at/schema#ordering',
    'recommended'   => 'https://vocabs.acdh.oeaw.ac.at/schema#recommendedClass',
    'langTag'       => 'https://vocabs.acdh.oeaw.ac.at/schema#langTag',
    'vocabs'        => 'https://vocabs.acdh.oeaw.ac.at/schema#vocabs',
];

$ontology = new \acdhOeaw\arche\Ontology($conn, $cfg);

$class = $ontology->getClass('https://vocabs.acdh.oeaw.ac.at/schema#RepoObject');
print_r($class);

$property = $ontology->getProperty('https://vocabs.acdh.oeaw.ac.at/schema#RepoObject', 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact');
print_r($property);

$property = $ontology->getProperty(null, 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact');
print_r($property);

$ontology->fetchVocabularies('vocabsCache.json', 'PT10D');
$property = $ontology->getProperty(null, 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicense');
print_r($property->vocabsValues);
echo $property->vocabsValues['https://vocabs.acdh.oeaw.ac.at/archelicenses/cc-by-4-0']->getLabel('en');

```
