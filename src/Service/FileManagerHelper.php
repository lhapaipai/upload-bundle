<?php

namespace Pentatrion\UploadBundle\Service;

class FileManagerHelper implements FileManagerHelperInterface
{
    protected $origins;

    public function __construct($uploadOrigins)
    {
        $this->origins = $uploadOrigins;
    }

    public function completeConfig($baseConfig = [], $locale = 'en'): array
    {
        $entryPoints = $baseConfig['entryPoints'];

        $completeEntryPoints = [];
        foreach ($entryPoints as $entryPoint) {
            $originName = isset($entryPoint['origin']) ? $entryPoint['origin'] : 'public';
            $completeEntryPoints[] = array_merge([
                'directory' => '',
                'origin' => $originName,
                'readOnly' => false,
                'icon' => 'fa-link-1',
                'label' => 'Répertoire principal',
                'webPrefix' => isset($this->origins[$originName]['web_prefix']) ? $this->origins[$originName]['web_prefix'] : null
            ], $entryPoint);
        }
        $fileUpload = isset($baseConfig['fileUpload']) && is_array($baseConfig['fileUpload'])
            ? $baseConfig['fileUpload']
            : [];
        $fileUpload = array_merge([
            'maxFileSize' => 10 * 1024 * 1024,
            'fileType' => [
                "text/*",
                "image/*", // image/vnd.adobe.photoshop  image/x-xcf
                "video/*",
                "audio/*"
            ]
        ], $fileUpload);

        unset($baseConfig['entryPoints']);
        unset($baseConfig['fileUpload']);

        return array_merge([
            'endPoint' => "/media-manager",
            'fileValidation' => null,
            'entryPoints' => $completeEntryPoints,
            'fileUpload' => $fileUpload,
            'locale' => $locale,
            'injectCssVars' => true,
            'themePrefix' => "penta",
            'form' => [
                'filter' => 'small',
                'type'   => 'image'
            ]
        ], $baseConfig);
    }
}
