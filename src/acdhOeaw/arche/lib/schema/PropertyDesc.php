<?php

/*
 * The MIT License
 *
 * Copyright 2019 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\arche\lib\schema;

/**
 * A container for an RDF property description
 *
 * @author zozlak
 */
class PropertyDesc extends BaseDesc {

    /**
     * Property URIs
     * 
     * @var array<string>
     */
    public array $property = [];

    /**
     * Property type URI (owl:DatatypeProperty or owl:ObjectProperty)
     */
    public string $type;

    /**
     * Property domain URI
     * 
     * @var array<string>
     */
    public array $domain = [];

    /**
     * Property URIs of all properties this one inhertis from (includint itself)
     * 
     * @var array<string>
     */
    public array $properties = [];

    /**
     * Property range URI
     * 
     * @var array<string>
     */
    public array $range = [];

    /**
     * Minimum count
     */
    public ?int $min = null;

    /**
     * Maximum count
     */
    public ?int $max = null;

    /**
     * If a class is among acdh:recommendedClass for this property.
     * @var bool | array<string>
     */
    public $recommendedClass = [];

    /**
     * achd:automatedFill annotation property value
     */
    public bool $automatedFill = false;

    /**
     * acdh:defaultValue annotation property value
     */
    public ?string $defaultValue = null;

    /**
     * acdh:langTag annotation property value
     */
    public bool $langTag = false;

    /**
     * achd:ordering annotation property value
     */
    public int $ordering = 99999;

    /**
     * acdh:vocabs annotation property value
     */
    public string $vocabs = '';

    /**
     * Array of vocabulary values fetched from vocabulary pointed by acdh:vocabs
     * annotation property
     * @var array<SkosConceptDesc>
     */
    private array $vocabsValues;
    private Ontology $ontologyObj;

    public function setOntology(Ontology $ontology) {
        $this->ontologyObj = $ontology;
    }

    public function __get(string $name) {
        if ($name === 'vocabsValues' && !isset($this->vocabsValues) && !empty($this->vocabs)) {
            $this->vocabsValues = $this->ontologyObj->getVocabularyValues($this->vocabs);
        }
        return $this->$name ?? null;
    }

    public function getVocabularyValues($lang = 'en'): array {
        $included = [];
        $ret      = [];
        if (!isset($this->vocabsValues) && !empty($this->vocabs)) {
            $this->vocabsValues = $this->ontologyObj->getVocabularyValues($this->vocabs);
        }
        foreach ($this->vocabsValues ?? [] as $v) {
            $hash = spl_object_hash($v);
            if (!isset($included[$hash])) {
                $ret[$v->getLabel($lang)] = $v;
                $included[$hash]          = true;
            }
        }
        ksort($ret);
        return array_values($ret);
    }

    /**
     * Checks if a given value exists in a given vocabulary.
     * 
     * Returns true if a property doesn't use a controlled vocabulary.
     * 
     * @param string $value
     * @return bool
     */
    public function checkVocabularyValue(string $value): bool {
        if (empty($this->vocabs)) {
            return true;
        }
        return $this->ontologyObj->checkVocabularyValue($this->vocabs, $value);
    }
}
