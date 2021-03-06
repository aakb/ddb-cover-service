<?php

/**
 * @file
 * Wrapper for information returned from cover store.
 */

namespace App\Utils\CoverStore;

class CoverStoreItem
{
    private $url;
    private $vendor;
    private $size;
    private $width;
    private $height;
    private $originalFile;
    private $imageFormat;
    private $crc;
    private $autoGenerated = false;

    public function __toString()
    {
        $output = [];

        $output[] = str_repeat('-', 42);
        $output[] = 'URL: '.$this->getUrl();
        $output[] = 'Vendor: '.$this->getVendor();
        $output[] = 'Size: '.$this->getSize().' Bytes';
        $output[] = 'Width: '.$this->getWidth();
        $output[] = 'Height: '.$this->getHeight();
        $output[] = 'CRC: '.$this->getCrc();
        $output[] = 'Original file: '.$this->getOriginalFile();
        $output[] = 'Original image format: '.$this->getImageFormat();
        $output[] = 'Auto-generated: '.$this->isAutoGenerated();
        $output[] = str_repeat('-', 42);

        return implode("\n", $output);
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     *
     * @return CoverStoreItem
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return string
     */
    public function getVendor(): string
    {
        return $this->vendor;
    }

    /**
     * @param $vendor
     *
     * @return CoverStoreItem
     */
    public function setVendor($vendor): self
    {
        $this->vendor = $vendor;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @param int $size
     *
     * @return CoverStoreItem
     */
    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @param int $width
     *
     * @return CoverStoreItem
     */
    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @param int $height
     *
     * @return CoverStoreItem
     */
    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    /**
     * @return string
     */
    public function getCrc()
    {
        return $this->crc;
    }

    /**
     * @param string $crc
     *
     * @return CoverStoreItem
     */
    public function setCrc(string $crc): self
    {
        $this->crc = $crc;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOriginalFile()
    {
        return $this->originalFile;
    }

    /**
     * @param string $originalFile
     *
     * @return CoverStoreItem
     */
    public function setOriginalFile(string $originalFile): self
    {
        $this->originalFile = $originalFile;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getImageFormat()
    {
        return $this->imageFormat;
    }

    /**
     * @param string $imageFormat
     *
     * @return CoverStoreItem
     */
    public function setImageFormat(string $imageFormat): self
    {
        $this->imageFormat = $imageFormat;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAutoGenerated(): bool
    {
        return $this->autoGenerated;
    }

    /**
     * @param bool $autoGenerated
     */
    public function setAutoGenerated(bool $autoGenerated): void
    {
        $this->autoGenerated = $autoGenerated;
    }
}
