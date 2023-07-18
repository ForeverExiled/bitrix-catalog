<?php

namespace Legacy\API;

use Bitrix\Main\Loader;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionTable;
use Legacy\Catalog\ProductElementTable;
use Legacy\General\CaseChangerHelper;
use Legacy\General\Constants;

class Catalog
{

    private static function processData($query)
    {
        $db = $query->exec();
        $result = $db->fetchAll();

        return CaseChangerHelper::array_change_key_case_recursive($result);
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