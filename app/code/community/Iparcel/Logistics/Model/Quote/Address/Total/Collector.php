<?php
/**
 * Class to reorder Magento's total collectors
 *
 * @category    Iparcel
 * @package     Iparcel_Logistics
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_Logistics_Model_Quote_Address_Total_Collector extends Mage_Sales_Model_Quote_Address_Total_Collector
{
    public function getCollectors()
    {
        $collectors = parent::getCollectors();

        $iparcel_tax = $collectors['iparcel_tax'];
        $iparcel_duty = $collectors['iparcel_duty'];
        $grand_total = $collectors['grand_total'];

        unset($collectors['iparcel_tax']);
        unset($collectors['iparcel_duty']);
        unset($collectors['grand_total']);

        $collectors['iparcel_tax'] = $iparcel_tax;
        $collectors['iparcel_duty'] = $iparcel_duty;
        $collectors['grand_total'] = $grand_total;

        return $collectors;
    }
}
