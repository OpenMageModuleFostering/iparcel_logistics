<?php
/**
 * General Helper for Iparcel_All
 *
 * @category    Iparcel
 * @package     Iparcel_All
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_All_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get GUID key
     *
     * @return string
     */
    public function getGuid()
    {
        return Mage::getStoreConfig('iparcel/config/userid');
    }

    /**
     * Get Customer ID
     *
     * @return string
     */
    public function getCustomerId()
    {
        return Mage::getStoreConfig('iparcel/config/custid');
    }
}
