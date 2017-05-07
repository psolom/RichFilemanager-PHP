<?php

namespace RFM\Repository;

/**
 *    BaseItemModel PHP class
 *
 *    Base class created to define base methods
 *
 *    @license    MIT License
 *    @author        Pavel Solomienko <https://github.com/servocoder/>
 *    @copyright    Authors
 */

class BaseItemModel
{
    const TYPE_FILE = 'file';
    const TYPE_FOLDER = 'folder';

    /**
     * File item model template
     *
     * @var array
     */
    protected $fileModel = [
        "id"    => '',
        "type"  => self::TYPE_FILE,
        "attributes" => [
            'name'      => '',
            'path'      => '',
            'readable'  => 1,
            'writable'  => 1,
            'created'   => '',
            'modified'  => '',
            'timestamp' => '',
            'height'    => 0,
            'width'     => 0,
            'size'      => 0,
        ]
    ];

    /**
     * Folder item model template
     *
     * @var array
     */
    protected $folderModel = [
        "id"    => '',
        "type"  => self::TYPE_FOLDER,
        "attributes" => [
            'name'      => '',
            'path'      => '',
            'readable'  => 1,
            'writable'  => 1,
            'created'   => '',
            'modified'  => '',
            'timestamp' => '',
        ]
    ];
}