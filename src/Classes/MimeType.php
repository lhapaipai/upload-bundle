<?php

namespace Pentatrion\UploadBundle\Classes;

class MimeType
{
    public static function getIconByMimeType($mimeType): string
    {
        $mimeTypeExploded = explode('/', $mimeType);

        switch ($mimeTypeExploded[0]) {
            case 'image':
                switch ($mimeTypeExploded[1]) {
                    case 'jpeg':
                        return 'image-jpg.svg';
                    case 'png':
                        return 'image-png.svg';
                    case 'webp':
                        return 'image-webp.svg';
                    case 'svg+xml':
                    case 'svg':
                        return 'image-svg+xml.svg';
                    case 'vnd.adobe.photoshop':
                        return 'application-photoshop.svg';
                    case 'x-xcf':
                        return 'image-x-compressed-xcf.svg';
                    default:
                        return 'image.svg';
                }
                // no break
            case 'video':
                return 'video-x-generic.svg';
            case 'audio':
                return 'application-ogg.svg';
                // erreur import font
            case 'font':
                return 'application-pgp-signature.svg';
            case 'application':
                switch ($mimeTypeExploded[1]) {
                    case 'pdf':
                        return 'application-pdf.svg';
                    case 'illustrator':
                        return 'application-illustrator.svg';
                    case 'json':
                        return 'application-json.svg';
                    case 'vnd.oasis.opendocument.spreadsheet':
                        return 'libreoffice-oasis-spreadsheet.svg';
                    case 'vnd.oasis.opendocument.text':
                        return 'libreoffice-oasis-master-document.svg';
                    case 'vnd.openxmlformats-officedocument.wordprocessingml.document':
                    case 'msword':
                        return 'application-msword-template.svg';
                    case 'vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    case 'vnd.ms-excel':
                        return 'application-vnd.ms-excel.svg';
                    case 'zip':
                        return 'application-x-archive.svg';
                    default:
                        return 'application-vnd.appimage.svg';
                }
                // no break
            case 'text':
                switch ($mimeTypeExploded[1]) {
                    case 'x-php':
                        return 'text-x-php.svg';
                    case 'x-java':
                        return 'text-x-javascript.svg';
                    case 'css':
                        return 'text-css.svg';
                    case 'html':
                        return 'text-html.svg';
                    case 'xml':
                        return 'text-xml.svg';

                    default:
                        return 'text.svg';
                }

                return 'text-x-script.png';
                break;
            default:
                return 'unknown.svg';
                break;
        }
    }
}
