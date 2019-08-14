<?php
/**
 * i-parcel model for shipping tax
 *
 * @category    Iparcel
 * @package     Iparcel_Logistics
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_Logistics_Model_Quote_Address_Total_Tax extends Iparcel_Logistics_Model_Quote_Address_Total_Abstract
{
    public function __construct()
    {
        $this->setCode('iparcel_tax');
    }

    /**
     * Used each time collectTotals is invoked to find the duty rate
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Iparcel_Logistics_Model_Total_Tax
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        if ($address->getAddressType() != 'shipping') {
            return;
        }

        $tax = 0;
        if ($this->isIparcelShipping($address) &&
            (Mage::helper('iplogistics')->getDisplayTaxAndDutyCumulatively()
            || Mage::helper('iplogistics')->getDisplayTaxAndDutySeparately())) {
            parent::collect($address);

            $dutyAndTaxes = Mage::registry('iparcel_duty_and_taxes');
            $tax = null;

            $quote = Mage::getModel('iplogistics/api_quote');
            $quote->loadByQuoteId($address->getQuote()->getId());
            if (is_array($dutyAndTaxes)) {
                /**
                 * To make the tax and duty information persist through to the final
                 * collectTotals, we must store the information from the registry.
                 */
                $quote->setQuoteId($address->getQuote()->getId());
                $quote->setParcelId($dutyAndTaxes['parcel_id']);
                $quote->setServiceLevels($dutyAndTaxes['service_levels']);
                $quote->save();
            } else {
                /**
                 * If the registry is empty, then we are collecting totals while
                 * converting the quote to an order
                 */
                if ($quote->getId()) {
                    $dutyAndTaxes = array(
                        'parcel_id' => $quote->getParcelId(),
                        'service_levels' => $quote->getServiceLevels()
                    );
                    /**
                     * Catch an error caused by unserialize returning `false`
                     * if the data stored in the databse is invalid
                     */
                    if ($dutyAndTaxes['service_levels'] == false) {
                        Mage::throwException('Failed to set shipping rates tax and duty.');
                    }
                    Mage::register('iparcel_duty_and_taxes', $dutyAndTaxes);
                }
            }

            $tax = $dutyAndTaxes['service_levels'][$address->getShippingMethod()]['tax'];

            $this->_setBaseAmount($tax);
            $this->_setAmount(
                $address->getQuote()->getStore()->convertPrice($tax, false)
            );

            /**
             * If this is an i-parcel shipped order, we have to remove Magento's
             * calculated tax, to allow i-parcel's to be the only tax calculated
             */
            $address->setTaxAmount(0);
            $address->setBaseTaxAmount(0);

            return $this;
        }
    }

    /**
     * Display the duty rate
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Iparcel_Logistics_Model_Total_Tax
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        if ($address->getAddressType() != 'shipping') {
            return;
        }

        /**
         * We only display the tax if the duty and tax are displayed seperately.
         * Otherwise, the Duty model will handle displaying the combined total.
         */
        if ($this->isIparcelShipping($address)
            && Mage::helper('iplogistics')->getDisplayTaxAndDutySeparately()
            && $address->getIparcelTaxAmount() != null) {
            $address->addTotal(array(
                'code' => $this->getCode(),
                'title' => Mage::helper('iplogistics')->getTaxLabel(),
                'value' => $address->getIparcelTaxAmount()
            ));
        }
    }
}
