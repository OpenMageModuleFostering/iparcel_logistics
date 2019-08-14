<?php
/**
 * i-parcel model for shipping duty
 *
 * @category    Iparcel
 * @package     Iparcel_Logistics
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_Logistics_Model_Quote_Address_Total_Duty extends Iparcel_Logistics_Model_Quote_Address_Total_Abstract
{
    public function __construct()
    {
        $this->setCode('iparcel_duty');
    }

    /**
     * Used each time collectTotals is invoked to find the duty rate
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Iparcel_Logistics_Model_Total_Duty
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        if ($address->getAddressType() != 'shipping') {
            return;
        }

        if (Mage::helper('iplogistics')->getDisplayTaxAndDutyCumulatively()
            || Mage::helper('iplogistics')->getDisplayTaxAndDutySeparately()) {
            parent::collect($address);

            $dutyAndTaxes = Mage::registry('iparcel_duty_and_taxes');
            $duty = $dutyAndTaxes['service_levels'][$address->getShippingMethod()]['duty'];

            $this->_setBaseAmount($duty);
            $this->_setAmount(
                $address->getQuote()->getStore()->convertPrice($duty, false)
            );
        }
        return $this;
    }

    /**
     * Display the duty rate
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Iparcel_Logistics_Model_Total_Duty
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        if ($address->getAddressType() != 'shipping'
            || $this->isIparcelShipping($address) == false) {
            return;
        }

        $duty = $address->getIparcelDutyAmount();

        // If we should show the tax and duty cumulatively
        if (Mage::helper('iplogistics')->getDisplayTaxAndDutyCumulatively()
            && ($duty != null || $address->getIparcelTaxAmount() != null)) {
            $address->addTotal(array(
                'code' => $this->getCode(),
                'title' => Mage::helper('iplogistics')->getTaxAndDutyLabel(),
                'value' => $duty + $address->getIparcelTaxAmount()
            ));
        } elseif (Mage::helper('iplogistics')->getDisplayTaxAndDutySeparately()
            && $duty != null) {
            $address->addTotal(array(
                'code' => $this->getCode(),
                'title' => Mage::helper('iplogistics')->getDutyLabel(),
                'value' => $duty
            ));
        }
    }
}
