<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Pentatrion\UploadBundle\Service;

use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Imagine\Cache\Helper\PathHelper;
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Liip\ImagineBundle\Imagine\Cache\Resolver\WebPathResolver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RequestContext;

class AbsoluteWebPathResolver extends WebPathResolver implements ResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function resolve($path, $filter): string
    {
        return sprintf(
            '/%s',
            ltrim($this->getFileUrl($path, $filter), '/')
        );
    }

    public function getFilePath($path, $filter)
    {
        return $this->webRoot . '/' . $this->getFullPath($path, $filter);
    }

    private function getFullPath($path, $filter)
    {
        // crude way of sanitizing URL scheme ("protocol") part
        $path = str_replace('://', '---', $path);

        return $this->cachePrefix . '/' . $filter . '/' . ltrim($path, '/');
    }
}
