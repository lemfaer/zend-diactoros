<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Diactoros;

use PHPUnit_Framework_TestCase as TestCase;
use Psr\Http\Message\UploadedFileInterface;

class NormalizeUploadedFilesTest extends TestCase
{
    public function testCreatesUploadedFileFromFlatFileSpecification()
    {
        $files = array(
            'avatar' => array(
                'tmp_name' => 'phpUxcOty',
                'name' => 'my-avatar.png',
                'size' => 90996,
                'type' => 'image/png',
                'error' => 0,
            ),
        );

        $normalised = \Zend\Diactoros\normalizeUploadedFiles($files);

        $this->assertCount(1, $normalised);
        $this->assertInstanceOf("Psr\Http\Message\UploadedFileInterface", $normalised['avatar']);
        $this->assertEquals('my-avatar.png', $normalised['avatar']->getClientFilename());
    }

    public function testTraversesNestedFileSpecificationToExtractUploadedFile()
    {
        $files = array(
            'my-form' => array(
                'details' => array(
                    'avatar' => array(
                        'tmp_name' => 'phpUxcOty',
                        'name' => 'my-avatar.png',
                        'size' => 90996,
                        'type' => 'image/png',
                        'error' => 0,
                    ),
                ),
            ),
        );

        $normalised = \Zend\Diactoros\normalizeUploadedFiles($files);

        $this->assertCount(1, $normalised);
        $this->assertEquals('my-avatar.png', $normalised['my-form']['details']['avatar']->getClientFilename());
    }

    public function testTraversesNestedFileSpecificationContainingNumericIndicesToExtractUploadedFiles()
    {
        $files = array(
            'my-form' => array(
                'details' => array(
                    'avatars' => array(
                        'tmp_name' => array(
                            0 => 'abc123',
                            1 => 'duck123',
                            2 => 'goose123',
                        ),
                        'name' => array(
                            0 => 'file1.txt',
                            1 => 'file2.txt',
                            2 => 'file3.txt',
                        ),
                        'size' => array(
                            0 => 100,
                            1 => 240,
                            2 => 750,
                        ),
                        'type' => array(
                            0 => 'plain/txt',
                            1 => 'image/jpg',
                            2 => 'image/png',
                        ),
                        'error' => array(
                            0 => 0,
                            1 => 0,
                            2 => 0,
                        ),
                    ),
                ),
            ),
        );

        $normalised = \Zend\Diactoros\normalizeUploadedFiles($files);

        $this->assertCount(3, $normalised['my-form']['details']['avatars']);
        $this->assertEquals('file1.txt', $normalised['my-form']['details']['avatars'][0]->getClientFilename());
        $this->assertEquals('file2.txt', $normalised['my-form']['details']['avatars'][1]->getClientFilename());
        $this->assertEquals('file3.txt', $normalised['my-form']['details']['avatars'][2]->getClientFilename());
    }

    /**
     * This case covers upfront numeric index which moves the tmp_name/size/etc
     * fields further up the array tree
     */
    public function testTraversesDenormalizedNestedTreeOfIndicesToExtractUploadedFiles()
    {
        $files = array(
            'slide-shows' => array(
                'tmp_name' => array(
                    // Note: Nesting *under* tmp_name/etc
                    0 => array(
                        'slides' => array(
                            0 => '/tmp/phpYzdqkD',
                            1 => '/tmp/phpYzdfgh',
                        ),
                    ),
                ),
                'error' => array(
                    0 => array(
                        'slides' => array(
                            0 => 0,
                            1 => 0,
                        ),
                    ),
                ),
                'name' => array(
                    0 => array(
                        'slides' => array(
                            0 => 'foo.txt',
                            1 => 'bar.txt',
                        ),
                    ),
                ),
                'size' => array(
                    0 => array(
                        'slides' => array(
                            0 => 123,
                            1 => 200,
                        ),
                    ),
                ),
                'type' => array(
                    0 => array(
                        'slides' => array(
                            0 => 'text/plain',
                            1 => 'text/plain',
                        ),
                    ),
                ),
            ),
        );

        $normalised = \Zend\Diactoros\normalizeUploadedFiles($files);

        $this->assertCount(2, $normalised['slide-shows'][0]['slides']);
        $this->assertEquals('foo.txt', $normalised['slide-shows'][0]['slides'][0]->getClientFilename());
        $this->assertEquals('bar.txt', $normalised['slide-shows'][0]['slides'][1]->getClientFilename());
    }
}
