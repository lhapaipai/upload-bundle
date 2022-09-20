<?php

namespace Pentatrion\UploadBundle\Service;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Pentatrion\UploadBundle\Exception\InformativeException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class FileHelper implements ServiceSubscriberInterface
{
    private $container;
    private $uploadedFileHelper;
    private $publicUploadsOrigin;
    private $liipCacheDir;

    public function __construct(
        $uploadOrigins,
        $webRootDir,
        UploadedFileHelperInterface $uploadedFileHelper,
        ContainerInterface $container
    ) {
        $this->container = $container;
        $this->uploadedFileHelper = $uploadedFileHelper;
        $this->publicUploadsOrigin = $uploadOrigins['public_uploads'];
        $this->liipCacheDir = $webRootDir.'/media';
    }

    public function purgeUploadsDirectory()
    {
        $finder = new Finder();
        $fs = new Filesystem();

        $finder->in($this->publicUploadsOrigin['path'])->depth('== 0');
        foreach ($finder as $file) {
            $fs->remove($file);
        }

        $finder->in($this->liipCacheDir)->depth('== 0');
        foreach ($finder as $file) {
            $fs->remove($file);
        }
    }

    public function sanitizeFilename($filename, $dir, $options = []): ?string
    {
        if (isset($options['prefix'])) {
            $filename = $options['prefix'].$filename;
        }

        if (isset($options['extension']) && $options['extension']) {
            $extension = $options['extension'];
        } else {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        }

        $filenameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);

        if (isset($options['urlize']) && $options['urlize']) {
            $filenameWithoutExtension = Urlizer::urlize($filenameWithoutExtension);
        }
        if (isset($options['unique']) && $options['unique']) {
            $filenameWithoutExtension = $filenameWithoutExtension.'-'.uniqid();
        } else {
            if (file_exists($dir.DIRECTORY_SEPARATOR.$filenameWithoutExtension.'.'.$extension)) {
                $counter = 1;
                while (file_exists($dir.DIRECTORY_SEPARATOR.$filenameWithoutExtension.'-'.$counter.'.'.$extension)) {
                    ++$counter;
                }
                $filenameWithoutExtension = $filenameWithoutExtension.'-'.$counter;
            }
        }

        return $filenameWithoutExtension.'.'.$extension;
    }

    // validation pour le file manager
    public function validateFile(File $file = null): array
    {
        if (!$file instanceof File) {
            return ['Veuillez envoyer un fichier.'];
        }

        if (!$this->container->has('validator')) {
            return [];
        }

        $violations = $this->container->get('validator')->validate(
            $file,
            [
                new NotBlank(),
                new FileConstraint([
                    'maxSize' => '500M',
                    'maxSizeMessage' => 'Votre fichier est trop grand ({{ size }} {{ suffix }}). limite : {{ limit }} {{ suffix }}.',
                    'mimeTypes' => [
                        'text/*',
                        'image/*', // image/vnd.adobe.photoshop  image/x-xcf
                        'video/*',
                        'audio/*',
                        'application/rtf',
                        'application/pdf',
                        'application/xml',
                        'application/zip',
                        'font/ttf',
                        'font/woff',
                        'font/woff2',
                        'application/vnd.oasis.opendocument.spreadsheet', // tableur libreoffice ods
                        'application/vnd.oasis.opendocument.text', // traitement de texte odt
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
                        'application/msword', // doc
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
                        'application/vnd.ms-excel', // xls
                        'application/json',
                        'application/illustrator', // .ai
                    ],
                    'mimeTypesMessage' => 'Ce type de fichier n\'est pas accepté : {{ type }}. Vous pouvez importer des textes, images, vidéos, audio, .pdf, .zip, .ods, .odt, .doc, .docx, .xls, .xlsx, .psd, .ai',
                ]),
            ]
        );
        $violationMsgs = [];
        foreach ($violations as $violation) {
            $violationMsgs[] = $violation->getMessage();
        }

        return $violationMsgs;
    }

    // les droits seront fixés
    // pour un admin  http:http 644
    // pour un client http:http 664
    public function uploadFile(File $file, $destRelDir, $originName = null, $options = [])
    {
        $destAbsDir = $this->uploadedFileHelper->getAbsolutePath($destRelDir, $originName);

        if (isset($options['forceFilename'])) {
            $newFilename = $options['forceFilename'].'.'.$file->guessExtension();
        } else {
            if (isset($options['guessExtension']) && $options['guessExtension']) {
                $extension = $file->guessExtension();
                $options['extension'] = $extension;
            }
            if ($file instanceof UploadedFile) {
                $filename = $file->getClientOriginalName();
            } else {
                $filename = $file->getFilename();
            }
            $newFilename = $this->sanitizeFilename($filename, $destAbsDir, array_merge($options, ['urlize' => true]));
        }

        $fs = new Filesystem();
        if (!$fs->exists($destAbsDir)) {
            $fs->mkdir($destAbsDir);
        }
        $file->move($destAbsDir, $newFilename);

        return $this->uploadedFileHelper->getUploadedFile(($destRelDir ? $destRelDir.DIRECTORY_SEPARATOR : '').$newFilename, $originName);
    }

    public function createFileFromChunks($tempDir, $filename, $totalSize, $totalChunks, $destRelDir, $originName = null, $options = [])
    {
        $fs = new Filesystem();
        $totalFilesOnServerSize = 0;
        $files = array_diff(scandir($tempDir), ['..', '.', 'output', 'done']);

        $destAbsDir = $this->uploadedFileHelper->getAbsolutePath($destRelDir, $originName);
        $newFilename = $this->sanitizeFilename($filename, $destAbsDir, array_merge($options, ['urlize' => true]));

        // si on reprend un upload au milieu des test on parviendra à générer le fichier, il faut donc que
        // les tests suivants renvoient tout de suite les bonnes infos.
        if ($fs->exists($destAbsDir.DIRECTORY_SEPARATOR.$newFilename)) {
            return $this->uploadedFileHelper->getUploadedFile(($destRelDir ? $destRelDir.DIRECTORY_SEPARATOR : '').$newFilename, $originName);
        }

        foreach ($files as $file) {
            $tempFileSize = \filesize($tempDir.DIRECTORY_SEPARATOR.$file);
            $totalFilesOnServerSize += $tempFileSize;
        }

        if ($totalFilesOnServerSize >= $totalSize) {
            if (($fp = \fopen($tempDir.DIRECTORY_SEPARATOR.'output', 'w')) !== false) {
                for ($i = 1; $i <= $totalChunks; ++$i) {
                    \fwrite($fp, \file_get_contents($tempDir.DIRECTORY_SEPARATOR.'chunk.part'.$i));
                }
                fclose($fp);
            }

            if (!$fs->exists($destAbsDir)) {
                $fs->mkdir($destAbsDir);
            }

            $file = new File($tempDir.DIRECTORY_SEPARATOR.'output');

            $violations = $this->validateFile($file);
            if (count($violations) > 0) {
                throw new InformativeException(implode('\n', $violations), 415);
            }

            $file->move($destAbsDir, $newFilename);

            // permet de supprimer récursivement sans problème avec les chunks concurrents
            $fs->rename($tempDir, $tempDir.'_done');
            $fs->remove($tempDir.'_done');

            return $this->uploadedFileHelper->getUploadedFile(($destRelDir ? $destRelDir.DIRECTORY_SEPARATOR : '').$newFilename, $originName);
        } else {
            return false;
        }
    }

    public function uploadChunkFile(File $file, $destDir, $filename): File
    {
        $fs = new Filesystem();
        if (!$fs->exists($destDir)) {
            $fs->mkdir($destDir);
        }

        return $file->move($destDir, $filename);
    }

    public function delete(string $uploadRelativePath, $originName = null): void
    {
        $absolutePath = $this->uploadedFileHelper->getAbsolutePath($uploadRelativePath, $originName);

        $fs = new Filesystem();
        $fs->remove($absolutePath);

        if ($this->container->has('cachemanager')) {
            $liipPath = $this->uploadedFileHelper->getLiipPath($uploadRelativePath, $originName);
            $this->container->get('cachemanager')->remove($liipPath);
        }
    }

    public function deleteDir(string $uploadRelativeDir, $originName = null): void
    {
        $absoluteDir = $this->uploadedFileHelper->getAbsolutePath($uploadRelativeDir, $originName);

        $finder = new Finder();
        $fs = new Filesystem();
        if (!file_exists($absoluteDir)) {
            return;
        }
        $finder->in($absoluteDir)->depth('== 0');
        foreach ($finder as $file) {
            if ($this->container->has('cachemanager')) {
                $liipPath = $this->uploadedFileHelper->getLiipPathFromFile($file, $originName);
                $this->container->get('cachemanager')->remove($liipPath);
            }

            $fs->remove($file);
        }
        $fs->remove($absoluteDir);
    }

    public function cropImage(string $uploadRelativePath, $origin, $x, $y, $width, $height, $finalWidth, $finalHeight, $angle = 0): bool
    {
        if (!class_exists("Imagine\Gd\Imagine")) {
            throw new InformativeException('Unable to crop image. Did you install Imagine ? composer require imagine/imagine', 401);
        }
        $absolutePath = $this->uploadedFileHelper->getAbsolutePath($uploadRelativePath, $origin);
        $imagine = new Imagine();
        $image = $imagine->open($absolutePath);

        if (0 !== $angle) {
            $image->rotate($angle);
        }
        if ($width >= 1 && $height >= 1) {
            $image->crop(new Point($x, $y), new Box($width, $height));
        }
        if ($finalWidth >= 1 && $finalHeight >= 1) {
            $image->resize(new Box($finalWidth, $finalHeight));
        }

        $image->save($absolutePath);

        if ($this->container->has('cachemanager')) {
            $liipPath = $this->uploadedFileHelper->getLiipPath($uploadRelativePath, $origin);
            $this->container->get('cachemanager')->remove($liipPath);
        }

        return true;
    }

    public static function getSubscribedServices(): array
    {
        return [
            'cachemanager' => '?'.CacheManager::class,
            'validator' => '?'.ValidatorInterface::class,
        ];
    }
}
