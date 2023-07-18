<?php

namespace Legacy\Catalog;

Use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\ProductTable;
use Bitrix\Iblock\ElementTable;
use Legacy\General\Constants;
use Legacy\Iblock\ElementPropertyTable;

class ProductElementTable extends ElementTable
{
    const DEFAULT_LIMIT = 24;
    const ASCENDING = 'ASC';
    const DESCENDING = 'DESC';

    public static function withDefault(Query $query)
    {
        $query->setSelect([
            'ID',
            'CODE',
            'NAME',
            'SECTION_ID' => 'IBLOCK_SECTION.ID',
            'SECTION_CODE' => 'IBLOCK_SECTION.CODE',
            'SECTION_NAME' => 'IBLOCK_SECTION.NAME',
        ]);
        $query->setFilter([
            'IBLOCK_ID' => Constants::IB_CLOTHES,
            'ACTIVE' => 'Y'
        ]);
        
        if ($query->getLimit() == 0) {
            $query->setLimit(self::DEFAULT_LIMIT);
        }
    }

    public static function withID(Query $query, array $ids)
    {
        if (count($ids) > 0) {
            $query->addFilter('=ID', $ids);
        }
    }

    public static function withCatalog(Query $query)
    {
        $query->registerRuntimeField(
            'ELEMENT_PRODUCT',
            new ReferenceField(
                'ELEMENT_PRODUCT',
                ProductTable::class,
                [
                    'this.ID' => 'ref.ID',
                ]
            )
        );
        $query->registerRuntimeField(
            'ELEMENT_PRICE',
            new ReferenceField(
                'ELEMENT_PRICE',
                PriceTable::class,
                [
                    'this.ID' => 'ref.PRODUCT_ID',
                ]
            )
        );
        $query->addSelect('ELEMENT_PRICE.PRODUCT_ID', 'PRODUCT_ID');
        $query->addSelect('ELEMENT_PRICE.PRICE', 'PRICE');
        $query->addSelect('ELEMENT_PRICE.CURRENCY', 'CURRENCY');
        $query->addSelect('ELEMENT_PRODUCT.QUANTITY', 'QUANTITY');
    }

    public static function withFromCategory(Query $query, string $category)
    {
        if (mb_strlen($category) > 0 && $category !== 'all') {
            $query->addFilter(null, [
                'IBLOCK_SECTION.CODE' => $category
            ]);
        }
    }

    public static function withFilter(Query $query, array $filter)
    {
        foreach ($filter as $code => $value) {
            $key = 'FILTER_PROPERTY_'.mb_strtoupper($code);
            $query->registerRuntimeField(
                $key, 
                new ReferenceField(
                    $key,
                    ElementPropertyTable::class,
                    [
                        'this.ID' => 'ref.IBLOCK_ELEMENT_ID',
                        'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', constant('Legacy\General\Constants::IB_PROP_CLOTHES_'.mb_strtoupper($code))),
                    ]
                )
            );
            $query->addFilter('@'.$key.'.VALUE', $value);
        }
    }

    public static function withPage(Query $query, int $page)
    {
        if ($page > 0) {
            $query->setOffset(($page - 1) * $query->getLimit());
        }
    }
    
    public static function withExclude(Query $query, array $ids)
    {
        if (count($ids) > 0) {
            $query->addFilter('!=ID', $ids);
        }
    }

    public static function withProperties(Query $query, $properties)
    {
        foreach($properties as $code => $property) {
            $key = $property['CODE'];
            $query->registerRuntimeField(
                $key,
                new ReferenceField(
                    $key,
                    ElementPropertyTable::class,
                    [
                        'this.ID' => 'ref.IBLOCK_ELEMENT_ID',
                        'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', $property['ID']),
                    ]
                )
            );
            if ($property['MULTIPLE']) {
                $query->addSelect(new \Bitrix\Main\Entity\ExpressionField(
                    $key.'_VALUE',
                    'GROUP_CONCAT(distinct %s)',
                    [$key.'.VALUE']
                ));
            } else {
                $query->addSelect($key.'.VALUE', $key.'_VALUE');
            }
        }
    }

    public static function withLimit(Query $query, int $limit)
    {
        $query->setLimit($limit);
    }
}