<?php
/**
 * I-parcel sending API helper
 *
 * This helper facilitates the connection to the API web service documented
 * here: http://webservices.i-parcel.com/Help
 *
 * @category    Iparcel
 * @package     Iparcel_Logistics
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_Logistics_Helper_Api extends Iparcel_All_Helper_Api
{
    /** @var string URL for the SubmitParcel endpoint */
    protected $_submitParcel = 'https://webservices.i-parcel.com/api/SubmitParcel';

    /** @var string URL for the Quote endpoint */
    protected $_quote = 'https://webservices.i-parcel.com/api/Quote';

    /** @var string URL for the Cancel endpoint */
    protected $_cancel = 'https://webservices.i-parcel.com/api/Cancel';

    /** @var string URL for the Split endpoint */
    protected $_split = 'https://webservices.i-parcel.com/api/Split';

    /**
     * Send Quote request
     *
     * Takes the passed Rate Request and transforms it into data that can
     * be passed to the API. It then submits the request as JSON to the
     * Quote API endpoint.
     *
     * @param Mage_Shipping_Model_Rate_Request $request Magento Shipping Rate Request
     * @return stdClass Response from the Quote API
     */
    public function quote(Mage_Shipping_Model_Rate_Request $request)
    {
        // log init
        $log = Mage::getModel('iparcel/log');
        /* var $log Iparcel_All_Model_Api_Log */
        $log->setController('Quote');

        $quote = Mage::getModel('checkout/cart')->getQuote();
        /* var $quote Mage_Sales_Model_Quote */
        $shippingAddress = $quote->getShippingAddress();
        /* var $shippingAddress Mage_Sales_Model_Quote_Address */
        $billingAddress = $quote->getBillingAddress();
        /* var $billingAddress Mage_Sales_Model_Quote_Address */

        $json = array();
        $addressInfo = array();

        $billingStreet = $billingAddress->getStreet();

        $billing = array();
        $billing['City'] = $billingAddress->getCity();
        $billing['CountryCode'] = $billingAddress->getCountryId();
        $billing['Email'] = $quote->getCustomerEmail();
        $billing['FirstName'] = $billingAddress->getFirstname();
        $billing['LastName'] = $billingAddress->getLastname();
        $billing['Phone'] = $billingAddress->getTelephone();
        $billing['PostCode'] = $billingAddress->getPostcode();
        $billing['Region'] = $billingAddress->getRegion();
        for ($i=0; $i<count($billingStreet); $i++) {
            $billing['Street'.($i+1)] = $billingStreet[$i];
        }

        $addressInfo['Billing'] = $billing;

        $shippingStreet = explode("\n", $request->getDestStreet());

        $shipping = array();

        $shipping['City'] = $request->getDestCity();
        $shipping['CountryCode'] = $request->getDestCountryId();
        $shipping['Email'] = $quote->getCustomerEmail();
        $shipping['FirstName'] = $shippingAddress->getFirstname();
        $shipping['LastName'] = $shippingAddress->getLastname();
        $shipping['Phone'] = $shippingAddress->getTelephone();
        $shipping['PostCode'] = $request->getDestPostcode();
        $shipping['Region'] = $request->getDestRegionCode();
        foreach($shippingStreet as $key => $value) {
            $shipping['Street' . ($key + 1)] = $value;
        }

        $addressInfo['Shipping'] = $shipping;

        $addressInfo['ControlNumber'] = $quote->getCpf();

        $json['AddressInfo'] = $addressInfo;

        $json['CurrencyCode'] = $request->getPackageCurrency()->getCurrencyCode();
        $json['DDP'] = true;

        $itemsList = array();

        foreach ($request->getAllItems() as $item) {
            /* var $item Mage_Sales_Model_Quote_Item */

            $itemProduct = Mage::getModel('catalog/product')->load($item->getProductId());
            /* var $itemProduct Mage_Catalog_Model_Product */

            //get item price
            $itemPrice = (float)$item->getFinalPrice() ?: (float)$item->getPrice();
            // if not price and item has parent (is configurable)
            if (!$itemPrice && ($parent=$item->getParentItem())) {
                // get parent price
                $itemPrice = (float)$parent->getFinalPrice() ?: (float)$parent->getPrice();
            }
            // if still not price
            if (!$itemPrice) {
                // get product price
                $itemPrice = (float)$item->getProduct()->getPrice();
            }

            // if product isn't virtual and is configurable or downloadable
            if ($item["is_virtual"] == false && !in_array($itemProduct->getTypeId(), array('configurable','downloadable'))) {
                // add line item node
                $lineItem = array();

                $lineItem['SKU'] = $item->getSku();
                $lineItem['CustWeightLbs'] = (float)$item->getWeight();
                $lineItem['ValueUSD'] = $itemPrice;
                $lineItem['CustLengthInches'] = (float)$item->getLength();
                $lineItem['CustWidthInches'] = (float)$item->getWidth();
                $lineItem['CustHeightInches'] = (float)$item->getHeight();
                $lineItem['Quantity'] = $item->getTotalQty();
                $lineItem['ValueShopperCurrency'] = $itemPrice;
                $lineItem['ShopperCurrency'] = Mage::app()->getStore()->getCurrentCurrencyCode();

                $itemsList[] = $lineItem;
            }
        }

        $json['ItemDetailsList'] = $itemsList;

        // Get discounts
        $totals = $quote->getTotals();
        $discount = 0;
        if (isset($totals['discount']) && $totals['discount']->getValue()) {
            $discount = -1 * $totals['discount']->getValue();
        }

        $json['OtherDiscount'] = $discount;
        $json['OtherDiscountCurrency'] = $quote->getQuoteCurrencyCode();
        $json['ParcelID'] = 0;
        $json['ServiceLevel'] = 115;
        $json['SessionID'] = '';
        $json['key'] = Mage::helper('iplogistics')->getGuid();

        $log->setRequest(json_encode($json));

        $response = $this->_restJSON($json, $this->_quote);

        $log->setResponse($response);
        $log->save();

        return json_decode($response);
    }

    /**
     * Send Submit Parcel request
     *
     * Takes the passed Shipment and transforms it into data that can be passed
     * to the API. It then submits the request as JSON to the SubmitParcel API
     * endpoint.
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment Magento shipment to be acknowledged as parcel
     * @return stdClass Response from the SubmitParcel API
     */
    public function submitParcel(Mage_Sales_Model_Order_Shipment $shipment)
    {
        $order = $shipment->getOrder();

        // init log
        $log = Mage::getModel('iparcel/log');
        /* var $log Iparcel_All_Model_Api_Log */
        $log->setController('Submit Parcel');

        $shippingAddress = $order->getShippingAddress();
        /* var $shippingAddress Mage_Sales_Model_Quote_Address */
        $billingAddress = $order->getBillingAddress();
        /* var $billingAddress Mage_Sales_Model_Quote_Address */

        $json = array();

        $addressInfo = array();

        $billingStreet = $billingAddress->getStreet();

        $billing = array();

        $billing['City'] = $billingAddress->getCity();
        $billing['CountryCode'] = $billingAddress->getCountryId();
        $billing['Email'] = $order->getCustomerEmail();
        $billing['FirstName'] = $billingAddress->getFirstname();
        $billing['LastName'] = $billingAddress->getLastname();
        $billing['Phone'] = $billingAddress->getTelephone();
        $billing['PostCode'] = $billingAddress->getPostcode();
        $billing['Region'] = $billingAddress->getRegion();
        $billing['Street1'] = $billingStreet[0];
        if (array_key_exists(1, $billingStreet)) {
            $billing['Street2'] = $billingStreet[1];
        }

        $addressInfo['Billing'] = $billing;

        $shippingStreet = $shippingAddress->getStreet();

        $shipping = array();

        $shipping['City'] = $shippingAddress->getCity();
        $shipping['CountryCode'] = $shippingAddress->getCountryId();
        $shipping['Email'] = $order->getCustomerEmail();
        $shipping['FirstName'] = $shippingAddress->getFirstname();
        $shipping['LastName'] = $shippingAddress->getLastname();
        $shipping['Phone'] = $shippingAddress->getTelephone();
        $shipping['PostCode'] = $shippingAddress->getPostcode();
        $shipping['Region'] = $shippingAddress->getRegion();
        $shipping['Street1'] = $shippingStreet[0];
        $shipping['Street2'] = $shippingStreet[1];

        $addressInfo['Shipping'] = $shipping;

        $addressInfo['ControlNumber'] = $order->getCpf();

        $json['AddressInfo'] = $addressInfo;

        $json['CurrencyCode'] = $order->getOrderCurrencyCode();
        $json['DDP'] = true;

        $shipmentItems = $shipment->getAllItems();

        $itemsList = array();
        foreach ($shipmentItems as $item) {
            /** @var $item Mage_Sales_Model_Order_Shipment_Item */

            // Check for a configurable product -- the simple should be loaded
            /** @var $itemProduct Mage_Catalog_Model_Product */
            $orderItem = $item->getOrderItem();
            if ($orderItem->getProductType() == "configurable") {
                $itemProduct = $orderItem->getChildrenItems();
                $itemProduct = $itemProduct[0];
            } else {
                $itemProduct = Mage::getModel('catalog/product')->load($item->getOrderItem()->getProductId());
            }

            //get item price
            $itemPrice = (float)$item->getFinalPrice() ?: (float)$item->getPrice();
            // if not price and item has parent (is configurable)
            if (!$itemPrice && ($parent=$item->getParentItem())) {
                // get parent price
                $itemPrice = (float)$parent->getFinalPrice() ?: (float)$parent->getPrice();
            }
            // if still not price
            if (!$itemPrice) {
                // get product price
                $itemPrice = (float)$item->getProduct()->getPrice();
            }

            // if product isn't virtual and is configurable or downloadable
            if ($item["is_virtual"] == false && !in_array($itemProduct->getTypeId(), array('configurable','downloadable'))) {
                // add line item node
                $lineItem = array();

                $lineItem['SKU'] = $item->getSku();
                $lineItem['CustWeightLbs'] = (float)$item->getWeight();
                $lineItem['ValueUSD'] = $itemPrice;
                $lineItem['CustLengthInches'] = (float)$item->getLength();
                $lineItem['CustWidthInches'] = (float)$item->getWidth();
                $lineItem['CustHeightInches'] = (float)$item->getHeight();
                $lineItem['Quantity'] = (float)$item->getQty();
                $lineItem['ValueShopperCurrency'] = $itemPrice;
                $lineItem['ShopperCurrency'] = Mage::app()->getStore()->getCurrentCurrencyCode();

                $itemsList[] = $lineItem;
            }
        }

        $json['ItemDetailsList'] = $itemsList;

        // if order_reference is set add it to request
        if (Mage::getStoreConfig('carriers/i-parcel/order_reference')) {
            $json['OrderReference'] = $order->getIncrementId();
        }

        // Get discounts
        $orderDiscountAmount = $order->getData('discount_amount');
        $discount = 0;
        if ($orderDiscountAmount != 0) {
            $discount = -1 * $orderDiscountAmount;
        }

        // Get ServiceLevelID
        $method = $order->getShippingMethod();
        /* var $method string */
        $method = explode('_', $method);
        /* var $method array */
        array_shift($method);
        $serviceLevelId = implode('_', $method);
        /* var $serviceLevelId string */

        $json['OtherDiscount'] = $discount;
        $json['OtherDiscountCurrency'] = $order->getOrderCurrencyCode();
        $json['ServiceLevel'] = $serviceLevelId;
        $json['SessionID'] = '';
        $json['key'] = Mage::helper('iparcel')->getGuid();

        $log->setRequest(json_encode($json));

        $response = $this->_restJSON($json, $this->_submitParcel);

        $log->setResponse($response);
        $log->save();

        return json_decode($response);
    }

    /**
     * Cancels shipments via the Web Service API
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return bool
     */
    public function cancelShipment(Mage_Sales_Model_Order_Shipment $shipment)
    {
        $json = array();
        $json['TrackingNumber'] = Mage::helper('iplogistics')->getTrackingNumber($shipment);
        $json['UserNotes'] = '';
        $json['Key'] = Mage::helper('iparcel')->getGuid();

        $response = $this->_restJSON($json, $this->_cancel);

        $log = Mage::getModel('iparcel/log');
        $log->setRequest(json_encode($json));
        $log->setResponse($response);
        $log->setController('Cancel Shipment');
        $log->save();

        $response = json_decode($response);

        if ($response->Code != 1) {
            Mage::throwException(
                'Failed when canceling shipment: ' . $response->Message
            );
        } else {
            Mage::getModel('sales/order_shipment_api')->addComment(
                $shipment->getIncrementId(),
                'Shipment canceled',
                false,
                false
            );
        }

        return true;
    }

    /**
     * Cancels all shipments of an order
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function cancelShipmentsByOrder(Mage_Sales_Model_Order $order)
    {
        if (!Mage::helper('iplogistics')->isIparcelOrder($order)) {
            return true;
        }

        $shipmentsCollection = $order->getShipmentsCollection();

        foreach ($shipmentsCollection as $shipment) {
            $this->cancelShipment($shipment);
        }

        return true;
    }

    /**
     * Split a shipment via web service API
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @param array $itemCollection Items to split into separate shipment
     * @return Object
     */
    public function splitShipment(Mage_Sales_Model_Order_Shipment $shipment, $itemCollection)
    {
        if (count($itemCollection) < 1) {
            Mage::throwException(
                'Failed when splitting shipment: Item count must be greater than 0'
            );
        }

        $json = array();
        $json['TrackingNumber'] = Mage::helper('iplogistics')->getTrackingNumber($shipment);
        $json['SkuList'] = array();
        $json['Key'] = Mage::helper('iparcel')->getGuid();

        $items = array();
        foreach ($itemCollection as $item) {
            $items['Sku'] = $item['sku'];
            $items['Quantity'] = $item['qty'];
            $json['SkuList'][] = $items;
        }

        $response = $this->_restJSON($json, $this->_split);

        $log = Mage::getModel('iparcel/log');
        $log->setController('Split Shipment');
        $log->setRequest(json_encode($json));
        $log->setResponse($response);
        $log->save();

        $response = json_decode($response);

        if (is_array($response) && count($response) == 2) {
            // Delete old shipment, create two new ones.

            $order = $shipment->getOrder();
            $orderItems = $order->getAllItems();

            // Split the order items into two arrays, one for each new shipment
            $firstItems = array();
            $secondItems = array();
            foreach($orderItems as $orderItem) {
                foreach($itemCollection as $item) {
                    if ($orderItem->getSku() == $item['sku']) {
                        $firstItems[$orderItem->getId()] = $item['qty'];
                        $secondItems[$orderItem->getId()] = $orderItem->getQtyOrdered() - $item['qty'];
                    } else {
                        $secondItems[$orderItem->getId()] = $orderItem->getQtyOrdered();
                    }
                }
            }

            // Delete the original shipment
            $shipment->delete();
            $items = $order->getAllVisibleItems();
            foreach($items as $item){
                $item->setQtyShipped(0);
                $item->save();
            }

            Mage::register('iparcel_skip_auto_submit', true);
            // Create a new shipment for each set of items
            $firstShipment = Mage::getModel('sales/order_shipment_api')->create(
                $order->getIncrementId(),
                $firstItems,
                null,
                false,
                false
            );
            Mage::getModel('sales/order_shipment_api')->addTrack(
                $firstShipment,
                $order->getShippingCarrier()->getCarrierCode(),
                'I-Parcel',
                $response[0]
            );

            $secondShipment = Mage::getModel('sales/order_shipment_api')->create(
                $order->getIncrementId(),
                $secondItems,
                null,
                false,
                false
            );
            Mage::getModel('sales/order_shipment_api')->addTrack(
                $secondShipment,
                $order->getShippingCarrier()->getCarrierCode(),
                'I-Parcel',
                $response[1]
            );
            Mage::unregister('iparcel_skip_auto_submit');
        } else {
            Mage::throwException(
                'Failed when splitting shipment.'
            );
        }

        return true;
    }
}
