<?php

namespace Pentatrion\UploadBundle\Service;

interface FileManagerHelperInterface
{
    public function completeConfig($baseConfig = [], $locale = 'en'): array;
}
