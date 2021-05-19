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

use OutOfBoundsException;

/**
 * A container for and RDF class description
 *
 * @author zozlak
 */
class ClassDesc extends BaseDesc {

    /**
     * Class URIs
     * 
     * @var array<string>
     */
    public $class = [];

    /**
     * Array of classes this class inherits from (including the class URI itself)
     * 
     * @var array<string>
     */
    public $classes = [];

    /**
     * Associative array of class properties (property URIs as keys, if 
     * a property has many URIs, it will exist under all of them - use the
     * `getProperties()` method to get a distinct list of properties).
     * 
     * @var array<PropertyDesc>
     */
    public $properties = [];

    /**
     * Returns distinct set of class properties
     * 
     * @return PropertyDesc[]
     * @throws OutOfBoundsException
     */
    public function getProperties(): array {
        $included = [];
        $ret      = [];
        foreach ($this->properties as $p) {
            $hash = spl_object_hash($p);
            if (!isset($included[$hash])) {
                $ret[sprintf('%04d.%d', $p->ordering, $p->id)] = $p;
                $included[$hash]                    = true;
            }
        }
        ksort($ret);
        return $ret;
    }

}
