<?php

namespace Pentatrion\UploadBundle\Service;

interface FileInfosHelperInterface
{
    public function getAbsolutePath(string $uploadRelativePath, string $origin): string;
    public function getWebPath(string $uploadRelativePath, string $origin): string;
    public function getLiipPath(string $uploadRelativePath, string $origin): string;
    public function getId(string $uploadRelativePath, string $originName = null): string;
    public function getUrlThumbnail(string $id, string $filter, bool $pregenerate = false, $withTimeStamp = true, array $runtimeConfig = []);
    public function getUploadedFileFromPath($uploadRelativePath, $originName = null): ?array;
    public function getInfosById(string $fileId, bool $withAbsPath = false): array;
    public function getInfos(string $uploadRelativePath, string $origin, bool $withAbsPath = false): array;
    public function getInfosFromDirectory(string $directorySuffix, string $origin, bool $withAbsPath = false, bool $withDirectoryInfos = false): array;
    public function hydrateEntityWithUploadedFileData($entity, $uploadFields = [], $filters = [], $originName = "public_uploads"): array;



    public function hydrateFileWithAbsolutePath(array $fileInfos);
    public function eraseSensibleInformations(array $fileInfos);
    public function getInfosFromFileObj(\SplFileInfo $file, bool $withAbsPath = false);
    public static function getHumanSize(int $size);
    public static function getIconByMimeType(string $mimeType);
    public static function hasGrantedAccess(array $fileInfos, $user);
    public function addAdditionalInfos(&$infos);
    public function addAdditionalInfosToDirectoryFiles(&$data);
}
