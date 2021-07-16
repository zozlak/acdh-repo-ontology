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

use PDO;
use OutOfBoundsException;
use EasyRdf\Resource;
use zozlak\RdfConstants as RDF;

/**
 * Provides an API for the ARCHE oontology.
 *
 * Maps the RDF ontology structure into the object model.
 * 
 * @author zozlak
 */
class Ontology {

    const VOCABSVALUE_ID        = 1;
    const VOCABSVALUE_NOTATION  = 2;
    const VOCABSVALUE_PREFLABEL = 4;
    const VOCABSVALUE_ALTLABEL  = 8;
    const VOCABSVALUE_ALL       = 255;

    /**
     * 
     * @var array<string, int>
     */
    static private array $vocabsValueProperties = [
        RDF::SKOS_NOTATION   => self::VOCABSVALUE_NOTATION,
        RDF::SKOS_PREF_LABEL => self::VOCABSVALUE_PREFLABEL,
        RDF::SKOS_ALT_LABEL  => self::VOCABSVALUE_ALTLABEL,
    ];
    private PDO $pdo;
    private object $schema;

    /**
     *
     * @var ClassDesc[]
     */
    private $classes = [];

    /**
     *
     * @var array<string, array<ClassDesc>>
     */
    private $classesRev = [];

    /**
     *
     * @var array<string, PropertyDesc>
     */
    private $properties = [];

    /**
     *
     * @var array<string, PropertyDesc>
     */
    private $distinctProperties = [];

    /**
     *
     * @var array<string, RestrictionDesc>
     */
    private $restrictions = [];

    /**
     * 
     * @param PDO $pdo
     * @param object $schema
     */
    public function __construct(PDO $pdo, object $schema) {
        $this->pdo    = $pdo;
        $this->schema = $schema;

        $this->loadClasses();
        $this->loadProperties();
        $this->loadRestrictions();
        $this->preprocess();
    }

    /**
     * Checks if a given RDF resource is of a given class taking into account
     * ontology class inheritance.
     * 
     * @param Resource $res
     * @param string $class
     * @return bool
     */
    public function isA(Resource $res, string $class): bool {
        foreach ($res->allResources(RDF::RDF_TYPE) as $t) {
            $t = (string) $t;
            if ($t === $class) {
                return true;
            }
            if (isset($this->classes[$t]) && in_array($class, $this->classes[$t]->classes)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns class description.
     * 
     * @param string $class class name URI
     * @return ClassDesc|null
     */
    public function getClass(string $class): ?ClassDesc {
        return $this->classes[$class] ?? null;
    }

    /**
     * Returns a given property description for a given set of RDF classes or
     * an RDF resource (in the latter case classes list is extracted from the
     * resource).
     * 
     * If property domain matches many classes, a description for first
     * encountered class is returned (property cardinality and range may vary
     * between classes).
     * 
     * If no classes/RDF resource is provided all known classes are searched
     * and a first encounterred match is returned (property cardinality and 
     * range may vary between classes).
     * 
     * @param Resource | array<string> | string | null $resOrClassesArray an RDF resource or an 
     *   array of RDF class URIs or an RDF class URI
     * @param string $property property URI
     * @return PropertyDesc|null
     */
    public function getProperty(Resource | array | string | null $resOrClassesArray,
                                string $property): ?PropertyDesc {
        if (empty($resOrClassesArray)) {
            $resOrClassesArray = [];
        } elseif ($resOrClassesArray instanceof Resource) {
            $resOrClassesArray = $resOrClassesArray->allResources(RDF::RDF_TYPE);
        } elseif (!is_array($resOrClassesArray)) {
            $resOrClassesArray = [$resOrClassesArray];
        }
        foreach ($resOrClassesArray as $class) {
            $class = (string) $class;
            if (isset($this->classes[$class]) && isset($this->classes[$class]->properties[$property])) {
                return $this->classes[$class]->properties[$property];
            }
        }
        return $this->properties[$property] ?? null;
    }

    /**
     * Fetches an array of SkosConceptDesc objects desribing vocabulary values.
     * 
     * @param string $vocabularyUrl
     * @return array<SkosConceptDesc>
     */
    public function getVocabularyValues(string $vocabularyUrl): array {
        $query = "
            SELECT r.id
            FROM
                identifiers i
                JOIN relations r ON r.target_id = i.id AND r.property = ?
            WHERE ids = ?
        ";
        $param = [RDF::SKOS_IN_SCHEME, $vocabularyUrl];
        return $this->fetchVocabularyValues($query, $param);
    }

    /**
     * Fetches SkosConceptDesc object desribing a vocabulary value.
     * 
     * @param string $vocabularyUrl
     * @param string $value
     * @param int $searchIn combination of Ontology::VOCABSVALUE_* flags indicating
     *   where to search for the $value (in a concept URI/ID, skos:notation, 
     *   skos:prefLabel, etc.)
     * @return ?SkosConceptDesc
     */
    public function getVocabularyValue(string $vocabularyUrl, string $value,
                                       int $searchIn = self::VOCABSVALUE_ID): ?SkosConceptDesc {
        $id = $this->checkVocabularyValue($vocabularyUrl, $value, $searchIn);
        if ($id === false) {
            return null;
        }
        $query = "SELECT id FROM identifiers WHERE ids = ?";
        $param = [$id];
        $value = $this->fetchVocabularyValues($query, $param);
        return array_pop($value);
    }

    /**
     * Checks if a given value exists in a given vocabulary.
     * 
     * @param string $vocabularyUrl
     * @param string $value
     * @param int $searchIn combination of Ontology::VOCABSVALUE_* flags indicating
     *   where to search for the $value (in a concept URI/ID, skos:notation, 
     *   skos:prefLabel, etc.)
     * @return string|false a vocabulary value identifier or false if $value is invalid
     */
    public function checkVocabularyValue(string $vocabularyUrl, string $value,
                                         int $searchIn = self::VOCABSVALUE_ID): string | false {
        if ($searchIn & self::VOCABSVALUE_ID) {
            $query = "
                SELECT count(*)
                FROM
                    identifiers i1
                    JOIN relations r USING (id)
                    JOIN identifiers i2 ON r.target_id = i2.id
                WHERE
                    i1.ids = ?
                    AND i2.ids = ?
                    AND r.property = ?
            ";
            $param = [$value, $vocabularyUrl, RDF::SKOS_IN_SCHEME];
            $query = $this->pdo->prepare($query);
            $query->execute($param);
            if ($query->fetchColumn() > 0) {
                return $value;
            }
        }
        if ($searchIn <= self::VOCABSVALUE_ID) {
            return false;
        }

        $query = "
            SELECT i1.id, (array_agg(i1.ids))[1] AS ids
            FROM
                identifiers i1
                JOIN metadata m USING (id)
                JOIN relations r USING (id)
                JOIN identifiers i2 ON r.target_id = i2.id
            WHERE
                substring(m.value, 1, 1000) = ?
                AND m.property = ?
                AND i2.ids = ?
                AND r.property = ?
            GROUP BY 1
        ";
        $query = $this->pdo->prepare($query);
        foreach (self::$vocabsValueProperties as $prop => $mask) {
            if ($searchIn & $mask) {
                $query->execute([$value, $prop, $vocabularyUrl, RDF::SKOS_IN_SCHEME]);
                $results = $query->fetchAll(PDO::FETCH_OBJ);
                if (count($results) === 1) {
                    return $results[0]->ids;
                }
            }
        }
        return false;
    }

    private function loadClasses(): void {
        $query = "
            WITH RECURSIVE t(pid, id, n) AS (
                SELECT DISTINCT id, id, 0
                FROM
                    identifiers
                    JOIN metadata USING (id)
                WHERE
                    property = ?
                    AND substring(value, 1, 1000) = ?
              UNION
                SELECT r.target_id, t.id, t.n + 1
                FROM
                    relations r 
                    JOIN t ON t.pid = r.id AND (property = ? OR property = ?)
            ),
            tt AS (
                SELECT id, json_agg(pid ORDER BY n DESC) AS pids
                FROM t
                GROUP BY 1
            )
            SELECT id, pids, class, classes, label, comment 
            FROM
                tt
                JOIN (
                    SELECT id, json_agg(ids ORDER BY ids) AS class
                    FROM tt JOIN identifiers USING (id)
                    GROUP BY 1
                ) c1 USING (id)
                JOIN (
                    SELECT t.id, json_agg(ids ORDER BY n DESC, ids) AS classes 
                    FROM t JOIN identifiers i ON t.pid = i.id
                    GROUP BY 1
                ) c2 USING (id)
                LEFT JOIN (
                    SELECT id, json_object(array_agg(lang), array_agg(value)) AS label
                    FROM tt JOIN metadata USING (id)
                    WHERE property = ?
                    GROUP BY 1
                ) c3 USING (id)
                LEFT JOIN (
                    SELECT id, json_object(array_agg(lang), array_agg(value)) AS comment
                    FROM tt JOIN metadata USING (id)
                    WHERE property = ?
                    GROUP BY 1
                ) c4 USING (id)
        ";
        $param = [
            RDF::RDF_TYPE, RDF::OWL_CLASS, // with non-recursive
            RDF::RDFS_SUB_CLASS_OF, RDF::OWL_EQUIVALENT_CLASS, // with recursive
            RDF::SKOS_ALT_LABEL, RDF::RDFS_COMMENT, // c3 (label), c4 (comment)
        ];
        $query = $this->pdo->prepare($query);
        $query->execute($param);
        while ($c     = $query->fetch(PDO::FETCH_OBJ)) {
            $classList = json_decode($c->class);
            $cc        = new ClassDesc($c, $classList, $this->schema->ontologyNamespace);
            foreach ($classList as $i) {
                $this->classes[$i] = $cc;
            }
            $this->classes[(string) $c->id] = $cc;
        }

        // reverse class index (from a class to all inheriting from it)
        foreach ($this->classes as $c) {
            foreach ($c->classes as $cStr) {
                if (!isset($this->classesRev[$cStr])) {
                    $this->classesRev[$cStr] = [];
                }
                $this->classesRev[$cStr][] = $this->classes[$c->class[0]];
            }
        }
    }

    private function loadProperties(): void {
        // much faster as it allows "property IN ()" clause in the next query
        $query  = "
            SELECT i1.ids
            FROM
                identifiers i1
                JOIN relations r USING (id)
                JOIN identifiers i2 ON r.target_id = i2.id
            WHERE
                r.property = ?
                AND i2.ids = ?
        ";
        $param       = [$this->schema->parent, RDF::OWL_ANNOTATION_PROPERTY];
        $query       = $this->pdo->prepare($query);
        $query->execute($param);
        $anPropParam = $query->fetchAll(PDO::FETCH_COLUMN);
        $anPropParam = count($anPropParam) > 0 ? $anPropParam : [''];
        $anPropSql   = substr(str_repeat('?, ', count($anPropParam)), 0, -2);

        $query = "
            WITH RECURSIVE t(pid, id, type, n) AS (
                SELECT DISTINCT id, id, value, 0
                FROM 
                    identifiers
                    JOIN metadata USING (id)
                WHERE 
                    property = ?    
                    AND substring(value, 1, 1000) IN (?, ?)
              UNION
                SELECT r.target_id, t.id, t.type, t.n + 1
                FROM
                    relations r 
                    JOIN t ON t.pid = r.id AND (property = ? OR property = ?) AND n < 30
            ),
            tt AS (
                SELECT id, type, json_agg(pid ORDER BY n DESC) AS pids
                FROM t
                GROUP BY 1, 2
            )
            SELECT tt.id, tt.pids, tt.type, property, properties, range, domain, label, comment, annotations
            FROM
                tt
                JOIN (
                    SELECT id, json_agg(ids ORDER BY ids) AS property
                    FROM tt JOIN identifiers USING (id)
                    GROUP BY 1
                ) c1 USING (id)
                JOIN (
                    SELECT t.id, json_agg(ids ORDER BY n DESC, ids) AS properties 
                    FROM t JOIN identifiers i ON t.pid = i.id
                    GROUP BY 1
                ) c2 USING (id)
                LEFT JOIN (
                    SELECT r.id, json_agg(ids) AS range
                    FROM relations r JOIN identifiers i ON r.target_id = i.id AND r.property = ?
                    GROUP BY 1
                ) c3 USING (id)
                LEFT JOIN (
                    SELECT r.id, json_agg(ids) AS domain
                    FROM relations r JOIN identifiers i ON r.target_id = i.id AND r.property = ?
                    GROUP BY 1
                ) c4 USING (id)
                LEFT JOIN (
                    SELECT id, json_object(array_agg(lang), array_agg(value)) AS label
                    FROM tt JOIN metadata USING (id)
                    WHERE property = ?
                    GROUP BY 1
                ) c5 USING (id)
                LEFT JOIN (
                    SELECT id, json_object(array_agg(lang), array_agg(value)) AS comment
                    FROM tt JOIN metadata USING (id)
                    WHERE property = ?
                    GROUP BY 1
                ) c6 USING (id)
                LEFT JOIN (
                    SELECT a1.id, json_agg(row_to_json(a1.*)) AS annotations
                    FROM (
                        SELECT id, property, type, lang, value
                        FROM metadata a
                        WHERE property IN ($anPropSql)
                      UNION
                        SELECT r.id, property, 'REL' AS type, null AS lang, ids AS value
                        FROM relations r JOIN identifiers i ON r.target_id = i.id
                        WHERE property IN ($anPropSql)
                    ) a1
                    GROUP BY 1
                ) c7 USING (id)
        ";
        $param = [
            RDF::RDF_TYPE, RDF::OWL_DATATYPE_PROPERTY, RDF::OWL_OBJECT_PROPERTY, // with non-recursive term
            RDF::RDFS_SUB_PROPERTY_OF, RDF::OWL_EQUIVALENT_PROPERTY, // with recursive term
            RDF::RDFS_RANGE, RDF::RDFS_DOMAIN, // c3, c4
            RDF::SKOS_ALT_LABEL, RDF::RDFS_COMMENT, // c5, c6
        ];
        $param = array_merge($param, $anPropParam, $anPropParam);
        $query = $this->pdo->prepare($query);
        $query->execute($param);
        while ($p     = $query->fetch(PDO::FETCH_OBJ)) {
            $propList = json_decode($p->property);
            $prop     = new PropertyDesc($p, $propList, $this->schema->ontologyNamespace);
            if (!empty($prop->vocabs)) {
                $prop->setOntology($this);
            }
            foreach ($propList as $i) {
                $this->properties[$i] = $prop;
            }
            $this->properties[(string) $p->id]         = $prop;
            $this->distinctProperties[(string) $p->id] = $prop;
        }
    }

    private function loadRestrictions(): void {
        $query = "
            SELECT 
                t.id, class, onproperty, 
                coalesce(m3.value, m2.value) AS min,
                coalesce(m4.value, m2.value) AS max
            FROM
                (
                    SELECT i1.id, json_agg(i1.ids ORDER BY i1.ids) AS class
                    FROM 
                        identifiers i1
                        JOIN relations r USING (id)
                        JOIN identifiers i2 ON r.target_id = i2.id AND r.property = ? AND i2.ids = ?
                    GROUP BY 1
                ) t
                LEFT JOIN (
                    SELECT r.id, json_agg(ids) AS onproperty
                    FROM relations r JOIN identifiers i ON r.target_id = i.id AND r.property = ?
                    GROUP BY 1
                ) c1 USING (id)
                LEFT JOIN metadata m2 ON t.id = m2.id AND m2.property = ?
                LEFT JOIN metadata m3 ON t.id = m3.id AND m3.property = ?
                LEFT JOIN metadata m4 ON t.id = m4.id AND m4.property = ?
        ";
        $param = [
            $this->schema->parent, RDF::OWL_RESTRICTION, // t
            RDF::OWL_ON_PROPERTY, // c1
            RDF::OWL_CARDINALITY, RDF::OWL_MIN_CARDINALITY, RDF::OWL_MAX_CARDINALITY, // m2, m3, m4
        ];
        $query = $this->pdo->prepare($query);
        $query->execute($param);
        while ($r     = $query->fetch(PDO::FETCH_OBJ)) {
            $classList = json_decode($r->class);
            $rr        = new RestrictionDesc($r);
            foreach ($classList as $i) {
                $this->restrictions[$i] = $rr;
            }
            $this->restrictions[(string) $r->id] = $rr;
        }
    }

    /**
     * Combines class, property and restriction information
     * @return void
     */
    private function preprocess(): void {
        // assign properties to classes
        $classes = array_keys($this->classes);
        foreach ($this->distinctProperties as $p) {
            $classMatch       = array_intersect($p->domain, $classes);
            $processedClasses = [];
            foreach ($classMatch as $cid) {
                foreach ($this->classesRev[$cid] as $c) {
                    $cHash = (string) spl_object_id($c);
                    if (!isset($processedClasses[$cHash])) {
                        $pp                   = clone($p); // clone because restrictions apply to a {property, class}
                        $pp->recommendedClass = count(array_intersect($c->classes, $p->recommendedClass)) > 0;
                        foreach ($pp->property as $puri) {
                            $c->properties[$puri] = $pp;
                        }
                        $processedClasses[$cHash] = true;
                    }
                }
            }
        }

        // process restrictions
        $restrClasses = array_keys($this->restrictions);
        foreach ($this->classes as $c) {
            $restrMatch     = array_intersect($restrClasses, $c->classes);
            $processedRestr = [];
            foreach ($restrMatch as $rid) {
                $r     = $this->restrictions[$rid];
                $rHash = (string) spl_object_id($r);
                if (!isset($processedRestr[$rHash]) && isset($c->properties[$r->onProperty[0]])) {
                    try {
                        $processedRestr[$rHash] = true;
                        $p                      = $c->properties[$r->onProperty[0]];
                        if (!empty($r->range)) {
                            $p->range = $r->range;
                        }
                        if (!empty($r->min)) {
                            $p->min = $r->min;
                        }
                        if (!empty($r->max)) {
                            $p->max = $r->max;
                        }
                    } catch (OutOfBoundsException $e) {
                        
                    }
                }
            }
        }
    }

    /**
     * Fetches information about SKOS concepts and formats them as SkosConceptDesc
     * objects.
     * 
     * @param string $valuesQuery
     * @param array<mixed> $valuesParam
     * @return array<SkosConceptDesc>
     */
    private function fetchVocabularyValues(string $valuesQuery,
                                           array $valuesParam): array {
        $query = "
            WITH c AS ($valuesQuery)
            SELECT json_agg(row_to_json(t))
            FROM (
                SELECT *
                FROM
                    c
                    JOIN (
                        SELECT id, json_agg(ids) AS concept
                        FROM identifiers
                        GROUP BY 1
                    ) t0 USING (id)
                    LEFT JOIN (
                        SELECT id, json_agg(value) AS notation
                        FROM metadata
                        WHERE property = ?
                        GROUP BY 1
                    ) t1 USING (id)
                    JOIN (
                        SELECT id, json_agg(json_build_object('lang', lang, 'value', value)) AS label
                        FROM metadata
                        WHERE property = ?
                        GROUP BY 1
                    ) t2 USING (id)
                    LEFT JOIN (
                        SELECT id, json_agg(target_id) AS narrower
                        FROM relations
                        WHERE property = ?
                        GROUP BY 1
                    ) t3 USING (id)
                    LEFT JOIN (
                        SELECT id, json_agg(target_id) AS broader
                        FROM relations
                        WHERE property = ?
                        GROUP BY 1
                    ) t4 USING (id)
            ) t
        ";
        $param = [
            RDF::SKOS_NOTATION,
            $this->schema->label, // t2
            RDF::SKOS_NARROWER, // t3
            RDF::SKOS_BROADER, // t4
        ];
        $query = $this->pdo->prepare($query);
        $query->execute(array_merge($valuesParam, $param));
        $tmp   = json_decode($query->fetchColumn());

        $concepts = [];
        foreach ($tmp as $i) {
            $langs = array_map(function ($x) {
                return $x->lang;
            }, $i->label);
            $labels = array_map(function ($x) {
                return $x->value;
            }, $i->label);
            $i->label                        = array_combine($langs, $labels);
            $concept                         = new SkosConceptDesc($i);
            $concepts[(string) $concept->id] = $concept;
        }
        foreach ($concepts as $i) {
            $i->broader = array_map(function ($x) use ($concepts) {
                return $concepts[(string) $x];
            }, $i->broader);
            $i->narrower = array_map(function ($x) use ($concepts) {
                return $concepts[(string) $x];
            }, $i->narrower);
        }
        $result = [];
        foreach ($concepts as $c) {
            foreach ($c->concept as $i) {
                $result[$i] = $c;
            }
        }
        return $result;
    }
}
