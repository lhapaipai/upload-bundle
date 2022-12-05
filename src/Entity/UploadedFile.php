<?php

namespace Pentatrion\UploadBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class UploadedFile
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $liipId;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $mimeGroup;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $mimeType;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $filename;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $directory;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $origin;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $imageWidth;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $imageHeight;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $type;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $size;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $updatedAt;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $icon;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $public;

    private $absolutePath;

    // chemin relatif par rapport aux origines définies dans pentatrion_upload.yaml
    // ex: projet/mon-projet/fichier.jpg
    public function getUploadRelativePath(): ?string
    {
        if (is_null($this->directory)) {
            return $this->filename;
        }

        if ('' === $this->directory) {
            return $this->filename;
        }

        return $this->directory.DIRECTORY_SEPARATOR.$this->filename;
    }

    public function __clone()
    {
        if (!is_null($this->id)) {
            $this->id = null;
        }
    }

    public function isEmpty(): bool
    {
        return is_null($this->filename) && is_null($this->origin);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getAbsolutePath(): ?string
    {
        return $this->absolutePath;
    }

    public function setAbsolutePath(?string $absolutePath): self
    {
        $this->absolutePath = $absolutePath;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getDirectory(): ?string
    {
        return $this->directory;
    }

    public function setDirectory(?string $directory): self
    {
        $this->directory = $directory;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): self
    {
        $this->origin = $origin;

        return $this;
    }

    public function getMimeGroup(): ?string
    {
        return $this->mimeGroup;
    }

    public function setMimeGroup(?string $mimeGroup): self
    {
        $this->mimeGroup = $mimeGroup;

        return $this;
    }

    public function getLiipId(): ?string
    {
        return $this->liipId;
    }

    public function setLiipId(?string $liipId): self
    {
        $this->liipId = $liipId;

        return $this;
    }

    public function getImageWidth(): ?int
    {
        return $this->imageWidth;
    }

    public function setImageWidth(?int $imageWidth): self
    {
        $this->imageWidth = $imageWidth;

        return $this;
    }

    public function getImageHeight(): ?int
    {
        return $this->imageHeight;
    }

    public function setImageHeight(?int $imageHeight): self
    {
        $this->imageHeight = $imageHeight;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function isPublic(): ?bool
    {
        return $this->public;
    }

    public function setPublic(?bool $public): self
    {
        $this->public = $public;

        return $this;
    }
}
