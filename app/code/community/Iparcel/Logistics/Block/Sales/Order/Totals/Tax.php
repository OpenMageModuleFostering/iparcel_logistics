<?php
/**
 * i-parcel model for shipping tax display to frontend order views
 *
 * @category    Iparcel
 * @package     Iparcel_Logistics
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_Logistics_Block_Sales_Order_Totals_Tax extends Iparcel_Logistics_Block_Sales_Order_Totals_Abstract
{
    /**
     * Find and display tax for current order/credit memo
     *
     * @return Iparcel_Logistics_Block_Sales_Order_Totals_Duty
     */
    public function initTotals()
    {
        if ((float) $this->getOrder()->getBaseIparcelTaxAmount()) {
            $source = $this->getSource();
            $value  = $source->getIparcelTaxAmount();

            $this->getParentBlock()->addTotal(new Varien_Object(array(
                'code'   => 'iparcel_tax',
                'strong' => false,
                'label'  => Mage::helper('iplogistics')->getTaxLabel(),
                'value'  => $value
            )));
        }

        return $this;
    }
}
