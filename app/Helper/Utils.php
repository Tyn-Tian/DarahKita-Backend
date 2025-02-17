<?php

namespace App\Helper;

class Utils
{
    public static function splitBloodType($bloodType)
    {
        if (!$bloodType) {
            return null;
        }
        preg_match('/^(a|b|ab|o)([+-])$/', $bloodType, $matches);

        if ($matches) {
            return [
                'blood' => $matches[1],
                'rhesus' => $matches[2]
            ];
        }

        return null;
    }
}
