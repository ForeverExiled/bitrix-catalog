<?php

namespace Legacy\General;

class CaseChangerHelper
{
    static function array_change_key_case_recursive($arr, $case = CASE_LOWER)
    {
        return array_map(function($item) use($case) {
            if(is_array($item))
                $item = self::array_change_key_case_recursive($item, $case);
            return $item;
        },array_change_key_case($arr, $case));
    }
}