<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Utilities\Exceptions;

use RuntimeException;
use Throwable;

class MultipleItemsFoundException extends RuntimeException
{
    /**
     * The number of items found.
     *
     * @var int
     */
    public $count;

    /**
     * Create a new exception instance.
     *
     * @param int            $count
     * @param int            $code
     * @param Throwable|null $previous
     *
     * @return void
     */
    public function __construct($count, $code = 0, $previous = null)
    {
        $this->count = $count;

        parent::__construct("{$count} items were found.", $code, $previous);
    }

    /**
     * Get the number of items found.
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }
}
