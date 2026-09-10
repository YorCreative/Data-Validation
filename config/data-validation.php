<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Field Value Cache Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of resolved field values held in memory during validation.
    | Larger limits trade memory for fewer redundant lookups when the same
    | nested field is referenced repeatedly (e.g. cross-field comparisons in
    | wildcard rules).
    |
    */
    'field_cache_limit' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Parsed Rules Cache Size
    |--------------------------------------------------------------------------
    |
    | Maximum number of parsed pipe-string rule sets cached statically across
    | Validator instances. Higher values speed up repeat validation runs that
    | reuse the same rule strings.
    |
    */
    'parsed_rules_cache' => 500,

    /*
    |--------------------------------------------------------------------------
    | Wildcard Chunk Size
    |--------------------------------------------------------------------------
    |
    | Number of items processed per wildcard traversal chunk. The Validator
    | will fall back to a dynamic heuristic when this is left null.
    |
    */
    'chunk_size' => 500,
];
