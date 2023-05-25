<?php

/* ********************
 *   Shared functions
 * ******************** */

// From a mixed var, return an integer value if it is integer.
// Return null if not.
function retInt($s)
{
    if (preg_match('/^\d{1,11}$/', $s)) {
        return intval($s);
    }
    return null;
}

// Format a float value to a fixed decimals, returning it as a float
function floatFormat($value, $decimals)
{
    return floatval(number_format($value, $decimals, ".", ""));
}
