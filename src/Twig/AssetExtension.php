<?php

namespace Pentatrion\UploadBundle\Twig;

use App\Service\FileInfosHelper;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Pentatrion\UploadBundle\Service\FileInfosHelperInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
            new TwigFunction('uploaded_asset', [$this, 'getUploadedAssetPath']),
        ];
    }

    public function getUploadedAssetPath($uploadRelativePath, $filter = null, $origin = 'public')
    {
      $id = $this->fileInfosHelper->getId($uploadRelativePath, $origin);
      $extension = substr($id, strrpos($id, '.') + 1);

      if (is_null($filter) || $extension === 'svg') {
        // si on n'a pas défini de filtre on affiche l'original dans ce cas seul les fichiers
        // publics peuvent l'afficher
        return $this->fileInfosHelper->getWebPath($uploadRelativePath, $origin);
      } else {
        return $this->fileInfosHelper->getUrlThumbnail($id, $filter);
      }
    }

}
