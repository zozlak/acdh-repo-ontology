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

namespace acdhOeaw\arche\lib\schema;

use PDO;
use ReflectionClass;
use ReflectionProperty;
use EasyRdf\Graph;
use zozlak\RdfConstants as RDF;

/**
 * Description of OntologyTest
 *
 * @author zozlak
 */
class OntologyTest extends \PHPUnit\Framework\TestCase {

    const CACHE_FILE = 'cache';

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
        self::$pdo = new PDO('pgsql: host=localhost port=5432 user=www-data');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        self::$schema = (object) [
                'ontologyNamespace' => 'https://vocabs.acdh.oeaw.ac.at/schema#',
                'parent'            => 'https://vocabs.acdh.oeaw.ac.at/schema#isPartOf',
                'label'             => 'https://vocabs.acdh.oeaw.ac.at/schema#hasTitle',
        ];
    }

    public function tearDown(): void {
        if (file_exists(self::CACHE_FILE)) {
            unlink(self::CACHE_FILE);
        }
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
        $this->assertArrayHasKey('de', $c->comment);
        $this->assertEquals('Collection', $c->label['en']);
        $this->assertEquals($c->label['en'], $c->getLabel('en'));
        $this->assertEquals($c->label['de'], $c->getLabel('de'));
        $this->assertEquals($c->comment['en'], $c->getComment('en'));
        $this->assertEquals($c->comment['de'], $c->getComment('de'));
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

    public function testClassGetProperties(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $c = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $p = $c->getProperties();
        $this->assertEquals(count(array_unique(array_map('spl_object_id', $p))), count($p));
        $this->assertEquals(count(array_unique(array_map('spl_object_id', $c->properties))), count($p));
    }

    public function testPropertyPropertiesInitialized(): void {
        $o  = new Ontology(self::$pdo, self::$schema);
        $p  = $o->getProperty(null, 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicense');
        $rc = new ReflectionClass($p);
        foreach ($rc->getProperties(ReflectionProperty::IS_PUBLIC) as $i) {
            $rp = new ReflectionProperty($p, $i->name);
            $rt = $rp->getType();
            if ($rt !== null && !$rt->allowsNull()) {
                $this->assertTrue(isset($p->{$i->name}), "propertyDesc->" . $i->name . " is not initialized");
            }
        }
    }

    public function testCardinalitiesIndirect(): void {
        $o = new Ontology(self::$pdo, self::$schema);

        $c    = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $pUri = 'https://vocabs.acdh.oeaw.ac.at/schema#hasDepositor'; //defined for RepoObject
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
        $r1->addResource(RDF::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#TopCollection');
        $p1 = $o->getProperty($r1, 'https://vocabs.acdh.oeaw.ac.at/schema#hasContact');
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
        $p = $o->getProperty($r, 'https://vocabs.acdh.oeaw.ac.at/schema#hasAcceptedDate');
        $this->assertContains('http://www.w3.org/2001/XMLSchema#date', $p->range);
        $this->assertContains('https://vocabs.acdh.oeaw.ac.at/schema#RepoObject', $p->domain);
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
        $this->assertEquals('https://vocabs.acdh.oeaw.ac.at/rest/v1/arche_licenses/data', $p->vocabs);
    }

    public function testPropertyVocabularyValues(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $c = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $p = $c->properties['https://vocabs.acdh.oeaw.ac.at/schema#hasLicense'];
        $this->assertArrayHasKey('https://vocabs.acdh.oeaw.ac.at/archelicenses/cc-by-4-0', $p->vocabularyValues);
        $this->assertEquals('Attribution 4.0 International (CC BY 4.0)', $p->vocabularyValues['https://vocabs.acdh.oeaw.ac.at/archelicenses/cc-by-4-0']->getLabel('en'));
        $this->assertEquals('Attribution 4.0 International (CC BY 4.0)', $p->vocabularyValues['https://vocabs.acdh.oeaw.ac.at/archelicenses/cc-by-4-0']->getLabel('pl', 'en'));
        $this->assertEquals('Namensnennung 4.0 International (CC BY 4.0)', $p->vocabularyValues['https://vocabs.acdh.oeaw.ac.at/archelicenses/cc-by-4-0']->getLabel('pl', 'de'));
    }

    public function testPropertyGetVocabularyValues(): void {
        $o  = new Ontology(self::$pdo, self::$schema);
        $c  = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $p  = $c->properties['https://vocabs.acdh.oeaw.ac.at/schema#hasLicense'];
        $vv = $p->getVocabularyValues();
        $this->assertEquals(count(array_unique(array_map('spl_object_id', $vv))), count($vv));
        $this->assertEquals(count(array_unique(array_map('spl_object_id', $p->vocabularyValues))), count($vv));
    }

    public function testPropertyCheckVocabularyValue(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $c = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $p = $c->properties['https://vocabs.acdh.oeaw.ac.at/schema#hasLicense'];
        $this->assertIsString($p->checkVocabularyValue('https://vocabs.acdh.oeaw.ac.at/archelicenses/publicdomain-1-0'));
        $this->assertFalse($p->checkVocabularyValue('Public Domain Mark 1.0'));
        $this->assertIsString($p->checkVocabularyValue('Public Domain Mark 1.0', Ontology::VOCABSVALUE_PREFLABEL));
        $this->assertFalse($p->checkVocabularyValue('foo', Ontology::VOCABSVALUE_ALL));

        $p = $c->properties['https://vocabs.acdh.oeaw.ac.at/schema#hasTitle'];
        $this->assertTrue($p->checkVocabularyValue('foo'));
    }

    public function testPropertyGetVocabularyValue(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $c = $o->getClass('https://vocabs.acdh.oeaw.ac.at/schema#Collection');
        $p = $c->properties['https://vocabs.acdh.oeaw.ac.at/schema#hasLicense'];
        $v = $p->getVocabularyValue('Public Domain Mark 1.0', Ontology::VOCABSVALUE_PREFLABEL);
        $this->assertInstanceOf(SkosConceptDesc::class, $v);
        $this->assertNull($p->getVocabularyValue('foo'));

        $p = $c->properties['https://vocabs.acdh.oeaw.ac.at/schema#hasTitle'];
        $this->assertNull($p->getVocabularyValue('foo'));
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

    public function testCache(): void {
        $this->assertFileDoesNotExist(self::CACHE_FILE);

        $t1     = microtime(true);
        $o1     = new Ontology(self::$pdo, self::$schema, self::CACHE_FILE, 10);
        $t1     = microtime(true) - $t1;
        $this->assertFileExists(self::CACHE_FILE);
        $mtime1 = filemtime(self::CACHE_FILE);

        $t2 = microtime(true);
        $o2 = new Ontology(self::$pdo, self::$schema, self::CACHE_FILE, 10);
        $t2 = microtime(true) - $t2;
        $this->assertEquals($mtime1, filemtime(self::CACHE_FILE));

        sleep(2);

        $t3 = microtime(true);
        $o3 = new Ontology(self::$pdo, self::$schema, self::CACHE_FILE, 1);
        $t3 = microtime(true) - $t3;
        clearstatcache();
        $this->assertGreaterThan($mtime1, filemtime(self::CACHE_FILE));

        $c    = 'https://vocabs.acdh.oeaw.ac.at/schema#Collection';
        $p    = 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicense';
        $p1   = $o1->getProperty($c, $p);
        $p2   = $o2->getProperty($c, $p);
        $p3   = $o3->getProperty($c, $p);
        $attr = [
            'id', 'uri', 'label', 'comment', // BaseDesc
            'property', 'type', 'domain', 'properties', 'range', 'min', 'max', 'recommendedClass',
            'automatedFill', 'defaultValue', 'langTag', 'ordering', 'vocabs'
        ];
        foreach ($attr as $i) {
            $this->assertEqualsCanonicalizing($p1->$i, $p2->$i);
            $this->assertEqualsCanonicalizing($p1->$i, $p3->$i);
        }

        $v1 = $p1->checkVocabularyValue('Public Domain Mark 1.0', Ontology::VOCABSVALUE_PREFLABEL);
        $this->assertEquals($v1, $p2->checkVocabularyValue('Public Domain Mark 1.0', Ontology::VOCABSVALUE_PREFLABEL));
        $this->assertEquals($v1, $p3->checkVocabularyValue('Public Domain Mark 1.0', Ontology::VOCABSVALUE_PREFLABEL));
    }

    public function testGetClasses(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $c = $o->getClasses();
        $this->assertGreaterThan(0, count($c));
        $this->assertInstanceOf(ClassDesc::class, $c[0]);
    }

    public function testGetProperties(): void {
        $o = new Ontology(self::$pdo, self::$schema);
        $p = $o->getProperties();
        $this->assertGreaterThan(0, count($p));
        $this->assertInstanceOf(PropertyDesc::class, $p[0]);
    }
}
