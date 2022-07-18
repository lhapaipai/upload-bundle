<?php

namespace Pentatrion\UploadBundle\Form;

use Pentatrion\UploadBundle\Service\FileInfosHelperInterface;
use Pentatrion\UploadBundle\Service\FileManagerHelperInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextFilePickerType extends AbstractType
{
    private $fileManagerHelper;
    private $fileInfosHelper;
    private $locale;

    public function __construct(
        FileManagerHelperInterface $fileManagerHelper,
        FileInfosHelperInterface $fileInfosHelper,
        RequestStack $requestStack
    ) {
        $this->fileManagerHelper = $fileManagerHelper;
        $this->fileInfosHelper = $fileInfosHelper;
        $this->locale = $requestStack->getCurrentRequest()->getLocale();
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $value = $form->getData();

        $fileManagerConfig = ($this->fileManagerHelper->completeConfig($options['fileManagerConfig'], $this->locale)
        );

        $uploadedFiles = [];
        if (!empty($value)) {
            $values = explode(",", $value);
            foreach ($values as $fileRelativePath) {
                $uploadedFiles[] = $this->fileInfosHelper->getUploadedFileFromPath(
                    $fileRelativePath,
                    $fileManagerConfig['entryPoints'][0]['origin']
                );
            }
        }

        $view->vars['type'] = 'text';
        $view->vars['attr']['data-minifilemanager'] = json_encode($fileManagerConfig);
        $view->vars['attr']['data-uploaded-files'] = json_encode($uploadedFiles);
        $view->vars['attr']['data-text-form-file-picker'] = 'true';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('fileManagerConfig');
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }
}
