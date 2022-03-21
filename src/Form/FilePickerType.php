<?php

namespace Pentatrion\UploadBundle\Form;

use Pentatrion\UploadBundle\Service\FileInfosHelper;
use Pentatrion\UploadBundle\Service\FileInfosHelperInterface;
use Pentatrion\UploadBundle\Service\FileManagerHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilePickerType extends AbstractType
{
    private $fileManagerHelper;
    private $fileInfosHelper;
    private $locale;

    public function __construct(FileManagerHelper $fileManagerHelper, FileInfosHelperInterface $fileInfosHelper, RequestStack $requestStack)
    {
        $this->fileManagerHelper = $fileManagerHelper;
        $this->fileInfosHelper = $fileInfosHelper;
        $this->locale = $requestStack->getCurrentRequest()->getLocale();
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $value = $form->getData();

        $fileManagerConfig = ($this->fileManagerHelper->completeConfig($options['fileManagerConfig'], $this->locale)
        );

        $formPreviewConfig = array_merge([
            'multiple'      => false,
            'filter' => 'small',
            'type'   => 'image'
        ], $options['formPreviewConfig']);

        $files = [];
        if (!empty($value)) {
            $values = explode(",", $value);
            foreach ($values as $fileRelativePath) {
                $files[] = $this->fileInfosHelper->getInfos(
                    $fileRelativePath,
                    $fileManagerConfig['entryPoints'][0]['origin']
                );
            }
        }

        if ($formPreviewConfig['multiple']) {
            $fileManagerConfig['multiple'] = true;
        }

        $view->vars['type'] = 'hidden';
        $view->vars['filemanager_config'] = json_encode($fileManagerConfig);
        $view->vars['formpreview_config'] = json_encode($formPreviewConfig);
        $view->vars['files'] = json_encode($files);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'formPreviewConfig' => [],
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

    public function getParent(): ?string
    {
        return TextType::class;
    }
}
