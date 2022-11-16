<?php

namespace Pentatrion\UploadBundle\Form;

use Pentatrion\UploadBundle\Service\FileManagerHelperInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EntityFilePickerType extends AbstractType
{
    private $fileManagerHelper;
    private $locale;
    private $normalizer;

    public function __construct(
        FileManagerHelperInterface $fileManagerHelper,
        RequestStack $requestStack,
        NormalizerInterface $normalizer
    ) {
        $this->fileManagerHelper = $fileManagerHelper;
        $this->locale = substr($requestStack->getCurrentRequest()->getLocale(), 0, 2);
        $this->normalizer = $normalizer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function ($uploadedFile) {
                return $uploadedFile;
            },
            function ($uploadedFile) {
                if (!$uploadedFile || $uploadedFile->isEmpty()) {
                    return null;
                }

                return $uploadedFile;
            }
        ));
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $value = $form->getData();
        $fileManagerConfig = $this->fileManagerHelper->completeConfig($options['fileManagerConfig'], $this->locale);
        $fileManagerConfig['multiple'] = false;

        $view->vars['attr']['data-name'] = $view->vars['full_name'];
        $view->vars['attr']['data-minifilemanager'] = json_encode($fileManagerConfig);
        $view->vars['attr']['data-uploaded-files'] = json_encode([$this->normalizer->normalize($value)]);
        $view->vars['attr']['data-entity-form-file-picker'] = 'true';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('fileManagerConfig');
        $resolver->setDefault('delete_empty', true);
    }

    public function getParent(): ?string
    {
        return UploadedFileType::class;
    }
}
