<?php

namespace Pentatrion\UploadBundle\Controller;

use Pentatrion\UploadBundle\Classes\ExtendedZip;
use Pentatrion\UploadBundle\Exception\InformativeException;
use Pentatrion\UploadBundle\Service\FileHelper;
use Pentatrion\UploadBundle\Service\FileInfosHelperInterface;
use Pentatrion\UploadBundle\Service\Urlizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    private $fileInfosHelper;

    public function __construct(FileInfosHelperInterface $fileInfosHelper)
    {
        $this->fileInfosHelper = $fileInfosHelper;
    }

    protected function isAdmin()
    {
      $user = $this->getUser();
      if ($user) {
        return in_array('ROLE_ADMIN', $user->getRoles());
      } else {
          return false;
      }
    }

    public function getFiles(Request $request)
    {
        $directory = $request->request->get('directory');
        $origin = $request->request->get('origin');

        return $this->json($this->fileInfosHelper->getInfosFromDirectory(
          $directory,
          $origin,
          false,
          true // with directory infos
        ));
    }

    public function showFile($mode, $origin, $uploadRelativePath, Request $request, FileInfosHelperInterface $fileInfosHelper)
    {
        $fileInfos = $fileInfosHelper->getInfos($uploadRelativePath, $origin, true);
        
        if(!$this->fileInfosHelper::hasGrantedAccess($fileInfos, $this->getUser())) {
            throw new InformativeException('Vous n\'avez pas les droits suffisants pour voir le contenu de ce fichier !!', 403);
        }

        $disposition = $mode === 'show' ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        $response = $this->file($fileInfos['absolutePath'], null, $disposition);

        // bug sinon cela télécharge l'image au lieu de l'afficher !
        if ($response->getFile()->getMimeType() === 'image/svg') {
          $response->headers->set('Content-Type', 'image/svg+xml');
        }
        return $response;
    }

    public function downloadFile(Request $request)
    {
        $fileIds = $request->request->get('files');
        $files = [];
        $user = $this->getUser();

        if (count($fileIds) < 0) {
            throw new InformativeException('Erreur dans la requête, aucun fichier sélectionné', 404);
        }

        foreach($fileIds as $fileId) {
            $fileInfos = $this->fileInfosHelper->getInfosById($fileId, true);
            if (!$this->fileInfosHelper::hasGrantedAccess($fileInfos, $user)) {
                throw new InformativeException('Le fichier appartient à un projet qui ne vous concerne pas !!', 403);
            }
            $files[] = $fileInfos;
        }

        $archiveTempPath = ExtendedZip::createArchiveFromFiles($files);
        return $this->file($archiveTempPath, 'archive.zip');
    }

    public function editFileRequest(Request $request)
    {
        $infos = $request->request->all();
        $readOnly = $request->request->getBoolean('readOnly');

        if (is_null($infos['newFilename']) || empty($infos['newFilename']) || $infos['newFilename'][0] === '.') {
            throw new InformativeException('Le nom de fichier n\'est pas valide', 401);
        }

        $extension = strtolower(pathinfo($infos['newFilename'], PATHINFO_EXTENSION));
        $filenameWithoutExtension = pathinfo($infos['newFilename'], PATHINFO_FILENAME);


        $newFilename = Urlizer::urlize($filenameWithoutExtension);

        if ($extension !== "") {
            $newFilename .= ".$extension";
        }

        $oldCompletePath = $this->fileInfosHelper->getAbsolutePath($infos['uploadRelativePath'], $infos['origin']);

        $newRelativePath = $infos['directory'].'/'.$newFilename;
        $newCompletePath = $this->fileInfosHelper->getAbsolutePath($newRelativePath, $infos['origin']);

        if ($this->isAdmin() || !$readOnly) {
            $fs = new Filesystem();
            $fs->rename($oldCompletePath, $newCompletePath);
        } else {
            throw new InformativeException('Impossible de renommer le fichier : '.$infos['filename'].' car vous n\'avez pas les droits nécessaires.', 401);
        }
        
        return $this->json([
            'file' => $this->fileInfosHelper->getInfos($newRelativePath, $infos['origin'])
        ]);
    }

    public function cropFile(Request $request, FileHelper $fileHelper)
    {
        $uploadRelativePath = $request->request->get('uploadRelativePath');
        $origin = $request->request->get('origin');

        $angle = (float) $request->request->get('rotate');
        $x = (float) $request->request->get('x');
        $y = (float) $request->request->get('y');
        $width = (float) $request->request->get('width');
        $height = (float) $request->request->get('height');
        $finalWidth = (float) $request->request->get('finalWidth');
        $finalHeight = (float) $request->request->get('finalHeight');

        $fileHelper->cropImage($uploadRelativePath, $origin, $x, $y, $width, $height, $finalWidth, $finalHeight, $angle);

        return $this->json([
            'file' => $this->fileInfosHelper->getInfos($uploadRelativePath, $origin)
        ]);
    }

    public function deleteFile(Request $request, FileHelper $fileHelper)
    {
        $fileIds = $request->request->get('files');
        $errors = [];

        foreach ($fileIds as $fileId) {

            $fileInfos = $this->fileInfosHelper->getInfosById($fileId, true);
            if(!$this->fileInfosHelper::hasGrantedAccess($fileInfos, $this->getUser())) {
                $errors[] = $fileInfos['filename'];
            } else {
                $fileHelper->delete($fileInfos['uploadRelativePath'], $fileInfos['origin']);
            }
        }

        if (count($errors) != 0) {
            throw new InformativeException('Impossible de supprimer le(s) fichier(s) : '.implode(', ', $errors).' car vous n\'avez pas les droits suffisants.', 401);
        }

        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Route("/add-directory", name="media_add_directory")
     */
    public function addDirectory(Request $request, FileHelper $fileHelper)
    {
        $infos = $request->request->all();

        $filename = Urlizer::urlize($infos['filename']);
        if (strlen($filename) > 128) {
            throw new InformativeException('Le nom du dossier est trop long.', 500);
        }
        $completePath = $this->fileInfosHelper->getAbsolutePath(
            $infos['directory'].'/'.$filename,
            $infos['origin']
        );

        $fs = new FileSystem();
        $fs->mkdir($completePath);

        return $this->json([
            'directory' => $this->fileInfosHelper->getInfos($infos['directory'].'/'.$filename, $infos['origin'])
        ]);
    }

    public function uploadFile(FileHelper $fileHelper, Request $request): Response
    {
        $fileFromRequest = $request->files->get('file');
        $destRelDir = $request->request->get('directory');
        $origin = $request->request->get('origin');

        $violations = $fileHelper->validateFile($fileFromRequest);
        if (count($violations) > 0) {
            throw new InformativeException(implode('\n', $violations), 415);
        }

        $fileInfos = $fileHelper->uploadFile(
            $fileFromRequest,
            $destRelDir,
            $origin,
        );

        return $this->json([
            'data' => $fileInfos
        ]);
    }

    public function chunkFile(FileHelper $fileHelper, Request $request): Response
    {
        $fs = new Filesystem();

        $destRelDir = $request->query->get('directory');
        $origin = $request->query->get('origin');
        $tempId = $request->query->get('id');

        $uid = $request->query->get('resumableIdentifier');
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.$uid;

        $filename = $request->query->get('resumableFilename');
        $totalSize = $request->query->getInt('resumableTotalSize');
        $totalChunks = $request->query->getInt('resumableTotalChunks');

        $chunkFilename = 'chunk.part'.$request->query->getInt('resumableChunkNumber');

        // on teste simplement si la portion de fichier a déjà été uploadée.
        if($request->getMethod() === 'GET') {
            $chunkPath = $tempDir.DIRECTORY_SEPARATOR.$chunkFilename;

            // le fichier n'existe pas on signale qu'il faudra donc l'uploader.
            if (!$fs->exists($chunkPath)) {
                return new Response('', 204);
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
                throw new InformativeException("Impossible de copier le fragment", 500);
            }
        }

        try {
            $fileInfos = $fileHelper->createFileFromChunks($tempDir, $filename, $totalSize, $totalChunks, $destRelDir, $origin);
        } catch (\Exception $err) {
            if ($err instanceof InformativeException) {
                throw $err;
            } else {
                throw new InformativeException("Impossible d'assembler les fragments en fichier", 500);
            }
        }

        if ($fileInfos) {
            return $this->json([
                'file' => $fileInfos,
                'oldId' => $tempId
            ]);
        } else {
            return $this->json([
                'message' => $request->getMethod() === 'GET' ? 'chunk already exist' : 'chunk uploaded'
            ]);
        }




        return $this->json([
            'data' => $fileInfos
        ]);
    }

}
