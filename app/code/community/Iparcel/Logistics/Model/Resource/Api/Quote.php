<?php
/**
 * Resource Model for Iparcel_Logistics_Model_Api_Quote class
 *
 * @category    Iparcel
 * @package     Iparcel_Logistics
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_Logistics_Model_Resource_Api_Quote extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Initializing Resource
     */
    protected function _construct()
    {
        $this->_init('iplogistics/api_quote', 'id');
    }
}
