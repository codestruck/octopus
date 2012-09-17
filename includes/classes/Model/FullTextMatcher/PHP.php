<?php

/**
 * A naive fulltext matcher that uses a combination of LIKE queries and
 * PHP comparison/sorting to produce a resultset.
 *
 * This is only appropriate for small result sets, in a pinch. For heavy-duty
 * production-level searching, use a "real" search indexer (Solr, MySQL fulltext,
 * whatever).
 */
class Octopus_Model_FullTextMatcher_PHP {

    /**
     * # of points awarded for matching at least the first characters of a
     * query term (so, in a search for "foo", "food" and "foolish" would
     * both get a point since they start with "foo").
     * @var integer
     */
    public $matchTermPoints = 1;

    /**
     * Maximum # of search terms to process.
     * @var integer
     */
    public $maxTerms = 8;

    /**
     * # of points awarded for matching a complete term.
     * @var integer
     */
    public $completeTermBonus = 1;

    /**
     * # of points awarded for matching all the terms in a query.
     * @var integer
     */
    public $completeQueryBonus = 10;

    public function filter(Octopus_Model_ResultSet $resultSet, $query) {

        // Step 1 -- break $query into terms to search for
        $terms = $this->tokenize($query);

        if (empty($terms)) {
            //  No actual query
            return $resultSet;
        }

        $terms = array_slice($terms, 0, $this->maxTerms);

        // Step 2 -- Find everything that matches those terms, even a little
        $modelClass = $resultSet->getModel();
        $searchFields = call_user_func(array($modelClass, '__getSearchFields'));

        $criteria = $this->createFreeTextCriteria($resultSet, $terms, $searchFields);
        $sorter = new Octopus_Model_FullTextMatcher_PHP_Sorter($this, $terms, $searchFields);

        return $resultSet->where($criteria)->sortUsing(array($sorter, 'compare'));
    }

    /**
     * @return Array An array of criteria to use to do the initial filtering
     * at the DB-level.
     */
    public function createFreeTextCriteria(Octopus_Model_ResultSet $resultSet, Array $terms, Array $searchFields) {

        $dummy = $resultSet->getModelInstance();

        $terms = array_unique($terms);

        $criteria = array();
        foreach($searchFields as $f) {

            foreach($terms as $term) {

                $restrict = $f['field']->restrictFreeText($dummy, $term);

                if ($restrict) {
                    if (count($criteria)) $criteria[] = 'OR';
                    $criteria[] = $restrict;
                }

            }

        }

        return $criteria;

    }

    /**
     * Takes a free-text query and turns it into an array of individual search
     * terms to match.
     * @param String $query A free-text query. Anything wrapped in quotes is
     * interpreted literally (passed through as a single term in the result
     * array).
     * @return Array
     */
    public function tokenize($query) {

        $stopWords = array(
            '' => 1,
            "a" => 1,
            "about" => 1,
            "and" => 1,
            "as" => 1,
            "by" => 1,
            "each" => 1,
            "ever" => 1,
            "for" => 1,
            "got" => 1,
            "i" => 1,
            "i'm" => 1,
            "i've" => 1,
            "im" => 1,
            "ive" => 1,
            "me" => 1,
            "my" => 1,
            "of" => 1,
            "that" => 1,
            "the" => 1,
        );

        $punctuation = array(
            ',' => 1,
            ';' => 1,
            '.' => 1,
            '(' => 1,
            ')' => 1,
            ':' => 1,
        );

        $query = strtolower($query);
        $query = strip_tags($query);
        $query = preg_replace('/\s+/', ' ', $query);
        $query = trim($query);

        $inLiteral = false;
        $len = strlen($query);
        $term = '';
        $terms = array();

        // Parse query, allowing for "quoted string literals"

        for($i = 0; $i < $len; $i++) {

            $c = $query[$i];

            if ($c === '"') {

                $inLiteral = !$inLiteral;

                if ($term) {
                    $terms[] = $term;
                    $term = '';
                }

                continue;

            }

            if ($inLiteral) {
                $term .= $c;
                continue;
            }

            if ($c === ' ') {

                if (!isset($stopWords[$term])) {
                    $terms[] = $term;
                }

                $term = '';
                continue;
            }

            if (isset($punctuation[$c])) {
                continue;
            }

            $term .= $c;

        }

        if ($inLiteral || !isset($stopWords[$term])) {
            $terms[] = $term;
        }

        return $terms;

    }

}

/**
 * @internal
 * Gets around 5.2's lack of support for anonymous functions.
 */
class Octopus_Model_FullTextMatcher_PHP_Sorter {

    private $matcher;
    private $terms;
    private $searchFields;

    private $termPattern;
    private $termCount;
    private $pointCache = array();

    public function __construct(Octopus_Model_FullTextMatcher_PHP $matcher, Array $terms, Array $searchFields) {

        $this->matcher = $matcher;
        $this->searchFields = $searchFields;

        $this->terms = $terms;
        $this->termCount = count($terms);

        // Generate a regex to use to calculate how many terms match
        $this->termPattern = '/';
        foreach($terms as $term) {

            $quotedTerm = preg_quote($term, '/');
            $this->termPattern .= '(\b(' . $quotedTerm . '([^\s]*))?)';

        }
        $this->termPattern .= '/i';


    }

    /**
     * Comparator function used for sorting.
     */
    public function compare(Octopus_Model $x, Octopus_Model $y) {

        $idX = spl_object_hash($x);
        $idY = spl_object_hash($y);

        if (isset($this->pointCache[$idX])) {
            $pointsX = $this->pointCache[$idX];
        } else {
            $pointsX = $this->calculatePoints($x);
            $this->pointCache[$idX] = $pointsX;
        }

        if (isset($this->pointCache[$idY])) {
            $pointsY = $this->pointCache[$idY];
        } else {
            $pointsY = $this->calculatePoints($y);
            $this->pointCache[$idY] = $pointsY;
        }

        return $pointsY - $pointsX;

    }

    /**
     * @param  Octopus_Model        $item
     * @param  Octopus_Model_Field  $field
     * @return Number
     */
    private function caclculateFieldPoints(Octopus_Model $item, Octopus_Model_Field $field) {

        $value = trim($field->accessValue($item));
        if (!$value) return 0;

        $valueTokens = $this->matcher->tokenize($value);
        $normalizedValue = implode(' ', $valueTokens);

        if (!preg_match_all($this->termPattern, $normalizedValue, $matches, PREG_SET_ORDER)) {
            return 0;
        }

        $score = 0;
        foreach($matches as $m) {

            $completeTermsMatched = 0;

            foreach($this->terms as $index => $term) {

                $fullMatch = $m[($index * 3) + 1];
                if (!$fullMatch) continue;

                $extraChars = $m[($index * 3) + 3];

                if (!$extraChars) $completeTermsMatched++;

                $score += $this->matcher->matchTermPoints;

            }

            $score += ($completeTermsMatched * $this->matcher->completeTermBonus);
            if ($completeTermsMatched === $this->termCount) {
                $score += $this->matcher->completeQueryBonus;
            }

        }

        return $score;

    }

    private function calculatePoints(Octopus_Model $item) {

        $score = 0;

        foreach($this->searchFields as $f) {

            $field = $f['field'];
            $fieldScore = $this->caclculateFieldPoints($item, $field);
            $fieldScore *= $f['weight'];

            $score += $fieldScore;

        }

        return $score;
    }

}