<?php

namespace Pentatrion\UploadBundle\Form;

use Pentatrion\UploadBundle\Entity\UploadedFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadedFileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('liipId', HiddenType::class, [
                'required' => false,
            ])
            ->add('mimeGroup', HiddenType::class, [
                'required' => false,
            ])
            ->add('mimeType', HiddenType::class, [
                'required' => false,
            ])
            ->add('filename', HiddenType::class, [
                'required' => false,
            ])
            ->add('directory', HiddenType::class, [
                'required' => false,
            ])
            ->add('origin', HiddenType::class, [
                'required' => false,
            ])
            ->add('imageWidth', HiddenType::class, [
                'required' => false,
            ])
            ->add('imageHeight', HiddenType::class, [
                'required' => false,
            ])
            ->add('type', HiddenType::class, [
                'required' => false,
            ])
            ->add('size', HiddenType::class, [
                'required' => false,
            ])
            ->add('updatedAt', HiddenType::class, [
                'required' => false,
            ])
            ->add('icon', HiddenType::class, [
                'required' => false,
            ])
            ->add('public', HiddenType::class, [
                'required' => false,
            ]);

        $builder->get('updatedAt')->addModelTransformer(new DateTimeToStringTransformer(null, null, 'Y-m-d\TH:i:sT'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UploadedFile::class,
        ]);
    }
}
