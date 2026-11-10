<?php

namespace App\Enums;

/**
 * How wide a place a row of market figures covers.
 */
enum MarketLevel: string
{
    case Country = 'country';
    case Region = 'region';
    case City = 'city';
    case District = 'district';
}
