<?php

namespace {

    function simplifyLogicExpression($expression) {

        list($condensed, $tests) = SimplifyLogicExpression\parseExpression($expression);
        $condensed = preg_replace('/\{\{(AND|OR|NOT|AND NOT)\}\}/', '$1', $condensed);
        $simplified = SimplifyLogicExpression\simplifyBooleanExpression($condensed);
        $simplified = preg_replace('/(AND|OR|NOT)/', '{{$1}}', $simplified);
        $result = SimplifyLogicExpression\reconstituteExpression($simplified, $tests);
        return $result;
    }

}

namespace SimplifyLogicExpression {

    see: https://chatgpt.com/share/67dbe238-2b30-8004-a672-405741f14800

    /**
    * Data structure for AST nodes.
    */
    class Node {
        public $type;   // 'var', 'not', 'and', 'or'
        public $value;  // For 'var', e.g. "[123]"; for ops, 'NOT', 'AND', 'OR'
        public $left;   // Child node (for 'not') or left child (for 'and'/'or')
        public $right;  // Right child (for 'and'/'or')

        public function __construct($type, $value = null, $left = null, $right = null) {
            $this->type  = $type;
            $this->value = $value;
            $this->left  = $left;
            $this->right = $right;
        }
    }

    /**
    * Splits the input string into an array of tokens: parentheses, operators, and bracketed variables (like [123]).
    */
    function tokenize($input) {
        $input = trim($input);
        // Regex to capture parentheses, AND, OR, NOT, bracketed vars like [123], ignoring whitespace
        preg_match_all(
            '/\(|\)|\bAND\b|\bOR\b|\bNOT\b|\[[0-9]+\]|\s+/i',
            $input,
            $matches
        );

        $tokens = [];
        foreach ($matches[0] as $raw) {
            $tk = strtoupper(trim($raw));
            if ($tk !== '') {
                $tokens[] = $tk;
            }
        }
        return $tokens;
    }

    /**
    * A simple recursive‐descent parser implementing operator precedence:
    *
    *   expression := or_expr
    *   or_expr  := and_expr ( "OR" and_expr )*
    *   and_expr := not_expr ( "AND" not_expr )*
    *   not_expr := ("NOT")? primary
    *   primary  := variable | "(" expression ")"
    *
    *   variable := something like "[123]"
    */
    class Parser {
        private $tokens;
        private $pos;

        public function __construct($tokens) {
            $this->tokens = $tokens;
            $this->pos    = 0;
        }

        public function currentToken() {
            return ($this->pos < count($this->tokens)) ? $this->tokens[$this->pos] : null;
        }

        private function eat($expected = null) {
            if ($expected !== null && $this->currentToken() !== $expected) {
                throw new \Exception("Parse error: expected '$expected', got '".$this->currentToken()."'");
            }
            $cur = $this->currentToken();
            $this->pos++;
            return $cur;
        }

        public function parseExpression() {
            // Top of grammar
            return $this->parseOrExpr();
        }

        private function parseOrExpr() {
            $node = $this->parseAndExpr();
            while ($this->currentToken() === 'OR') {
                $this->eat('OR');
                $right = $this->parseAndExpr();
                $node = new Node('or', 'OR', $node, $right);
            }
            return $node;
        }

        private function parseAndExpr() {
            $node = $this->parseNotExpr();
            while ($this->currentToken() === 'AND') {
                $this->eat('AND');
                $right = $this->parseNotExpr();
                $node = new Node('and', 'AND', $node, $right);
            }
            return $node;
        }

        private function parseNotExpr() {
            if ($this->currentToken() === 'NOT') {
                $this->eat('NOT');
                $sub = $this->parseNotExpr();
                return new Node('not', 'NOT', $sub);
            }
            return $this->parsePrimary();
        }

        private function parsePrimary() {
            $tok = $this->currentToken();
            if ($tok === '(') {
                $this->eat('(');
                $node = $this->parseExpression();
                $this->eat(')');
                return $node;
            }
            // Otherwise, expect a bracketed variable
            if (preg_match('/^\[[0-9]+\]$/', $tok)) {
                $this->eat(); // consume the variable token
                return new Node('var', $tok);
            }
            //throw new \Exception("Unexpected token '$tok' in parsePrimary()");
        }
    }

    /**
    * Given an expression string, build its AST.
    */
    function buildAST($expression) {
        $tokens = tokenize($expression);
        $parser = new Parser($tokens);
        $root   = $parser->parseExpression();

        // Ensure we've consumed all tokens
        if ($parser->currentToken() !== null) {
            throw new \Exception("Extra tokens left after parse.");
        }
        return $root;
    }

    /**
    * Pretty‐print the AST with minimal parentheses, collapsing multiple NOTs.
    */
    function printNodeMin($node, $parentPrecedence = 0) {
        $precedences = [
            'or'  => 1,
            'and' => 2,
            'not' => 3,
            'var' => 4
        ];
        $thisPrec = $precedences[$node->type];

        switch ($node->type) {
            case 'var':
                return $node->value;

            case 'not':
                // Collapse consecutive NOTs
                $countNot = 1;
                $child = $node->left;
                while ($child->type === 'not') {
                    $countNot++;
                    $child = $child->left;
                }
                // Even number of NOT => they cancel; odd => effectively one NOT
                if ($countNot % 2 === 0) {
                    // no NOT at all
                    return printNodeMin($child, $parentPrecedence);
                } else {
                    // just one NOT
                    $childStr = printNodeMin($child, $precedences['not']);
                    // Don't remove parnetheses for NOT
                    /*
                    // if child's precedence is lower, we need parentheses
                    if (0 && $precedences[$child->type] < $precedences['not']) {
                        $childStr = "($childStr)";
                    }
                    */
                    return "NOT ( $childStr )";
                }

            case 'and':
            case 'or':
                $op = $node->value; // 'AND' or 'OR'
                // left
                $leftStr = printNodeMin($node->left, $thisPrec);
                if ($precedences[$node->left->type] < $thisPrec) {
                    $leftStr = "($leftStr)";
                }
                // right
                $rightStr = printNodeMin($node->right, $thisPrec);
                if ($precedences[$node->right->type] < $thisPrec) {
                    $rightStr = "($rightStr)";
                }
                return $leftStr . " " . $op . " " . $rightStr;

            default:
                throw new \Exception("Unknown node type: " . $node->type);
        }
    }

    /**
    * Public helper: parse and simplify
    */
    function simplifyBooleanExpression($expr) {
        $ast = buildAST($expr);
        return printNodeMin($ast);
    }

    /***********************************************************************
    * TEST CASES
    * We provide 20 expressions plus their expected simplified result.
    **********************************************************************/
    $testCases = [
        // 1
        [
            'expr'     => '[123]',
            'expected' => '[123]'
        ],
        // 2
        [
            'expr'     => 'NOT [123]',
            'expected' => 'NOT [123]'
        ],
        // 3
        [
            'expr'     => 'NOT (NOT [123])',
            'expected' => '[123]'   // double NOT collapses
        ],
        // 4
        [
            'expr'     => '[123] AND [456]',
            'expected' => '[123] AND [456]'
        ],
        // 5
        [
            'expr'     => '[123] OR [456]',
            'expected' => '[123] OR [456]'
        ],
        // 6
        [
            'expr'     => '( ([123] OR [456]) AND [789] )',
            // The AND is top-level, with left child = ( [123] OR [456] ), so parentheses are needed
            'expected' => '([123] OR [456]) AND [789]'
        ],
        // 7
        [
            'expr'     => '[123] OR ([456] AND [789])',
            // Actually, because AND has higher precedence, *if* this is the right child of OR,
            // we do need parentheses. Minimal output is: [123] OR [456] AND [789]
            // but the code checks: child type=AND (precedence=2), parent=OR (precedence=1).
            // Because child's precedence is strictly greater, no parentheses are needed.
            // So the code will produce: [123] OR [456] AND [789].
            // That is logically correct under typical precedence rules.
            'expected' => '[123] OR [456] AND [789]'
        ],
        // 8
        [
            'expr'     => '( NOT ([123]) )',
            'expected' => 'NOT [123]'
        ],
        // 9
        [
            'expr'     => 'NOT NOT NOT [123]',
            // 3 times NOT => effectively one NOT
            'expected' => 'NOT [123]'
        ],
        // 10
        [
            'expr'     => 'NOT ([123] AND NOT ([456]))',
            // Inside: [123] AND NOT [456]
            // Then top-level NOT => we do need parentheses around the child because it's AND
            'expected' => 'NOT ([123] AND NOT [456])'
        ],
        // 11
        [
            'expr'     => '[100] AND ( NOT([200]) OR [300] )',
            // Child is (NOT [200] OR [300]) which is an OR expression
            // AND (precedence=2), OR (precedence=1): child is *lower*, so parentheses needed
            // The NOT [200] inside does not need parentheses for itself:
            // => [100] AND (NOT [200] OR [300])
            'expected' => '[100] AND (NOT [200] OR [300])'
        ],
        // 12
        [
            'expr'     => '( [123] OR [456] ) OR [789]',
            // OR on left, OR on right => same precedence => no parentheses needed
            // => [123] OR [456] OR [789]
            'expected' => '[123] OR [456] OR [789]'
        ],
        // 13
        [
            'expr'     => '[123] AND [456] AND [789]',
            // straight left-associative => [123] AND [456] AND [789]
            'expected' => '[123] AND [456] AND [789]'
        ],
        // 14
        [
            'expr'     => '( [123] AND [456] ) OR [789]',
            // The left child is AND, the parent is OR => parentheses needed around the left
            'expected' => '([123] AND [456]) OR [789]'
        ],
        // 15
        [
            'expr'     => '[123] OR ([456] AND [789])',
            // as discussed, minimal is [123] OR [456] AND [789] because AND > OR
            'expected' => '[123] OR [456] AND [789]'
        ],
        // 16
        [
            'expr'     => '( ( [100] OR [200] ) AND ( NOT(NOT [300]) ) )',
            // Inside NOT(NOT [300]) => collapses to [300]
            // => ( ([100] OR [200]) AND [300] )
            // The top-level node is AND, whose left child is OR => parentheses needed
            // The entire expression is top-level => no parentheses around the whole.
            'expected' => '([100] OR [200]) AND [300]'
        ],
        // 17
        [
            'expr'     => '[100] OR NOT [200] AND [300]',
            // parse => OR( [100], AND( NOT [200], [300] ))
            // AND has higher precedence => no parentheses needed
            'expected' => '[100] OR NOT [200] AND [300]'
        ],
        // 18
        [
            'expr'     => 'NOT([100] OR [200]) AND NOT ([300] OR [400])',
            // => AND( NOT(OR(...)), NOT(OR(...)) )
            // Child is OR => parentheses needed under NOT
            'expected' => 'NOT ([100] OR [200]) AND NOT ([300] OR [400])'
        ],
        // 19
        [
            'expr'     => '( [123] AND ([456] OR [789]) ) OR [999]',
            // => OR( AND( [123], OR([456], [789]) ), [999] )
            // AND is precedence=2, OR is precedence=1 => keep parentheses around ( [456] OR [789] ).
            // Then the left child of the top-level OR is AND => we do need parentheses around that,
            // => ( [123] AND ([456] OR [789]) ) OR [999]
            'expected' => '([123] AND ([456] OR [789])) OR [999]'
        ],
        // 20
        [
            'expr'     => 'NOT NOT [111] OR NOT NOT ( NOT NOT [222] )',
            // `NOT NOT [111]` => even => collapses to [111]
            // `NOT NOT ( NOT NOT [222] )` => inside we have `NOT NOT [222]` => collapses to [222],
            // so entire sub‐expression => `NOT NOT [222]` => again collapses => [222].
            // So overall => [111] OR [222]
            'expected' => '[111] OR [222]'
        ],
    ];

    function runTests() {
        /**
        * Now run the tests
        */
        echo "Running test cases...\n\n";
        foreach ($testCases as $i => $tc) {
            $input    = $tc['expr'];
            $expected = $tc['expected'];

            $result   = simplifyBooleanExpression($input);

            $testNum = $i + 1;
            if ($result === $expected) {
                echo "Test #$testNum: PASS\n";
            } else {
                echo "Test #$testNum: FAIL\n";
                echo "   Expression: $input\n";
                echo "   Expected:   $expected\n";
                echo "   Got:        $result\n";
            }
        }

        echo "\nDone.\n";
    }

    /**
    * Parse a textual expression into a condensed version and a list of extracted tests.
    *
    * @param string $expression The full search expression, e.g. '( NOT ( name contains "ben" ) AND age < 12 ) OR height > 6'
    * @return array An array containing:
    *               [0] => string Condensed expression, e.g. '( NOT ( [0] ) AND [1] ) OR [2]'
    *               [1] => array  List of tests extracted, e.g. ['name contains "ben"', 'age < 12', 'height > 6']
    */
    function parseExpression(string $expression): array
    {
        /**
        * This pattern captures:
        * 1) One or more column names (possibly comma-separated).
        * 2) One of the recognised operators (words or symbols).
        * 3) One of the following forms for the value:
        *    - A double-quoted string, e.g. "some text here"
        *    - A numeric (int or float, optional leading sign), e.g. 42 or -3.14
        *    - A single unquoted token (if not numeric or spaced), e.g. abc
        */
        $pattern = '/
            ([^\(\{\}]+)                                         # (1) Column name(s)
            \s+                                                  # One or more whitespace chars
            \{\{(between|equals|greater\s+than|less\s+than|
            greater\s+than\s+or\sequal\sto|less\s+than\s+or\sequal\sto|
            contains|starts\s+with|ends\s+with|>|<|>=|<=|=)\}\}  # (2) Operator
            \s+                                                  # One or more whitespace chars
            ([^\)]+)                                             # (3) Value: quoted string, or number, or single token
        /ix';

        $tests = [];

        // Use a callback to collect each match into the $tests array and replace it in the expression
        $condensedExpression = preg_replace_callback(
            $pattern,
            function ($matches) use (&$tests) {
                $testStr = $matches[0];
                $index   = count($tests);  // The new index for this test
                $tests[] = $testStr;       // Store the exact matched test
                return '[' . $index . ']';
            },
            $expression
        );

        return [$condensedExpression, $tests];
    }

    /**
    * Reconstruct the original expression from the condensed string and the list of tests.
    *
    * @param string $condensedExpression The simplified expression, e.g. '( NOT ( [0] ) AND [1] ) OR [2]'
    * @param array $tests An array of tests, e.g. ['name contains "ben"', 'age < 12', 'height > 6']
    * @return string The fully rebuilt expression.
    */
    function reconstituteExpression(string $condensedExpression, array $tests): string
    {
        // Replace placeholders [0], [1], etc. with the corresponding test from $tests
        return preg_replace_callback(
            '/\[(\d+)\]/',
            function ($matches) use ($tests) {
                $index = (int) $matches[1];
                return $tests[$index] ?? '[UNKNOWN]';
            },
            $condensedExpression
        );
    }

}