<?php

namespace Pentatrion\UploadBundle\Form;

use Pentatrion\UploadBundle\Service\FileManagerHelperInterface;
use Pentatrion\UploadBundle\Service\UploadedFileHelperInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TextFilePickerType extends AbstractType
{
    private $fileManagerHelper;
    private $uploadedFileHelper;
    private $locale;
    private $normalizer;

    public function __construct(
        FileManagerHelperInterface $fileManagerHelper,
        UploadedFileHelperInterface $uploadedFileHelper,
        RequestStack $requestStack,
        NormalizerInterface $normalizer
    ) {
        $this->fileManagerHelper = $fileManagerHelper;
        $this->uploadedFileHelper = $uploadedFileHelper;
        $this->locale = $requestStack->getCurrentRequest()->getLocale();
        $this->normalizer = $normalizer;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $value = $form->getData();

        $fileManagerConfig = $this->fileManagerHelper->completeConfig($options['fileManagerConfig'], $this->locale)
        ;

        $uploadedFiles = [];
        if (!empty($value)) {
            $values = explode(',', $value);
            foreach ($values as $fileRelativePath) {
                $uploadedFiles[] = $this->uploadedFileHelper->getUploadedFile(
                    $fileRelativePath,
                    $fileManagerConfig['entryPoints'][0]['origin']
                );
            }
        }

        $view->vars['type'] = 'hidden';
        $view->vars['attr']['data-minifilemanager'] = json_encode($fileManagerConfig);
        $view->vars['attr']['data-uploaded-files'] = json_encode($this->normalizer->normalize($uploadedFiles));
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
