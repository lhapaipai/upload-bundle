<?php

namespace Pentatrion\UploadBundle\Classes;

class Size
{
    public static function getHumanSize($size): string
    {
        if (!$size) {
            return '';
        }
        $sz = ' KMGTP';
        $factor = floor((strlen($size) - 1) / 3);
        if (0 == $factor) {
            return sprintf('%.0f octets', $size);
        }

        return sprintf('%.1f ', $size / pow(1024, $factor)).@$sz[$factor].'o';
    }
}
