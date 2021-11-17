<?php

namespace Pentatrion\UploadBundle\Service;

interface FileInfosHelperInterface
{
  public function getAbsolutePath(string $uploadRelativePath, string $origin);
  public function getWebPath(string $uploadRelativePath, string $origin);
  public function getLiipPath(string $uploadRelativePath, string $origin);
  public function getInfosFromDirectory(string $directorySuffix, string $origin, bool $withAbsPath = false, bool $withDirectoryInfos = false);
  public function hydrateFileWithAbsolutePath(array $fileInfos);
  public function eraseSensibleInformations(array $fileInfos);
  public function getInfos(string $uploadRelativePath, string $origin, bool $withAbsPath = false);
  public function getInfosById(string $fileId, bool $withAbsPath = false);
  public function getInfosFromFileObj(\SplFileInfo $file, bool $withAbsPath = false);
  public static function getHumanSize(int $size);
  public static function getIconByMimeType(string $mimeType);
  public static function hasGrantedAccess(array $fileInfos, $user);
  public function addAdditionalInfos(&$infos);
  public function addAdditionalInfosToDirectoryFiles(&$data);
  public function getId(string $uploadRelativePath, string $originName = null);
  public function getUrlThumbnail(string $id, string $filter, bool $pregenerate = false, $withTimeStamp = true, array $runtimeConfig = []);
}
