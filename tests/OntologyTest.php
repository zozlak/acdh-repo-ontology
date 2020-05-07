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

    /**
     *
     * @var object
     */
    static private $schema;

    static public function setUpBeforeClass(): void {
        self::$pdo = new PDO('pgsql:');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        self::$schema = (object) [
                'skipNamespace' => 'http://127.0.0.1/%',
                'order'         => 'https://vocabs.acdh.oeaw.ac.at/schema#ordering',
                'recommended'   => 'https://vocabs.acdh.oeaw.ac.at/schema#recommendedClass',
                'langTag'       => 'https://vocabs.acdh.oeaw.ac.at/schema#langTag',
                'vocabs'        => 'https://vocabs.acdh.oeaw.ac.at/schema#vocabs',
        ];
    }

    public function testInit(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $this->assertNotNull($o);
    }

    public function testClassLabelComment(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $c = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $this->assertArrayHasKey('en', $c->label);
        $this->assertArrayHasKey('de', $c->label);
        $this->assertArrayHasKey('en', $c->comment);
        $this->assertEquals('Collection', $c->label['en']);
    }

    public function testClassInheritance(): void {
        $o = new Ontology(self::$pdo, self::$schema);

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
        $o = new Ontology(self::$pdo, self::$schema);

        $c    = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $pUri = 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact';
        $this->assertArrayHasKey($pUri, $c->properties);
        $this->assertEquals(1, $c->properties[$pUri]->min);

        $c    = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#BinaryContent');
        $pUri = 'https://vocabs.acdh.oeaw.ac.at/schema#hasNote';
        $this->assertArrayHasKey($pUri, $c->properties);
        $this->assertNull($c->properties[$pUri]->min);
    }

    public function testCardinalitiesDirect(): void {
        $o = new Ontology(self::$pdo, self::$schema);

        $r1 = (new Graph())->resource('.');
        $r1->addResource(RDF::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $p1 = $o->getProperty($r1, 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicense');
        $this->assertEquals(1, $p1->min);

        $r2 = (new Graph())->resource('.');
        $r2->addResource(RDF::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#BinaryContent');
        $p2 = $o->getProperty($r2, 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact');
        $this->assertNull($p2->min);

        $this->assertNull($o->getProperty($r2, 'https://foo/bar'));
    }

    public function testPropertyDomainRange(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $r = (new Graph())->resource('.');
        $p = $o->getProperty($r, 'https://vocabs.acdh.oeaw.ac.at/schema#hasUpdatedDate');
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#date', $p->range);
        $this->assertEquals('https://vocabs.acdh.oeaw.ac.at/schema#Main', $p->domain);
    }

    public function testPropertyLabelComment(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $c = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $p = $c->properties['https://vocabs.acdh.oeaw.ac.at/schema#hasContact'];
        $this->assertArrayHasKey('en', $p->label);
        $this->assertArrayHasKey('de', $p->label);
        $this->assertArrayHasKey('en', $p->comment);
        $this->assertEquals('Contact(s)', $p->label['en']);
    }

    public function testPropertyLangTag(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $c = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $p = $c->properties['https://vocabs.acdh.oeaw.ac.at/schema#hasTitle'];
        $this->assertEquals(1, $p->langTag);
    }

    public function testPropertyVocabs(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $c = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $p = $c->properties['https://vocabs.acdh.oeaw.ac.at/schema#hasLicense'];
        $this->assertEquals('https://vocabs.acdh.oeaw.ac.at/arche_licenses/', $p->vocabs);
    }
    
    public function testPropertyByClassUri(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $p = $o->getProperty('https://vocabs.acdh.oeaw.ac.at/schema#Collection', 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact');
        $this->assertEquals('Contact(s)', $p->label['en']);
    }

    public function testPropertyWithoutClass(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $p = $o->getProperty(null, 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact');
        $this->assertEquals('Contact(s)', $p->label['en']);
    }

}
