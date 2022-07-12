<?php

namespace Pentatrion\UploadBundle\Form;

use Pentatrion\UploadBundle\Service\FileManagerHelperInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilePickerType extends AbstractType
{
    private $fileManagerHelper;
    private $locale;

    public function __construct(
        FileManagerHelperInterface $fileManagerHelper,
        RequestStack $requestStack
    ) {
        $this->fileManagerHelper = $fileManagerHelper;
        $this->locale = substr($requestStack->getCurrentRequest()->getLocale(), 0, 2);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $fileManagerConfig = ($this->fileManagerHelper->completeConfig($options['fileManagerConfig'], $this->locale)
        );
        $fileManagerConfig['multiple'] = false;

        $filePickerConfig = array_merge([
            'multiple'      => false,
            'filter' => 'small',
            'type'   => 'image'
        ], $options['filePickerConfig']);

        $view->vars['attr']['data-name'] = $view->vars['full_name'];
        $view->vars['attr']['data-minifilemanager'] = json_encode($fileManagerConfig);
        $view->vars['attr']['data-file-picker'] = json_encode($filePickerConfig);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'filePickerConfig' => [],
            'fileManagerConfig' => [
                'entryPoints' => [
                    [
                        'label' => 'Uploads',
                        'directory' => '',
                        'origin' => 'public_uploads',
                        'readOnly' => false,
                        'icon' => 'fa-lock'
                    ]
                ],
                'fileValidation' => null,
                'fileUpload' => [
                    'maxFileSize' => 10 * 1024 * 1024,
                    'fileType' => [
                        "text/*",
                        "image/*", // image/vnd.adobe.photoshop  image/x-xcf
                        "video/*",
                        "audio/*"
                    ]
                ],
                'locale' => 'fr',
            ],
        ]);
    }

    public function getParent()
    {
        return UploadedFileType::class;
    }
}
