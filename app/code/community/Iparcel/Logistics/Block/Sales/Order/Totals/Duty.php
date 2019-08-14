<?php
/**
 * i-parcel model for shipping duty display to frontend order views
 *
 * @category    Iparcel
 * @package     Iparcel_Logistics
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_Logistics_Block_Sales_Order_Totals_Duty extends Iparcel_Logistics_Block_Sales_Order_Totals_Abstract
{
    /**
     * Find and display duty for current order/credit memo
     *
     * @return Iparcel_Logistics_Block_Sales_Order_Totals_Duty
     */
    public function initTotals()
    {
        if ((float) $this->getOrder()->getBaseIparcelDutyAmount()) {
            $source = $this->getSource();
            $value  = $source->getIparcelDutyAmount();

            $this->getParentBlock()->addTotal(new Varien_Object(array(
                'code'   => 'iparcel_duty',
                'strong' => false,
                'label'  => Mage::helper('iplogistics')->getDutyLabel(),
                'value'  => $value
            )));
        }

        return $this;
    }
}
