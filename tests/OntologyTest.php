<?php

/*
 * The MIT License
 *
 * Copyright 2019 zozlak.
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

use PDO;
use EasyRdf\Graph;
use zozlak\RdfConstants as RDF;

/**
 * Description of OntologyTest
 *
 * @author zozlak
 */
class OntologyTest extends \PHPUnit\Framework\TestCase {

    /**
     *
     * @var \PDO
     */
    static private $pdo;

    static public function setUpBeforeClass(): void {
        self::$pdo = new PDO('pgsql:');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testInit(): void {
        $o = new Ontology(self::$pdo, 'https://vocabs.acdh.oeaw.ac.at/schema#%');
        $this->assertNotNull($o);
    }

    public function testClassInheritance(): void {
        $o = new Ontology(self::$pdo, 'https://vocabs.acdh.oeaw.ac.at/schema#%');
        
        $r1 = (new Graph())->resource('.');
        $r1->addResource(RDF::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $this->assertTrue($o->isA($r1, 'https://vocabs.acdh.oeaw.ac.at/schema#RepoObject'));
        
        $r2 = (new Graph())->resource('.');
        $r2->addResource(RDF::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#BinaryContent');
        $this->assertTrue($o->isA($r2, 'https://vocabs.acdh.oeaw.ac.at/schema#RepoObject'));        
        
        $r3 = (new Graph())->resource('.');
        $r3->addResource(RDF::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#Agent');
        $this->assertFalse($o->isA($r3, 'https://vocabs.acdh.oeaw.ac.at/schema#RepoObject'));
        
        $r3->addResource(RDF::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#RepoObject');
        $this->assertTrue($o->isA($r3, 'https://vocabs.acdh.oeaw.ac.at/schema#RepoObject'));        
    }

    public function testCardinalitiesIndirect(): void {
        $o = new Ontology(self::$pdo, 'https://vocabs.acdh.oeaw.ac.at/schema#%');
        
        $c = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $pUri = 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact';
        $this->assertArrayHasKey($pUri, $c->properties);
        $this->assertEquals(1, $c->properties[$pUri]->min);
        
        $c = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#BinaryContent');
        $pUri = 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact';
        $this->assertArrayHasKey($pUri, $c->properties);
        $this->assertNull($c->properties[$pUri]->min);
    }
    
    public function testCardinalitiesDirect(): void {
        $o = new Ontology(self::$pdo, 'https://vocabs.acdh.oeaw.ac.at/schema#%');
        
        $r1 = (new Graph())->resource('.');
        $r1->addResource(RDF::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $p1 = $o->getProperty($r1, 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact');
        $this->assertEquals(1, $p1->min);
        
        $r2 = (new Graph())->resource('.');
        $r2->addResource(RDF::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#BinaryContent');
        $p2 = $o->getProperty($r2, 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact');
        $this->assertNull($p2->min);
        
        $this->assertNull($o->getProperty($r2, 'https://foo/bar'));
    }
    
    public function testPropertyDomainRange(): void {
        $o = new Ontology(self::$pdo, 'https://vocabs.acdh.oeaw.ac.at/schema#%');        
        $r = (new Graph())->resource('.');
        $p = $o->getProperty($r, 'https://vocabs.acdh.oeaw.ac.at/schema#hasUpdatedDate');
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#date', $p->range);
        $this->assertEquals('https://vocabs.acdh.oeaw.ac.at/schema#RepoObject', $p->domain);
    }
}
