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

  public function __construct(FileManagerHelper $fileManagerHelper, FileInfosHelperInterface $fileInfosHelper, RequestStack $requestStack) {
    $this->fileManagerHelper = $fileManagerHelper;
    $this->fileInfosHelper = $fileInfosHelper;
    $this->locale = $requestStack->getCurrentRequest()->getLocale();
  }

  public function buildView(FormView $view, FormInterface $form, array $options)
  {
    $value = $form->getData();

    $fileManagerConfig = (
      $this->fileManagerHelper->completeConfig($options['fileManagerConfig'], $this->locale)
    );

    $formFilePickerConfig = array_merge([
      'multiple'      => false,
      'previewFilter' => 'small',
      'previewType'   => 'image'
    ], $options['formFilePickerConfig']);

    $selection = [];
    if (!empty($value)) {
      $values = explode(",", $value);
      foreach($values as $fileRelativePath) {
        $selection[] = $this->fileInfosHelper->getInfos(
          $fileRelativePath,
          $fileManagerConfig['entryPoints'][0]['origin']
        );
      }
    }

    if ($formFilePickerConfig['multiple']) {
      $fileManagerConfig['multiSelection'] = true;
    }

    $view->vars['type'] = 'hidden';
    $view->vars['filemanager_config'] = json_encode($fileManagerConfig);
    $view->vars['formfilepicker_config'] = json_encode($formFilePickerConfig);
    $view->vars['selection'] = json_encode($selection);
  }

  public function configureOptions(OptionsResolver $resolver)
  {
    $resolver->setDefined('fileManagerConfig');
    $resolver->setDefined('formFilePickerConfig');
    $resolver->setDefaults([
      'formFilePickerConfig' => []
    ]);
    $resolver->setDefaults([
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
    return TextType::class;
  }
}