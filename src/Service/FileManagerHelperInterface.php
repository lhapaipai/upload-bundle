<?php

namespace Pentatrion\UploadBundle\Service;

interface FileManagerHelperInterface
{
    public static function completeConfig($baseConfig = [], $locale = 'en'): array;
}
