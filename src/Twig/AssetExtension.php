<?php

namespace Pentatrion\UploadBundle\Twig;

use Pentatrion\UploadBundle\Service\FileInfosHelperInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssetExtension extends AbstractExtension
{
    private $fileInfosHelper;

    public function __construct(FileInfosHelperInterface $fileInfosHelper)
    {
        $this->fileInfosHelper = $fileInfosHelper;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('uploaded_file_id', [$this, 'getUploadedFileId']),
            new TwigFunction('uploaded_file_web_path', [$this, 'getUploadedFileWebPath']),
            new TwigFunction('uploaded_image_filtered', [$this, 'getUploadedImageFiltered']),
        ];
    }

    public function getUploadedFileId($uploadRelativePath, $originName = null): string
    {
        return $this->fileInfosHelper->getId($uploadRelativePath, $originName);
    }

    public function getUploadedFileWebPath($uploadRelativePath, $originName = null): string
    {
        return $this->fileInfosHelper->getWebPath($uploadRelativePath, $originName);
    }

    public function getUploadedImageFiltered($uploadRelativePath, $filter, $originName = null): string
    {
        $id = $this->fileInfosHelper->getId($uploadRelativePath, $originName);
        $extension = substr($id, strrpos($id, '.') + 1);
        if ($extension === 'svg') {
            return $this->fileInfosHelper->getWebPath($uploadRelativePath, $originName);
        } else {
            $liipPath = $this->fileInfosHelper->getLiipPath($uploadRelativePath, $originName);
            return $this->fileInfosHelper->getUrlThumbnail($liipPath, $filter);
        }
    }
}
