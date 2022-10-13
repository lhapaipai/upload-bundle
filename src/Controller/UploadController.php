<?php

namespace Pentatrion\UploadBundle\Controller;

use Exception;
use Pentatrion\UploadBundle\Classes\ExtendedZip;
use Pentatrion\UploadBundle\Exception\InformativeException;
use Pentatrion\UploadBundle\Service\FileHelper;
use Pentatrion\UploadBundle\Service\UploadedFileHelperInterface;
use Pentatrion\UploadBundle\Service\Urlizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class UploadController extends AbstractController implements ServiceSubscriberInterface
{
    private $uploadedFileHelper;
    private $security;

    public function __construct(UploadedFileHelperInterface $uploadedFileHelper, Security $security)
    {
        $this->uploadedFileHelper = $uploadedFileHelper;
        $this->security = $security;
    }

    public function getFiles(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        $directory = $request->request->get('directory');
        $origin = $request->request->get('origin');
        $mimeGroup = $request->request->get('mimeGroup');

        return $this->json($normalizer->normalize($this->uploadedFileHelper->getUploadedFilesFromDirectory(
            $directory,
            $origin,
            $mimeGroup,
            true // with directory infos
        )));
    }

    public function showFile($mode, $origin, $uploadRelativePath, Request $request, UploadedFileHelperInterface $uploadedFileHelper): BinaryFileResponse
    {
        $uploadedFile = $uploadedFileHelper->getUploadedFile($uploadRelativePath, $origin);
        $absolutePath = $uploadedFileHelper->getAbsolutePath($uploadRelativePath, $origin);

        if (!$this->uploadedFileHelper::hasGrantedAccess($uploadedFile, $this->security->getUser())) {
            throw new InformativeException('Vous n\'avez pas les droits suffisants pour voir le contenu de ce fichier !!', 403);
        }

        $disposition = 'show' === $mode ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        $response = $this->file($absolutePath, null, $disposition);

        // bug sinon cela télécharge l'image au lieu de l'afficher !
        if ('image/svg' === $response->getFile()->getMimeType()) {
            $response->headers->set('Content-Type', 'image/svg+xml');
        }

        return $response;
    }

    public function downloadFile(Request $request, UploadedFileHelperInterface $uploadedFileHelper): BinaryFileResponse
    {
        $liipIds = $request->request->all()['files'];
        $files = [];
        $user = $this->security->getUser();

        if (count($liipIds) < 0) {
            throw new InformativeException('Erreur dans la requête, aucun fichier sélectionné', 404);
        }

        foreach ($liipIds as $liipId) {
            $uploadedFile = $this->uploadedFileHelper->getUploadedFileByLiipId($liipId);
            $this->uploadedFileHelper->addAbsolutePath($uploadedFile);

            if (!$this->uploadedFileHelper::hasGrantedAccess($uploadedFile, $user)) {
                throw new InformativeException('Le fichier appartient à un projet qui ne vous concerne pas !!', 403);
            }
            $files[] = $uploadedFile;
        }

        $archiveTempPath = ExtendedZip::createArchiveFromFiles($files);

        return $this->file($archiveTempPath, 'archive.zip');
    }

    public function editFileRequest(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        $infos = $request->request->all();
        $readOnly = $request->request->getBoolean('readOnly');

        if (is_null($infos['newFilename']) || empty($infos['newFilename']) || '.' === $infos['newFilename'][0]) {
            throw new InformativeException('Le nom de fichier n\'est pas valide', 401);
        }

        $extension = strtolower(pathinfo($infos['newFilename'], PATHINFO_EXTENSION));
        $filenameWithoutExtension = pathinfo($infos['newFilename'], PATHINFO_FILENAME);

        $newFilename = Urlizer::urlize($filenameWithoutExtension);

        if ('' !== $extension) {
            $newFilename .= ".$extension";
        }

        $oldCompletePath = $this->uploadedFileHelper->getAbsolutePath($infos['uploadRelativePath'], $infos['origin']);

        $newRelativePath = $infos['directory'].'/'.$newFilename;
        $newCompletePath = $this->uploadedFileHelper->getAbsolutePath($newRelativePath, $infos['origin']);

        if (!$readOnly) {
            try {
                $fs = new Filesystem();
                $fs->rename($oldCompletePath, $newCompletePath);
            } catch (Exception $err) {
                throw new InformativeException('Impossible de renommer le fichier : '.$infos['filename'].'. Vérifiez que le nom est bien unique.', 401);
            }
        } else {
            throw new InformativeException('Impossible de renommer le fichier : '.$infos['filename'].' car vous n\'avez pas les droits nécessaires.', 401);
        }

        return $this->json([
            'file' => $normalizer->normalize($this->uploadedFileHelper->getUploadedFile($newRelativePath, $infos['origin'])),
        ]);
    }

    public function cropFile(Request $request, FileHelper $fileHelper, NormalizerInterface $normalizer): JsonResponse
    {
        $uploadRelativePath = $request->request->get('uploadRelativePath');
        $origin = $request->request->get('origin');

        $angle = (float) $request->request->get('rotate');
        $x = (float) $request->request->get('x');
        $y = (float) $request->request->get('y');
        if ($x < 0) {
            $x = 0;
        }
        if ($y < 0) {
            $y = 0;
        }
        $width = (float) $request->request->get('width');
        $height = (float) $request->request->get('height');
        $finalWidth = (float) $request->request->get('finalWidth');
        $finalHeight = (float) $request->request->get('finalHeight');

        $fileHelper->cropImage($uploadRelativePath, $origin, $x, $y, $width, $height, $finalWidth, $finalHeight, $angle);

        return $this->json([
            'file' => $normalizer->normalize($this->uploadedFileHelper->getUploadedFile($uploadRelativePath, $origin)),
        ]);
    }

    public function deleteFile(Request $request, FileHelper $fileHelper): JsonResponse
    {
        $liipIds = $request->request->all()['files'];
        $errors = [];

        foreach ($liipIds as $liipId) {
            $uploadedFile = $this->uploadedFileHelper->getUploadedFileByLiipId($liipId);
            if (!$this->uploadedFileHelper::hasGrantedAccess($uploadedFile, $this->security->getUser())) {
                $errors[] = $uploadedFile->getFilename();
            } else {
                $fileHelper->delete($uploadedFile->getUploadRelativePath(), $uploadedFile->getOrigin());
            }
        }

        if (0 != count($errors)) {
            throw new InformativeException('Impossible de supprimer le(s) fichier(s) : '.implode(', ', $errors).' car vous n\'avez pas les droits suffisants.', 401);
        }

        return $this->json([
            'success' => true,
        ]);
    }

    /**
     * @Route("/add-directory", name="media_add_directory")
     */
    public function addDirectory(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        $infos = $request->request->all();

        $filename = Urlizer::urlize($infos['filename']);
        if (strlen($filename) > 128) {
            throw new InformativeException('Le nom du dossier est trop long.', 500);
        }
        $completePath = $this->uploadedFileHelper->getAbsolutePath(
            $infos['directory'].'/'.$filename,
            $infos['origin']
        );

        try {
            $fs = new FileSystem();
            $fs->mkdir($completePath);
        } catch (Exception $err) {
            throw new InformativeException('Impossible de créer le dossier', 401);
        }

        return $this->json([
            'directory' => $normalizer->normalize($this->uploadedFileHelper->getUploadedFile($infos['directory'].'/'.$filename, $infos['origin'])),
        ]);
    }

    public function uploadFile(FileHelper $fileHelper, Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        $fileFromRequest = $request->files->get('file');
        $destRelDir = $request->request->get('directory');
        $origin = $request->request->get('origin');

        $violations = $fileHelper->validateFile($fileFromRequest);
        if (count($violations) > 0) {
            throw new InformativeException(implode('\n', $violations), 415);
        }

        $uploadedFile = $fileHelper->uploadFile(
            $fileFromRequest,
            $destRelDir,
            $origin,
        );

        return $this->json([
            'data' => $normalizer->normalize($uploadedFile),
        ]);
    }

    public function chunkFile(FileHelper $fileHelper, Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        $fs = new Filesystem();

        $destRelDir = $request->query->get('directory');
        $origin = $request->query->get('origin');
        $tempLiipId = $request->query->get('liipId');

        $uid = $request->query->get('resumableIdentifier');
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.$uid;

        $filename = $request->query->get('resumableFilename');
        $totalSize = $request->query->getInt('resumableTotalSize');
        $totalChunks = $request->query->getInt('resumableTotalChunks');

        $chunkFilename = 'chunk.part'.$request->query->getInt('resumableChunkNumber');

        // on teste simplement si la portion de fichier a déjà été uploadée.
        if ('GET' === $request->getMethod()) {
            $chunkPath = $tempDir.DIRECTORY_SEPARATOR.$chunkFilename;

            // le fichier n'existe pas on signale qu'il faudra donc l'uploader.
            if (!$fs->exists($chunkPath)) {
                return new JsonResponse('', 204);
            }
        // le fichier existe, on vérifiera comme lors d'un upload si on ne peut pas
        // déjà assembler le fichier
        } else {
            // on upload la portion de fichier.
            $fileFromRequest = $request->files->get('file');
            try {
                // if (rand(0,3) === 3) {
                //     throw new \Exception("random error");
                // }
                $fileHelper->uploadChunkFile($fileFromRequest, $tempDir, $chunkFilename);
            } catch (\Exception $err) {
                throw new InformativeException('Impossible de copier le fragment', 500);
            }
        }

        try {
            $uploadedFile = $fileHelper->createFileFromChunks($tempDir, $filename, $totalSize, $totalChunks, $destRelDir, $origin);
        } catch (\Exception $err) {
            if ($err instanceof InformativeException) {
                throw $err;
            } else {
                throw new InformativeException("Impossible d'assembler les fragments en fichier", 500);
            }
        }

        if ($uploadedFile) {
            return $this->json([
                'file' => $normalizer->normalize($uploadedFile),
                'oldLiipId' => $tempLiipId,
            ]);
        } else {
            return $this->json([
                'message' => 'GET' === $request->getMethod() ? 'chunk already exist' : 'chunk uploaded',
            ]);
        }
    }
}
