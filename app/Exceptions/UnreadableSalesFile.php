<?php

namespace App\Exceptions;

use Exception;

/**
 * A file that is not a quarter of sale deeds as the ministry publishes them.
 * The message is written for the person who uploaded it.
 */
class UnreadableSalesFile extends Exception {}
