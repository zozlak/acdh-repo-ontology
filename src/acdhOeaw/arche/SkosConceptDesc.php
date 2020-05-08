<?php

/*
 * The MIT License
 *
 * Copyright 2020 Austrian Centre for Digital Humanities.
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

use EasyRdf\Resource;
use zozlak\RdfConstants as RDF;

/**
 * Container class for a skos:Concept
 *
 * @author zozlak
 */
class SkosConceptDesc {

    static public function fromResource(Resource $data): self {
        $o      = new self();
        $o->uri = $data->getUri();

        $skosNmsp = substr(RDF::SKOS_ALT_LABEL, 0, strpos(RDF::SKOS_ALT_LABEL, '#') + 1);
        foreach ($o as $k => $v) {
            $values = $data->all($skosNmsp . $k);
            if (count($values) === 0) {
                continue;
            }
            if (!is_array($v)) {
                $o->$k = (string) $values[0];
            } else {
                foreach ($values as $v) {
                    if ($v instanceof Resource || empty($v->getLang())) {
                        $o->$k[] = (string) $v;
                    } else {
                        $o->$k[$v->getLang()] = (string) $v;
                    }
                }
            }
        }
        return $o;
    }

    static public function fromObject(object $data): self {
        $o = new self();
        foreach ($o as $k => $v) {
            if (isset($data->$k)) {
                if (is_object($data->$k)) {
                    $o->$k = (array) $data->$k;
                } else {
                    $o->$k = $data->$k;
                }
            }
        }
        return $o;
    }

    public $uri;
    public $broader   = [];
    public $narrower  = [];
    public $altLabel  = [];
    public $prefLabel = [];

    public function getLabel(string $lang, string $fallbackLang = 'en'): string {
        return $this->prefLabel[$lang] ??
            ($this->altLabel[$lang] ??
            ($this->prefLabel[$fallbackLang] ??
            ($this->altLabel[$fallbackLang] ??
            (reset($this->prefLabel) ??
            (reset($this->altLabel) ??
            '')))));
    }

}
