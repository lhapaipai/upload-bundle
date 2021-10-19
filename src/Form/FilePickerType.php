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
      $this->fileManagerHelper->completeConfig([
        'entryPoints' => [[
          'directory' => $options['uploadDirectory'],
          'label' => $options['uploadDirectoryLabel'],
          'origin'=> $options['uploadOrigin'],
        ]],
        'fileValidation' => $options['fileValidation'],
        'locale' => $options['locale'],
      ])
    );

    $formFilePickerConfig = [
      'multiple'      => $options['multiple'],
      'previewFilter' => $options['previewFilter'],
      'previewType'   => $options['previewType']
    ];

    $selection = [];
    if (!empty($value)) {
      $values = explode(",", $value);
      foreach($values as $fileRelativePath) {
        $selection[] = $this->fileInfosHelper->getInfos($fileRelativePath, $options['uploadOrigin']);
      }
    }

    $view->vars['type'] = 'hidden';
    $view->vars['filemanager_config'] = json_encode($fileManagerConfig);
    $view->vars['formfilepicker_config'] = json_encode($formFilePickerConfig);
    $view->vars['selection'] = json_encode($selection);
  }

  public function configureOptions(OptionsResolver $resolver)
  {
    $resolver->setDefined('uploadDirectory');
    $resolver->setDefined('uploadOrigin');
    $resolver->setDefaults([
      'uploadDirectoryLabel' => 'Répertoire principal',
      'previewFilter' => 'small',
      'previewType' => 'image',
      'previewClass' => '',
      'fileValidation' => null,
      'locale' => $this->locale,
      'multiple' => false
    ]);
  }

  public function getParent()
  {
    return TextType::class;
  }
}