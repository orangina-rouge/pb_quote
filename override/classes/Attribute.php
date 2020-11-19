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

class Attribute extends AttributeCore
{

    /**
     * AttributeCore constructor.
     *
     * @param int|null $id Attribute ID
     * @param int|null $idLang Language ID
     * @param int|null $idShop Shop ID
     */
    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        parent::__construct($id, $idLang, $idShop);
    }

    /**
     * Get all attributes for a given language.
     *
     * @param int $idLang Language ID
     * @param bool $notNull Get only not null fields if true
     *
     * @return array Attributes
     */
    public static function getAttributes($idLang, $notNull = false)
    {
        $results = array();
        $rooms = Product::getRooms();
        $vats = Product::getVats();
        foreach ($rooms as $idRoom => $room) {
            foreach ($vats as $idVat => $vat) {
                $attribute = Product::baseAttribute();
                $attribute['id_attribute'] = Product::getIdAttributeVat($idVat);
                $attribute['id_attribute_group'] = '1';
                $attribute['name'] = $vat;
                $attribute['attribute_group'] = Product::VAT;
                $attribute['position'] = strval($idVat);
                $attribute['group_type'] = 'select';

                $results[]= $attribute;

                $attribute = Product::baseAttribute();
                $attribute['id_attribute'] = Product::getIdAttributeRoom($idRoom);
                $attribute['id_attribute_group'] = '2';
                $attribute['name'] = $room;
                $attribute['attribute_group'] = Product::ROOM;
                $attribute['position'] = strval($idRoom);
                $attribute['group_type'] = 'select';

                $results[]= $attribute;
            }

        }
        return $results;
    }

    /**
     * Check if the given name is an Attribute within the given AttributeGroup.
     *
     * @param int $idAttributeGroup AttributeGroup
     * @param string $name Attribute name
     * @param int $idLang Language ID
     *
     * @return array|bool
     */
    public static function isAttribute($idAttributeGroup, $name, $idLang)
    {
        if (3 == (int) $idAttributeGroup) {
            $names = Product::getVats();
        } elseif (2 == (int) $idAttributeGroup) {
            $names = Product::getRooms();
        }
        if(in_array($name, $names)) {
            return (int) true;
        } else {
            return (int) false;
        }
    }

    /**
     * Get quantity for a given attribute combination
     * Check if quantity is enough to serve the customer.
     *
     * @param int $idProductAttribute Product attribute combination id
     * @param int $qty Quantity needed
     * @param Shop $shop Shop
     *
     * @return bool Quantity is available or not
     */
    public static function checkAttributeQty($idProductAttribute, $qty, Shop $shop = null)
    {
        return true;
    }

    /**
     * Return true if the Attribute is a color.
     *
     * @return bool Color is the attribute type
     */
    public function isColorAttribute()
    {
        return false;
    }

    /**
     * Get minimal quantity for product with attributes quantity.
     *
     * @param int $idProductAttribute Product Attribute ID
     *
     * @return mixed Minimal quantity or false if no result
     */
    public static function getAttributeMinimalQty($idProductAttribute)
    {
        return false;
    }

    /**
     * get highest position.
     *
     * Get the highest attribute position from a group attribute
     *
     * @param int $idAttributeGroup AttributeGroup ID
     *
     * @return int $position Position
     * @todo: Shouldn't this be called getHighestPosition instead?
     */
    public static function getHigherPosition($idAttributeGroup)
    {
        if (3 == (int) $idAttributeGroup) {
            $names = Product::getVats();
        } elseif (2 == (int) $idAttributeGroup) {
            $names = Product::getRooms();
        }
        return sizeof($names) - 1;
    }
}
