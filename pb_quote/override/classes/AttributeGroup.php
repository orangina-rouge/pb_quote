<?php
/**
 * 2020 point-barre.com
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @author    tenshy
 * @copyright 2020 point-barre.com
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
class AttributeGroup extends AttributeGroupCore
{
    /**
     * Get all attributes for a given language / group.
     *
     * @param int $idLang Language ID
     * @param int $idAttributeGroup AttributeGroup ID
     *
     * @return array Attributes
     */
    public static function getAttributes($idLang, $idAttributeGroup)
    {
        $results = array();

        if ((int)Product::GROUP_VAT == (int) $idAttributeGroup) {
            $vats = Product::getVats();
            foreach ($vats as $idVat => $vat) {
                $attribute = array();
                $attribute['id_attribute'] = Product::getIdAttributeVat($idVat);
                $attribute['id_attribute_group'] = Product::GROUP_VAT;
                $attribute['name'] = $vat;
                $attribute['position'] = strval($idVat);
                $attribute['color'] = '';
                $attribute['id_lang'] = $idLang;

                $results[]= $attribute;
            }
        } elseif ((int)Product::GROUP_ROOM == (int) $idAttributeGroup) {
            $rooms = Product::getRooms();
            foreach ($rooms as $idRoom => $room) {
                $attribute = array();
                $attribute['id_attribute'] = Product::getIdAttributeRoom($idRoom);
                $attribute['id_attribute_group'] = Product::GROUP_ROOM;
                $attribute['name'] = $room;
                $attribute['position'] = strval($idRoom);
                $attribute['color'] = '';
                $attribute['id_lang'] = $idLang;

                $results[]= $attribute;
            }
        }
        return $results;
    }

    /**
     * Get all attributes groups for a given language.
     *
     * @param int $idLang Language id
     *
     * @return array Attributes groups
     */
    public static function getAttributesGroups($idLang)
    {
        $results = array();

        $attribute = array();
        $attribute['id_attribute_group'] = Product::GROUP_VAT;
        $attribute['name'] = Product::VAT;
        $attribute['public_name'] = Product::TVA;
        $attribute['position'] = strval(1);
        $attribute['group_type'] = 'select';
        $attribute['is_color_group'] = '0';
        $attribute['id_lang'] = $idLang;

        $results[]= $attribute;

        $attribute = array();
        $attribute['id_attribute_group'] = Product::GROUP_ROOM;
        $attribute['name'] = Product::ROOM;
        $attribute['public_name'] = Product::PIECE;
        $attribute['position'] = strval(0);
        $attribute['group_type'] = 'select';
        $attribute['is_color_group'] = '0';
        $attribute['id_lang'] = $idLang;

        $results[]= $attribute;

        return $results;
    }

    /**
     * Get the highest AttributeGroup position.
     *
     * @return int $position Position
     */
    public static function getHigherPosition()
    {
        return 1;
    }
}
