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

use ReflectionClass;

/**
 * Description of BaseDesc
 *
 * @author zozlak
 */
class BaseDesc {

    /**
     * Internal id of a corresponding repository resource
     */
    public ?int $id = null;

    /**
     * The ontology entity URI within the ontology namespace
     */
    public ?string $uri = null;

    /**
     * Associative array of label values (langauge as a key)
     * 
     * @var array<string>
     */
    public array $label = [];

    /**
     * Associative array of rdfs:comment values (langauge as a key)
     * 
     * @var array<string>
     */
    public array $comment = [];

    /**
     * 
     * @param object $d
     * @param array<string> $ids
     * @param string $nmsp
     */
    public function __construct(object $d = null, array $ids = [],
                                string $nmsp = null) {
        $nmspL = strlen((string) $nmsp);
        foreach ($ids as $i) {
            if ($nmspL > 0 && substr($i, 0, $nmspL) === $nmsp) {
                $this->uri = $i;
                break;
            }
        }
        if (empty($this->uri)) {
            $this->uri = $ids[0] ?? null;
        }

        if ($d !== null) {
            $rc = new ReflectionClass(static::class);
            foreach ($rc->getProperties() as $k) {
                $k = $k->name;
                $dk = strtolower($k);
                if (isset($d->$dk)) {
                    if (is_array($this->$k ?? null) && !is_array($d->$dk)) {
                        $this->$k = json_decode($d->$dk, true);
                    } else {
                        $this->$k = $d->$dk;
                    }
                }
            }
            if (isset($d->annotations) && !empty($d->annotations)) {
                foreach (json_decode($d->annotations) as $a) {
                    $prop = preg_replace('|^.*[#/]|', '', $a->property);
                    if (property_exists($this, $prop)) {
                        if (is_array($this->$prop ?? null)) {
                            if (!empty($a->lang)) {
                                $this->$prop[$a->lang] = $a->value ?? '';
                            } else {
                                $this->$prop[] = $a->value;
                            }
                        } else {
                            $this->$prop = $a->value;
                        }
                    }
                }
            }
        }
    }

    public function getLabel(string $lang, string $fallbackLang = 'en'): string {
        return $this->getPropInLang('label', $lang, $fallbackLang);
    }

    public function getComment(string $lang, string $fallbackLang = 'en'): string {
        return $this->getPropInLang('comment', $lang, $fallbackLang);
    }

    private function getPropInLang(string $property, string $lang,
                                   string $fallbackLang): string {
        return $this->{$property}[$lang] ?? ($this->{$property}[$fallbackLang] ?? (reset($this->$property) ?? ''));
    }
}
