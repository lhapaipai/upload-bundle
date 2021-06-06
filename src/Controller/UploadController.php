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
      return in_array('ROLE_ADMIN', $user->getRoles());
    }

    /**
     * @Route("/get-files", name="media_get_files")
     */
    public function getFiles(Request $request)
    {
        $data = json_decode($request->getContent());
        return $this->json($this->fileInfosHelper->getInfosFromDirectory(
          $data->directory,
          $data->origin,
          false,
          true // with directory infos
        ), 200);

    }

    /**
     * @Route("/get/{mode}/{origin}/{uploadRelativePath}", name="media_show_file", defaults={"mode"="", "origin"="", "uploadRelativePath"=""}, requirements={"mode"="(show|download)", "uploadRelativePath"=".+"})
     */
    public function showFile($mode, $origin, $uploadRelativePath, Request $request, FileInfosHelperInterface $fileInfosHelper)
    {
        $fileInfos = $fileInfosHelper->getInfos($uploadRelativePath, $origin, true);
        
        if(!$this->fileInfosHelper::hasGrantedAccess($fileInfos, $this->getUser())) {
            throw new InformativeException('Le fichier appartient à un projet qui ne vous concerne pas !!', 403);
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
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['files']) || count($data['files']) < 0) {
            throw new InformativeException('Erreur dans la requête, aucun fichier sélectionné', 404);
        }

        $files = $data['files'];
        foreach($files as $key => $file) {
            $files[$key] = $this->fileInfosHelper->hydrateFileWithAbsolutePath($file);

            if (!$this->fileInfosHelper::hasGrantedAccess($files[$key], $user)) {
                throw new InformativeException('Le fichier appartient à un projet qui ne vous concerne pas !!', 403);
            }
        }

        $archiveTempPath = ExtendedZip::createArchiveFromFiles($files);
        return $this->file($archiveTempPath, 'archive.zip');
    }

    /**
     * @Route("/edit", name="media_edit_file")
     */
    public function editFileRequest(Request $request, FileHelper $fileHelper)
    {
        $data = json_decode($request->getContent(), true);
        $newFilename = $data['newFilename'];
        $infos = $data['file'];
        
        if (is_null($newFilename) || empty($newFilename) || $newFilename[0] === '.') {
            throw new InformativeException('Le nom de fichier n\'est pas valide', 401);
        }

        $oldCompletePath = $this->fileInfosHelper->getAbsolutePath($infos['uploadRelativePath'], $infos['origin']);
        $newCompletePath = $this->fileInfosHelper->getAbsolutePath($infos['directory'], $infos['origin']).'/'.$newFilename;

        if ($this->isAdmin() || !$infos['readOnly']) {
            $fs = new Filesystem();
            $fs->rename($oldCompletePath, $newCompletePath);
        } else {
            throw new InformativeException('Impossible de renommer le fichier : '.$infos['filename'].' car vous n\'avez pas les droits nécessaires.', 401);
        }
        
        return $this->json($this->fileInfosHelper->getInfosFromDirectory($infos['directory'], $infos['origin']), 200);
    }

    /**
     * @Route("/delete", name="media_delete_file")
     */
    public function deleteFile(Request $request, FileHelper $fileHelper, FileInfosHelperInterface $fileInfosHelper)
    {
        $data = json_decode($request->getContent(), true);
        $errors = [];

        foreach ($data as $fileInfos) {

            // sécurité : on ne se base pas sur les informations de la requête...
            $fileInfos = $fileInfosHelper->getInfos($fileInfos['uploadRelativePath'], $fileInfos['origin'], true);
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
        $data = json_decode($request->getContent());

        $filename = Urlizer::urlize($data->filename);
        if (strlen($filename) > 128) {
            throw new InformativeException('Le nom du dossier est trop long.', 500);
        }
        $completePath = $this->fileInfosHelper->getAbsolutePath(
            $data->directory.'/'.$filename,
            $data->origin
        );

        $fs = new FileSystem();
        $fs->mkdir($completePath);

        return $this->json($this->fileInfosHelper->getInfosFromDirectory($data->directory, $data->origin, false, true), 200);
    }

    /**
     * @Route("/upload", name="media_upload_file", methods={"POST"})
     */
    public function uploadFile(FileHelper $fileHelper, Request $request): Response
    {
        $fileFromRequest = $request->files->get('file');
        $destRelDir = $request->request->get('directory');
        $origin = $request->request->get('origin');
        $fromFileManager = $request->request->getBoolean('fileManager', false);

        $violations = $fileHelper->validateFile($fileFromRequest, $destRelDir);
        if (count($violations) > 0) {
            throw new InformativeException(implode('\n', $violations), 415);
        }

        $fileInfos = $fileHelper->uploadFile(
            $fileFromRequest,
            $destRelDir,
            $origin,
        );

        if ($fromFileManager) {
          return $this->json($this->fileInfosHelper->getInfosFromDirectory($destRelDir, $origin), 200);
        }
        return $this->json([
            'data' => $fileInfos
        ], 200);
    }

}
