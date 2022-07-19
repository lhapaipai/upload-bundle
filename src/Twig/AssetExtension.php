<?php

namespace Pentatrion\UploadBundle\Twig;

use Pentatrion\UploadBundle\Service\UploadedFileHelperInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssetExtension extends AbstractExtension
{
    private $uploadedFileHelper;

    public function __construct(UploadedFileHelperInterface $uploadedFileHelper)
    {
        $this->uploadedFileHelper = $uploadedFileHelper;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('uploaded_file_liip_id', [$this, 'getUploadedFileLiipId']),
            new TwigFunction('uploaded_file_web_path', [$this, 'getUploadedFileWebPath']),
            new TwigFunction('uploaded_image_filtered', [$this, 'getUploadedImageFiltered']),
        ];
    }

    public function getUploadedFileId($uploadRelativePath, $originName = null): string
    {
        return $this->uploadedFileHelper->getLiipId($uploadRelativePath, $originName);
    }

    public function getUploadedFileWebPath($uploadRelativePath, $originName = null): string
    {
        return $this->uploadedFileHelper->getWebPath($uploadRelativePath, $originName);
    }

    public function getUploadedImageFiltered($uploadRelativePath, $filter, $originName = null): string
    {
        $liipId = $this->uploadedFileHelper->getLiipId($uploadRelativePath, $originName);
        $extension = substr($liipId, strrpos($liipId, '.') + 1);
        if ($extension === 'svg') {
            return $this->uploadedFileHelper->getWebPath($uploadRelativePath, $originName);
        } else {
            $liipPath = $this->uploadedFileHelper->getLiipPath($uploadRelativePath, $originName);
            return $this->uploadedFileHelper->getUrlThumbnail($liipPath, $filter);
        }
    }
}
