<?php
/**
 * i-parcel shipping method model
 *
 * @category    Iparcel
 * @package     Iparcel_Logistics
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_Logistics_Model_Carrier_Iparcel extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'iplogistics';

    protected $_trackingUrl = 'https://tracking.i-parcel.com/secure/track.aspx?track=';

    /**
     * Check if carrier has shipping label option available
     *
     * @return bool
     */
    public function isShippingLabelsAvailable()
    {
        return true;
    }

    /**
     * Check if carrier has shipping tracking option available
     *
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Get info for track order page
     *
     * @param string $number Tracking Number
     * @return Varien_Object
     */
    public function getTrackingInfo($number)
    {
        return new Varien_Object(array(
            'tracking' => $number,
            'carrier_title' => $this->getConfigData('title'),
            'url' => $this->_trackingUrl.$number
        ));
    }

    /**
     * Return container types of carrier
     *
     * @return array
     */
    public function getContainerTypes(Varien_Object $params = null)
    {
        return array('DEFAULT' => Mage::helper('iplogistics')->__('Default box'));
    }

    /**
     * Do request to shipment
     *
     * @param Mage_Shipping_Model_Shipment_Request $request
     * @return Varien_Object
     */
    public function requestToShipment(Mage_Shipping_Model_Shipment_Request $request)
    {
        $shipping = $request->getOrderShipment();
        /* var $shipping Mage_Sales_Model_Order_Shipment */
        $tracking = $shipping->getAllTracks();
        if (empty($tracking)) {
            Mage::throwException('Invalid Request To Shipment Call');
        }
        $tracking = $tracking[0];
        /* var $tracking Mage_Sales_Model_Order_Shipment_Track */

        // prepare label PDF
        $pdf = new Zend_Pdf();
        $number = $tracking->getNumber();
        $pdfPage = $pdf->pages[] = new Zend_Pdf_Page(('162:75'));
        $barcodeFont = Zend_Pdf_Font::fontWithPath(Mage::getBaseDir('media').'/font/code128.ttf');
        $courier = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_COURIER);
        $pdfPage->setFont($courier, 10);
        $pdfPage->drawText($number, 15, 10);
        $pdfPage->setFont($barcodeFont, 40);
        $pdfPage->drawText($number, 15, 25);

        $info = array();
        $info[] = array(
            'label_content'     =>  $pdf->render(),
            'tracking_number'   =>  $number
        );

        // prepare response
        $response = new Varien_Object();
        $response->setTrackingNumer($number);
        $response->setInfo($info);

        return $response;
    }

    /**
     * Collect shipping rates for i-parcel shipping
     * refactor: add result check, add intermediate storage for parcel_id
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return Mage_Shipping_Model_Rate_Result|bool
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        try {
            /** @var boolean $internationalOrder */
            $internationalOrder = Mage::helper('iplogistics')->getIsInternational($request);

            if ($internationalOrder && Mage::getStoreConfig('carriers/iplogistics/active')) {
                /** @var array $iparcel Tax & Duty totals */
                $iparcelTaxAndDuty = array();
                /** @var Mage_Shipping_Model_Rate_Result $result*/
                $result = Mage::getModel('shipping/rate_result');
                
				 // Get Allowed Methods
				 /** @var array $allowed_methods Shipping method allowed via admin config "names" */
				 $allowed_methods = $this->getAllowedMethods();
                
				 /** @var stdClass $quote */
				 $quote = Mage::helper('iplogistics/api')->quote($request);
				 $iparcelTaxAndDuty['parcel_id'] = $quote->ParcelID;

				 $serviceLevel = new stdClass;
				 if (isset($quote->ServiceLevels)) {
					 $serviceLevel = $quote->ServiceLevels;
				 }

				 // Handling serviceLevels results and set up the shipping method
				 foreach ($serviceLevel as $ci) {
					 // setting up values
					 $servicename = @$ci->ServiceLevelID;

					 $duty = (float)@$ci->DutyCompanyCurrency;
					 $tax = (float)@$ci->TaxCompanyCurrency;
					 $shipping = (float)@$ci->ShippingChargeCompanyCurrency;

					 $tax_flag = Mage::getStoreConfig('iparcel/tax/mode') == Iparcel_Logistics_Model_System_Config_Source_Tax_Mode::DISABLED
						 || $request->getDestCountryId() == $request->getCountryId();
					 // true if tax intercepting is disabled

					 $total = $tax_flag ? (float)($duty + $tax + $shipping) : (float)$shipping;
					 if (!isset($allowed_methods[$servicename])) {
						 continue;
					 }

					 $shiplabel = $allowed_methods[$servicename];

					 $title = $shiplabel;
					 if ($tax_flag) {
						 $title = Mage::helper('iplogistics')->__(
							 '%s (Shipping Price: %s Duty: %s Tax: %s)',
							 $shiplabel,
							 $this->_formatPrice($shipping),
							 $this->_formatPrice($duty),
							 $this->_formatPrice($tax)
						 );
					 }
					 
					 $method = Mage::getModel('shipping/rate_result_method');
					 $method->setCarrier($this->_code);
					 $method->setCarrierTitle($this->getConfigData('title'));
					 $method->setMethod($servicename);
					 $method->setMethodTitle($title);
					 $method->setPrice($total);
					 $method->setCost($total);
					 
					 // append method to result
					 $result->append($method);

					 $iparcelTaxAndDuty['service_levels'][$this->_code . '_' . $servicename] = array(
						 'duty' => $duty,
						 'tax' => $tax
					 );
				 }

                Mage::unregister('iparcel_duty_and_taxes');
                Mage::register('iparcel_duty_and_taxes', $iparcelTaxAndDuty);
                return $result;
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return false;
    }

    public function getMethodsNames()
    {
        $names = array();
        $raw = $this->getConfigData('name');

        $raw = unserialize($raw);

        foreach ($raw as $method) {
            $names[$method['service_id']] = $method['title'];
        }

        return $names;
    }

    /**
     * @param float|int $price
     * @return string
     */
    protected function _formatPrice($price)
    {
        /** @var Mage_Core_Helper_Data $helper */
        $helper = Mage::helper('core');
        return $helper->formatPrice($price, false);
    }

    /**
     * Get Allowed Shipping Methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {    
        return $this->getMethodsNames();
    }
}
