<?php

namespace RFM\Repository;

interface ItemInterface
{
    /**
     * Get storage instance associated with model item.
     *
     * @return StorageInterface
     */
    public function getStorage();

    /**
     * Associate storage with model item.
     *
     * @param string $storageName
     */
    public function setStorage($storageName);

    /**
     * Return thumbnail relative path from given path.
     * Work for both files and dirs paths.
     *
     * @return string
     */
    public function getThumbnailPath();
}