<?php

namespace Pentatrion\UploadBundle\Form;

use Pentatrion\UploadBundle\Entity\UploadedFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadedFileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type')
            ->add('mimeType')
            ->add('width')
            ->add('height')
            ->add('filename')
            ->add('directory')
            ->add('origin');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UploadedFile::class
        ]);
    }
}
