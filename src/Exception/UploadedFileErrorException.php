<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Diactoros\Exception;

use RuntimeException;

class UploadedFileErrorException extends RuntimeException implements ExceptionInterface
{
    public static function forUnmovableFile()
    {
        return new self('Error occurred while moving uploaded file');
    }

    public static function dueToStreamUploadError($error)
    {
        return new self(sprintf(
            'Cannot retrieve stream due to upload error: %s',
            $error
        ));
    }

    public static function dueToUnwritablePath()
    {
        return new self('Unable to write to designated path');
    }

    public static function dueToUnwritableTarget($targetDirectory)
    {
        return new self(sprintf(
            'The target directory `%s` does not exists or is not writable',
            $targetDirectory
        ));
    }
}
