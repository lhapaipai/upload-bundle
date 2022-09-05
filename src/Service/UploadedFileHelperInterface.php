<?php

namespace Pentatrion\UploadBundle\Service;

use Pentatrion\UploadBundle\Entity\UploadedFile;

interface UploadedFileHelperInterface
{
    public function getAbsolutePath(string $uploadRelativePath, string $origin): string;

    public function getWebPath(string $uploadRelativePath, string $origin): string;

    public function getLiipPath(string $uploadRelativePath, string $origin): string;

    public function getLiipId(string $uploadRelativePath, string $originName = null): string;

    public function parseLiipId($liipId): array;

    public function getUrlThumbnail(string $id, string $filter, bool $pregenerate = false, $withTimeStamp = true, array $runtimeConfig = []);

    public function getUploadedFile($uploadRelativePath, $originName = null): ?UploadedFile;

    public function getUploadedFileByLiipId(string $fileId): UploadedFile;

    public function getUploadedFilesFromDirectory(string $directorySuffix, string $origin, string $mimeGroup = null, bool $withDirectoryInfos = false): array;

    public function addAbsolutePath(UploadedFile $uploadedFile): UploadedFile;

    public function hydrateFileWithAbsolutePath(array $fileInfos);

    public function eraseSensibleInformations(array $fileInfos);

    public static function hasGrantedAccess(UploadedFile $uploadedFile, $user);

    public function addAdditionalInfos(&$infos);

    public function addAdditionalInfosToDirectoryFiles(&$data);
}
