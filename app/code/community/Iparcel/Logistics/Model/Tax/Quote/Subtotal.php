<?php
/**
 * Class to override Magento's tax rate
 *
 * @category    Iparcel
 * @package     Iparcel_Logistics
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_Logistics_Model_Tax_Quote_Subtotal extends Mage_Tax_Model_Sales_Total_Quote_Subtotal
{
    /**
     * Returns i-parcel's quote address abstract class
     *
     * @return Iparcel_Logistics_Model_Quote_Address_Total_Abstract
     */
    private function _getAbstract()
    {
        $model = new Iparcel_Logistics_Model_Quote_Address_Total_Abstract;
        return $model;
    }

    /**
     * Prevent's Magento's tax calculation if i-parcel shipping method is used
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return object
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        if ($this->_getAbstract()->isIparcelShipping($address)) {
            return;
        } else {
            return parent::fetch($address);
        }
    }

    /**
     * Prevent's Magento's tax display if i-parcel shipping method is used
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Iparcel_Logistics_Model_Quote_Address_Total_Magentotax
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        if ($this->_getAbstract()->isIparcelShipping($address)) {
            return;
        } else {
            return parent::collect($address);
        }
    }
}