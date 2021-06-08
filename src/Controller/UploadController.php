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

/**
 * @Route("/media-manager", defaults={"_format"="json"}, name="file_manager_endpoint_")
 */
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

    /**
     * @Route("/get-files", name="media_get_files")
     */
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

    /**
     * @Route("/get-file-content/{mode}/{origin}/{uploadRelativePath}", name="media_show_file", defaults={"mode"="", "origin"="", "uploadRelativePath"=""}, requirements={"mode"="(show|download)", "uploadRelativePath"=".+"})
     */
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

    /**
     * @Route("/download-archive", name="media_download_archive")
     */
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

    /**
     * @Route("/edit", name="media_edit_file")
     */
    public function editFileRequest(Request $request)
    {
        $infos = $request->request->all();
        $readOnly = $request->request->getBoolean('readOnly');

        if (is_null($infos['newFilename']) || empty($infos['newFilename']) || $infos['newFilename'][0] === '.') {
            throw new InformativeException('Le nom de fichier n\'est pas valide', 401);
        }
        $newFilename = Urlizer::urlize($infos['newFilename']);

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

    /**
     * @Route("/crop", name="media_crop_file")
     */
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

    /**
     * @Route("/delete", name="media_delete_file")
     */
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

    /**
     * @Route("/upload", name="media_upload_file", methods={"POST"})
     */
    public function uploadFile(FileHelper $fileHelper, Request $request): Response
    {
        $fileFromRequest = $request->files->get('file');
        $destRelDir = $request->request->get('directory');
        $origin = $request->request->get('origin');

        $violations = $fileHelper->validateFile($fileFromRequest, $destRelDir);
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

}
