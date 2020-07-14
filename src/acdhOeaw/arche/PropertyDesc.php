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

namespace acdhOeaw\arche;

/**
 * A container for an RDF property description
 *
 * @author zozlak
 */
class PropertyDesc extends BaseDesc {

    /**
     * Property URI
     * 
     * @var string
     */
    public $property;

    /**
     * Property type URI (owl:DatatypeProperty or owl:ObjectProperty)
     * 
     * @var string
     */
    public $type;

    /**
     * Property domain URI
     * 
     * @var string
     */
    public $domain;

    /**
     * Property URIs of all properties this one inhertis from (includint itself)
     * 
     * @var string[]
     */
    public $properties = [];

    /**
     * Property range URI
     * 
     * @var string
     */
    public $range;

    /**
     * Minimum count
     * 
     * @var int
     */
    public $min;

    /**
     * Maximum count
     * 
     * @var int
     */
    public $max;

    /**
     * If a class is among acdh:recommendedClass for this property.
     * @var bool
     */
    public $recommended = [];

    /**
     * achd:ordering annotation property value
     * @var int
     */
    public $order = 99999;

    /**
     * acdh:langTag annotation property value
     * @var bool
     */
    public $langTag;

    /**
     * acdh:vocabs annotation property value
     * @var string
     */
    public $vocabs;

    /**
     * Array of vocabulary values fetched from vocabulary pointed by acdh:vocabs
     * annotation property
     * @var SkocConceptDesc[]
     */
    private $vocabsValues;

    /**
     *
     * @var Ontology
     */
    private $ontologyObj;

    public function setOntology(Ontology $ontology) {
        $this->ontologyObj = $ontology;
    }

    public function __get(string $name) {
        if ($name === 'vocabsValues' && $this->vocabsValues === null && !empty($this->vocabs)) {
            $this->vocabsValues = $this->ontologyObj->getVocabularyValues($this->vocabs);
        }
        return $this->$name;
    }

}
