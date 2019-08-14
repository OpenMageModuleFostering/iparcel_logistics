<?php
/**
 * Iparcel_Logistics default data Helper
 *
 * @category    Iparcel
 * @package     Iparcel_Logistics
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_Logistics_Helper_Data extends Iparcel_All_Helper_Data
{

    /**
     * Determines if the current shipping rate request is International.
     *
     * "International" is determined by the store's Shipping Settings "Origin"
     * country. This can be overridden in the i-parcel Shipping Method settings.
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return boolean
     */
    public function getIsInternational(Mage_Shipping_Model_Rate_Request $request)
    {
        $isInternational = false;

        $originCountryId = $this->_getOriginCountry();
        $destinationCountryId = $request->getDestCountryId();

        if ($originCountryId !== $destinationCountryId) {
            $isInternational = true;
        }

        return $isInternational;
    }

    /**
     * Retrieve Service Levels from the database
     *
     * @return array Array of [ServiceLevel] => Title
     */
    public function getServiceLevels()
    {
        $serviceLevels = unserialize(Mage::getStoreConfig('carriers/i-parcel/name'));
        $formatted = array();
        foreach ($serviceLevels as $level) {
            $formatted[$level['service_id']] = $level['title'];
        }

        return $formatted;
    }

    /**
     * Finds the Origin country as configured in the Magento admin.
     *
     * @return string Two-letter Country Code
     */
    private function _getOriginCountry()
    {
        // If the admin has chose to select a different "origin" country
        if (Mage::getStoreConfig('carriers/i-parcel/choose_domestic')) {
            return Mage::getStoreConfig('carriers/i-parcel/origin_country_id');
        }

        return Mage::getStoreConfig('shipping/origin/country_id');
    }

    /**
     * Determines if an order is an i-parcel order
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function isIparcelOrder(Mage_Sales_Model_Order $order)
    {
        $iparcelCarrier = Mage::getModel('iplogistics/carrier_iparcel');
        if ($order->getShippingCarrier()->getCarrierCode() == $iparcelCarrier->getCarrierCode()) {
            return true;
        }

        return false;
    }

    /**
     * Finds the tracking number of a Magento shipment
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return string
     */
    public function getTrackingNumber(Mage_Sales_Model_Order_Shipment $shipment)
    {
        $trackingNumber = '';
        $tracks = $shipment->getAllTracks();

        if (array_key_exists(0, $tracks)) {
            $trackingNumber = $tracks[0]->getTrackNumber();
        }

        return $trackingNumber;
    }

    /**
     * Returns the admin URL to cancel a shipment
     *
     * @param string $shipment ID of shipment to cancel.
     * @return string
     */
    public function getShipmentCancelUrl($shipment)
    {
        return Mage::helper('adminhtml')->getUrl(
            'adminhtml/iplogistics_shipment/cancel',
            array('shipment' => $shipment)
        );
    }

    /**
     * Returns the admin URL to split a shipment
     *
     * @param string $shipment ID of shipment to split.
     * @return string
     */
    public function getShipmentSplitUrl($shipment)
    {
        return Mage::helper('adminhtml')->getUrl(
            'adminhtml/iplogistics_shipment/split',
            array('shipment' => $shipment)
        );
    }

    /**
     * Returns the total count of all items on a shipment
     *
     * @param $shipment Shipment ID or Mage_Sales_Model_Order_Shipment object to count
     * @return string
     */
    public function getShipmentItemCount($shipment)
    {
        if (!is_object($shipment)) {
            $shipment = Mage::getModel('sales/order_shipment')->load($shipment);
        }

        $qty = 0;
        $items = $shipment->getAllItems();
        foreach ($items as $item) {
            $qty += $item->getQty();
        }

        return $qty;
    }

    /**
     * Helper method to find the value of the tax intercepting mode
     *
     * @return boolean
     */
    public function getDisplayTaxAndDutyCumulatively()
    {
        return Mage::getStoreConfig('iparcel/tax/mode') == Iparcel_Logistics_Model_System_Config_Source_Tax_Mode::CUMULATIVELY;
    }

    /**
     * Helper method to find the value of the tax intercepting mode
     *
     * @return boolean
     */
    public function getDisplayTaxAndDutySeparately()
    {
        return Mage::getStoreConfig('iparcel/tax/mode') == Iparcel_Logistics_Model_System_Config_Source_Tax_Mode::SEPARATELY;
    }

    /**
     * Return the Admin user created "Tax" Label
     *
     * @return string
     */
    public function getTaxLabel()
    {
        $taxLabel = Mage::getStoreConfig('iparcel/tax/tax_label');
        if ($taxLabel == '') {
            return 'Tax';
        } else {
            return $taxLabel;
        }
    }

    /**
     * Return the Admin user created "Duty" Label
     *
     * @return string
     */
    public function getDutyLabel()
    {
        $dutyLabel = Mage::getStoreConfig('iparcel/tax/duty_label');
        if ($dutyLabel == '') {
            return 'Duty';
        } else {
            return $dutyLabel;
        }
    }

    /**
     * Return the Admin user created "Tax And Duty" Label
     *
     * @return string
     */
    public function getTaxAndDutyLabel()
    {
        $taxAndDutyLabel = Mage::getStoreConfig('iparcel/tax/tax_duty_label');
        if ($taxAndDutyLabel == '') {
            return 'Tax & Duty';
        } else {
            return $taxAndDutyLabel;
        }
    }
}
