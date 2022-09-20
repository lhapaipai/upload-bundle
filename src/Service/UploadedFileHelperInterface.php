<?php

namespace Pentatrion\UploadBundle\Service;

use Pentatrion\UploadBundle\Entity\UploadedFile;
use SplFileInfo;

interface UploadedFileHelperInterface
{
    public function getAbsolutePath(string $uploadRelativePath, string $origin = null): string;

    public function getWebPath(string $uploadRelativePath, string $origin = null): string;

    public function getLiipPath(string $uploadRelativePath, string $origin = null): string;

    public function getLiipId(string $uploadRelativePath, string $originName = null): string;

    public function getLiipPathFromFile(SplFileInfo $file, string $originName = null);

    public function parseLiipId($liipId): array;

    public function getUrlThumbnail(string $id, string $filter, bool $pregenerate = false, $withTimeStamp = true, array $runtimeConfig = []);

    public function getUploadedFile($uploadRelativePath, $originName = null): ?UploadedFile;

    public function getUploadedFileByLiipId(string $fileId): UploadedFile;

    public function getUploadedFilesFromDirectory(string $directorySuffix, string $origin, string $mimeGroup = null, bool $withDirectoryInfos = false): array;

    public function addAbsolutePath(UploadedFile $uploadedFile): UploadedFile;

    public function hydrateFileWithAbsolutePath(UploadedFile $uploadedFile): string;

    public function eraseSensibleInformations(UploadedFile $uploadedFile): UploadedFile;

    public static function hasGrantedAccess(UploadedFile $uploadedFile, $user);

    public function addAdditionalInfos(&$infos);

    public function addAdditionalInfosToDirectoryFiles(&$data);
}
