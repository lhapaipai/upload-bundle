<?php

namespace Pentatrion\UploadBundle\Form;

use Pentatrion\UploadBundle\Service\FileInfosHelper;
use Pentatrion\UploadBundle\Service\FileInfosHelperInterface;
use Pentatrion\UploadBundle\Service\FileManagerHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilePickerType extends AbstractType
{
  private $fileManagerHelper;
  private $fileInfosHelper;

  public function __construct(FileManagerHelper $fileManagerHelper, FileInfosHelperInterface $fileInfosHelper) {
    $this->fileManagerHelper = $fileManagerHelper;
    $this->fileInfosHelper = $fileInfosHelper;
  }

  public function buildView(FormView $view, FormInterface $form, array $options)
  {
    $value = $form->getData();

    $fileManagerConfig = json_encode(
      $this->fileManagerHelper->getConfig([[
        'directory' => $options['uploadDirectory'],
        'label' => $options['uploadDirectoryLabel'],
        'origin'=> $options['uploadOrigin']
      ]])
    );

    $view->vars['row_attr']['class'] = 'file-picker'.($value?' with-value':'');
    $view->vars['type'] = 'hidden';
    $view->vars['picker_config'] = $fileManagerConfig;

    if ($options['previewType'] === 'image') {
      $view->vars['preview'] = [
        'class' => $options['previewClass'],
        'type' => $options['previewType'],
        'path' => $value ?? '',
        'filter' => $options['previewFilter'],
        'origin'=> $options['uploadOrigin'],
        'filename' => basename($value)
      ];
    } else {
      $absPath = $this->fileInfosHelper->getAbsolutePath($value, $options['uploadOrigin']);
      $mimeType = MimeTypes::getDefault()->guessMimeType($absPath);
      $icon = '/file-manager/icons/'.FileInfosHelper::getIconByMimeType($mimeType);

      $view->vars['preview'] = [
        'class' => $options['previewClass'],
        'type' => $options['previewType'],
        'path' => $icon,
        'filename' => basename($value)
      ];
    }
  }

  public function configureOptions(OptionsResolver $resolver)
  {
    $resolver->setDefined('uploadDirectory');
    $resolver->setDefined('uploadOrigin');
    $resolver->setDefaults([
      'uploadDirectoryLabel' => 'Répertoire principal',
      'previewFilter' => 'small',
      'previewType' => 'file',
      'previewClass' => 'rounded-corner'
    ]);
  }

  public function getParent()
  {
    return TextType::class;
  }
}