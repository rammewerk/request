<?php

namespace Rammewerk\Component\Request\Error;

/**
 * Thrown when a file was not found.
 */
class FileNotFoundException extends FileException
{
    public function __construct(string $path)
    {
        parent::__construct(sprintf('The file "%s" does not exist', $path));
    }
}
