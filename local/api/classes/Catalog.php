<?php

namespace Legacy\API;

use Bitrix\Main\Loader;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\ElementPropertyTable;
use Legacy\Catalog\CatalogTable;
use Legacy\Catalog\FilterTable;
use Legacy\Catalog\ProductElementTable;
use Legacy\General\CaseChangerHelper;
use Legacy\General\Constants;

class Catalog
{

    private static function processData($query)
    {
        $result = [];
        
        $db = $query->exec();
        // while($arr = $db->fetch()){
        //     $arr
        // }

        return CaseChangerHelper::array_change_key_case_recursive($db->fetchAll());
    }

    public static function getCategories()
    {
        $result = [
            [
                'id' => 0,
                'name' => 'Все товары',
                'code' => 'all',
            ],
        ];

        if (Loader::includeModule('iblock')) {
            $db = SectionTable::getList([
                'select' => [
                    'ID',
                    'NAME',
                    'CODE',
                ],
                'filter' => [
                    'IBLOCK_ID' => Constants::IB_CLOTHES,
                    'ACTIVE' => true,
                ],
            ]);

            while ($res = $db->fetch()) {
                $result[] = [
                    'id' => $res['ID'],
                    'name' => $res['NAME'],
                    'code' => $res['CODE'],
                ];
            }
        }

        return $result;
    }

    public static function getFilter($arRequest, $properties)
    {
        $result = [
            'categories' => [
                'name' => 'Категория',
                'items' => self::getCategories()
            ],
        ];

        if (Loader::includeModule('iblock') && Loader::includeModule('catalog')) {
            $category = $arRequest['category'] ?? '';
            $filter = $arRequest['filter'] ?? [];
            if (count($filter) == 1) {
                foreach ($properties as $pcode => $property) {
                    if (mb_strtolower($property['CODE']) == mb_strtolower((key($filter)))) {
                        foreach ($property['VALUES'] as $key => $value) {
                            $properties[$pcode]['VALUES'][$key]['is_available'] = true;
                        }
                        break;
                    }
                }
            }

            $q = FilterTable::query()->withDefault($properties)->withFromCategory($category)->withFilter($filter);
            $db = $q->exec();
            while ($arr = $db->fetch()) {
                foreach ($arr as $column => $value) {
                    if (mb_strpos($column, 'P_') === 0) {
                        $code = mb_substr($column, 2);
                        foreach ($properties as $pcode => $property) {
                            if ($pcode == $code) {
                                $properties[$pcode]['VALUES'][$value]['is_available'] = true;
                            }
                        }
                    }
                }
            }

            foreach ($properties as $pid => $property) {
                $isEmpty = true;
                foreach($property['VALUES'] as $code => $value) {
                    $isEmpty = !$value;
                    if (!$isEmpty) {
                        break;
                    }
                }
                if ($isEmpty) {
                    unset($properties[$pid]);
                }
            }

            foreach ($properties as $pid => $property) {
                $result[mb_strtolower($property['CODE'])] = [
                    'name' => $property['NAME'],
                    'items' => $property['VALUES'],
                ];
            }
        }

        return $result;
    }

    public static function get($arRequest)
    {
        $result = [];
        if (Loader::includeModule('iblock') && Loader::includeModule('catalog')) {
            $id = $arRequest['id'] ?? [];
            $page = (int) $arRequest['page'];
            $category = $arRequest['category'] ?? '';
            $filter = $arRequest['filter'] ?? [];
            $limit = (int) $arRequest['limit'];

            $properties = PropertyTable::getList([
                'select' => [
                    'ID',
                    'CODE',
                    'NAME',
                    'PROPERTY_TYPE',
                    'MULTIPLE',
                    'USER_TYPE'
                ],
                'filter' => [
                    'IBLOCK_ID' => Constants::IB_CLOTHES,
                    'ACTIVE' => 'Y'
                    ]
            ])->fetchAll();
            $q = ProductElementTable::query()
            ->withDefault()
            ->withID($id)
            ->withCatalog()
            ->withFromCategory($category)
            ->withFilter($arRequest['filter'] ?? [])
            ->withPage($page)
            ->withLimit($limit)
            ->withExclude($arRequest['exclude'] ?? [])
            ->withProperties($properties);

            $result['items'] = self::processData($q);
            $result['count'] = $q->queryCountTotal();
            $result['categories'] = self::getCategories();
            $result['filter'] = $filter;
        }

        return $result;
    }
}