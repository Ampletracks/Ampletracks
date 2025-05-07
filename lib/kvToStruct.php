<?php

/**
 * Parse a list of key=>value pairs into a nested PHP data structure.
 * This version supports "optional slashes" around bracket segments.
 * 
 * For example, all of these forms are valid and will parse identically:
 *   - people/[0]/forename
 *   - people[0]/forename
 *   - people[0]forename
 *   - people(0)forename
 *   - people(0)/forename
 * 
 * Usage:
 *   $input = [
 *       'people/[0]/forename' => 'Alice',
 *       'people/[0]/surname'  => 'Smith',
 *       'people(1)forename'   => 'Bob',
 *       'people(1)/surname'   => 'Jones',
 *       'title'               => 'Example Form'
 *   ];
 * 
 *   $nested = kvToStruct($input);
 *   echo json_encode($nested, JSON_PRETTY_PRINT);
 */
function kvToStruct(array $keyValuePairs): array
{
    // Build an internal tree with metadata to distinguish arrays vs objects.
    $tree = [
        '__type' => 'object', // The root is treated as an object
        '__data' => []
    ];

    // Step 1: Insert each key=>value pair into the intermediate $tree structure.
    foreach ($keyValuePairs as $key => $value) {
        // Convert the raw key string into a list of segments
        // (object keys or array indices).
        $segments = parsePath($key);

        // Insert into the tree
        insertValue($tree, $segments, $value);
    }

    // Step 2: Convert the metadata structure into a pure nested PHP array
    //         (removing __type and __data, handling squishable vs literal arrays).
    return normalise($tree);
}

/**
 * Convert a single key string (e.g. "people(0)/forename") into an array
 * of path segments (e.g. ["people","(0)","forename"]).
 *
 * This handles:
 *  - optional slashes around brackets,
 *  - splitting on slashes,
 *  - detecting bracket tokens (e.g. "(3)" or "[2]").
 */
function parsePath(string $path): array
{
    // 1) Normalise: remove any slash immediately before an opening bracket
    //               or immediately after a closing bracket.
    //    e.g. "people/[0]/name" -> "people[0]/name"
    //         "people(0)/name" -> "people(0)name"
    $path = preg_replace('@/(?=\(|\[)@', '', $path);  // remove slash if next char is "(" or "["
    $path = preg_replace('@(?<=\]|\))/@', '', $path); // remove slash if prev char is "]" or ")"

    // 2) Split on remaining slashes to get big "chunks."
    //    For example, "people[0]forename" stays as one chunk,
    //    whereas "people[0]/forename" becomes ["people[0]forename"] and ["forename"] if there's a slash.
    $chunks = preg_split('@/+@', $path, -1, PREG_SPLIT_NO_EMPTY);

    $segments = [];
    foreach ($chunks as $chunk) {
        // 3) Within each chunk, separate bracket tokens from plain text.
        //    e.g. "people[0]forename" -> ["people", "[0]", "forename"]
        $parts = preg_split('@(\([0-9]+\)|\[[0-9]+\])@', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $segments[] = $p;
            }
        }
    }

    return $segments;
}

/**
 * Recursive function to insert a value into our tree structure.
 *
 * @param array  $node     The current node in the tree, passed by reference
 * @param array  $segments The remaining path segments
 * @param mixed  $value    The value to set
 */
function insertValue(array &$node, array $segments, $value)
{
    // If there are no more segments, store the final value.
    if (empty($segments)) {
        $node['__data'] = $value;
        $node['__final'] = true;
        return;
    }

    // Shift the next segment
    $segment = array_shift($segments);

    // Detect bracket types or a plain text key
    if (preg_match('/^\[(\d+)\]$/', $segment, $sqMatch)) {
        // SQUISHABLE array index, e.g. [0]
        $index = (int) $sqMatch[1];
        convertToArrayIfNeeded($node, true);
        if (!isset($node['__data'][$index])) {
            $node['__data'][$index] = ['__type' => 'object', '__data' => []];
        }
        insertValue($node['__data'][$index], $segments, $value);

    } elseif (preg_match('/^\((\d+)\)$/', $segment, $litMatch)) {
        // LITERAL array index, e.g. (0)
        $index = (int) $litMatch[1];
        convertToArrayIfNeeded($node, false);
        if (!isset($node['__data'][$index])) {
            $node['__data'][$index] = ['__type' => 'object', '__data' => []];
        }
        insertValue($node['__data'][$index], $segments, $value);

    } else {
        // Plain text => object key
        convertToObjectIfNeeded($node);
        if (!isset($node['__data'][$segment])) {
            $node['__data'][$segment] = ['__type' => 'object', '__data' => []];
        }
        insertValue($node['__data'][$segment], $segments, $value);
    }
}

/**
 * Mark the current node as an array (squishable or literal) if not already.
 *
 * @param array $node
 * @param bool  $squishable
 */
function convertToArrayIfNeeded(array &$node, bool $squishable)
{
    $desiredType = $squishable ? 'squashArray' : 'literalArray';

    if ($node['__type'] === $desiredType) {
        return; // Already correct
    }

    if ($node['__type'] === 'object') {
        // Convert from object => array
        // In a real system, we might worry about existing string keys. For simplicity,
        // assume no conflict or we overwrite.
        $node['__type'] = $desiredType;
    } else {
        // If it's a different array type, you have a conflict. Overwrite for demonstration.
        $node['__type'] = $desiredType;
    }
}

/**
 * Mark the current node as an object if not already.
 *
 * @param array $node
 */
function convertToObjectIfNeeded(array &$node)
{
    if ($node['__type'] === 'object') {
        return;
    }
    // If it's currently an array type, we override (or throw an error).
    $node['__type'] = 'object';
}

/**
 * Convert the internal metadata structure (with __type and __data)
 * into a plain PHP array or scalar.
 *
 * @param array $node
 * @return mixed A nested array or scalar suitable for json_encode
 */
function normalise(array $node)
{
    if (!isset($node['__type'])) {
        // Possibly an edge case if the structure isn't as expected
        return $node;
    }

    // If __data is not an array, it's likely a direct scalar
    if (!is_array($node['__data']) || isset($node['__final'])) {
        return $node['__data'];
    }

    switch ($node['__type']) {
        case 'object':
            // Normalise each child
            $result = [];
            foreach ($node['__data'] as $k => $child) {
                $result[$k] = normalise($child);
            }
            return $result;

        case 'literalArray':
            // Fill gaps with null
            if (empty($node['__data'])) {
                return [];
            }
            $maxIndex = max(array_keys($node['__data']));
            $arr = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                if (isset($node['__data'][$i])) {
                    $arr[$i] = normalise($node['__data'][$i]);
                } else {
                    $arr[$i] = null;
                }
            }
            return $arr;

        case 'squashArray':
            // Sort indices, remove gaps
            if (empty($node['__data'])) {
                return [];
            }
            ksort($node['__data'], SORT_NUMERIC);
            $arr = [];
            foreach ($node['__data'] as $child) {
                $arr[] = normalise($child);
            }
            return $arr;

        default:
            // Unknown type, return null or handle error
            return null;
    }
}

/* ---------------------------------------------------------------------
   Example usage (uncomment to test)

   $input = [
       'people/[0]/forename' => 'Alice',
       'people/[0]/surname'  => 'Smith',
       'people(1)forename'   => 'Bob',
       'people(1)/surname'   => 'Jones',
       'title'               => 'Example Form'
   ];

   $output = kvToStruct($input);

   echo "<pre>" . json_encode($output, JSON_PRETTY_PRINT) . "</pre>";
----------------------------------------------------------------------- */
