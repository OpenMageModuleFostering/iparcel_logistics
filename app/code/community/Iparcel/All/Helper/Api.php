<?php
/**
 * I-parcel shared API helper
 *
 * @category    Iparcel
 * @package     Iparcel_All
 * @authoer     Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_All_Helper_Api
{
    /** @var string URL for the SubmitCatalog endpoint */
    protected $_submitCatalog = 'https://webservices.i-parcel.com/api/SubmitCatalog';

    /**
     * Send POST requests to the REST API
     *
     * @param string $post POST Data to send
     * @param string $url REST API URL to send POST data to
     * @param array $header Array of headers to attach to the request
     * @return string Response from the POST request
     */
    protected function _rest($post, $url, array $header)
    {
        $curl = curl_init($url);

        $timeout = 15;
        if ($timeout) {
            curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        }

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POSTFIELDS, "$post");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    /**
     * Send REST XML requests
     *
     * Wrapper for _rest() that sends a SimpleXMLElement object to the API.
     *
     * @param SimpleXMLElement $xml XML to send
     * @param string $url REST API URL to send POST data to
     * @return string Response from the POST request
     */
    protected function _restXML($xml, $url)
    {
        return $this->_rest($xml->asXml, $url, array('Content-Type: text/xml'));
    }

    /**
     * Send REST JSON requests
     *
     * Wrapper for _rest() that sends the passed data as JSON to the API.
     *
     * @param string $json Data to be JSON encoded and sent to the API
     * @param string $url REST API URL to send POST data to
     * @return string Response from the POST request
     */
    protected function _restJSON($json, $url)
    {
        return $this->_rest(
            json_encode($json),
            $url,
            array('Content-Type: text/json')
        );
    }

    /**
     * Send SubmitCatalog request
     *
     * Takes the passed Product Collection and transforms it into data that can
     * be passed to the API. It then submits the request as JSON to the
     * SubmitCatalog API endpoint.
     *
     * @param Varien_Data_Collection $productCollection A Magento Product collection
     * @return int The amount of products uploaded to the API
     */
    public function submitCatalog(Varien_Data_Collection $productCollection)
    {
        // init log
        /** @var Iparcel_All_Model_Log $log */
        $log = Mage::getModel('iparcel/log');
        $log->setController('Submit Catalog');

        $items = $this->prepareProductsForSubmitCatalog($productCollection);

        $log->setRequest(json_encode($items));

        $numberToUpload = count($items['SKUs']);

        if ($numberToUpload > 0) {
            $response = $this->_restJSON($items, $this->_submitCatalog);

            $log->setResponse($response);
            $log->save();

            if (!preg_match('/.*Success.*/', $response)) {
                $numberToUpload = -1;
            }
        }

        return $numberToUpload;
    }

    /**
     * Prepare product collection for SubmitCatalog calls
     *
     * This function takes a Magento product collection and extracts the
     * necessary information to send it to the SubmitCatalog API endpoint.
     *
     * @param Varien_Data_Collection $products Products to prepare
     * @return array Prepared array of products and product information
     */
    public function prepareProductsForSubmitCatalog(Varien_Data_Collection $products)
    {
        /** @var Mage_Eav_Model_Entity_Attribute $hsCode */
        $hsCode = Mage::getModel('eav/entity_attribute')->load(Mage::getStoreConfig('catalog_mapping/attributes/hscodeus'));
        /** @var Mage_Eav_Model_Entity_Attribute $shipAlone */
        $shipAlone = Mage::getModel('eav/entity_attribute')->load(Mage::getStoreConfig('catalog_mapping/attributes/shipalone'));

        $items = array();
        $items['key'] = Mage::helper('iparcel')->getGuid();

        $skus = $items['SKUs'] = array();

        foreach ($products as $product) {
            /** @var Mage_Catalog_Model_Product $product */
            $product = Mage::getModel('catalog/product')->load($product->getId());

            $sku = $product->getSku() ?: '';
            $name = $product->getName() ?: '';
            if (empty($sku) || empty($name)) {
                continue;
            }

            $item = array();

            $item['SKU'] = $sku;
            $item['ProductName'] = $name;

            for ($i = 1; $i <= 6; $i++) {
                $_attribute = Mage::getModel('eav/entity_attribute')
                    ->load(Mage::getStoreConfig(sprintf('catalog_mapping/attributes/attribute%d', $i)));

                // if attribute exists
                $productAttribute = null;
                if ($_attribute !== null) {
                    // and has attribute_code
                    if ($code = $_attribute->getAttributeCode()) {
                        // then productAttribute value is product's attribute_text (if exists) or product's data (if not)
                        $productAttribute = strip_tags($product->getAttributeText($code) ?: $product->getData($code));
                    }
                }
                $item["Attribute$i"] = (string)substr($productAttribute, 0, 255);
            }

            $price = null;
            // if it's simple product and config is to get parent's price
            if ($product->getTypeId() == 'simple' && Mage::getStoreConfig('catalog_mapping/attributes/price') == Iparcel_All_Model_System_Config_Source_Catalog_Mapping_Configurable_Price::CONFIGURABLE) {
                // get parentIds
                $parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId()) ?: Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
                // get price
                $price = $parentIds ? Mage::getModel('catalog/product')->load($parentIds[0])->getPrice() : $product->getPrice();
            }
            // if there's no price
            if (!$price) {
                //get current product's price
                $price = $product->getPrice();
            }

            $item['CountryOfOrigin'] = (string)$product->getCountryOfManufacture();
            $item['CurrentPrice'] = (float)$price;
            $item['Delete'] = $product->getIsDeleted() ? true : false;
            $item['HSCodeCA'] = '';

            if ($code = $hsCode->getAttributeCode()) {
                $item['HSCodeUS'] = trim($product->getAttributeText($code)) ?: $product->getData($code);
            } else {
                $item['HSCodeUS'] = '';
            }

            $item['Height'] = (float)$product->getHeight();
            $item['Length'] = (float)$product->getLength();
            $item['ProductURL'] = $product->getUrlPath();
            $item['SKN'] = '';
            if ($code = $shipAlone->getAttributeCode()) {
                $item['ShipAlone'] = $product->getAttributeText($code) == 'Yes' ? true : false;
            }
            $item['Width'] = (float)$product->getWidth();
            $item['Weight'] = (float)$product->getWeight();

            // Detect and handle a Simple Product with Custom Options
            if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE && $product->getHasOptions()) {
                // loop through each of the sorted products with custom options
                // and build out custom option and option type based skus
                foreach ($this->_findSimpleProductVariants($product) as $customOptionProduct) {
                    $customOptionSku = "";
                    $customOptionPriceMarkup = 0;
                    $customOptionName = "";
                    foreach ($customOptionProduct as $o) {
                        $customOptionSku .= "_" . $o["sku"];
                        $customOptionPriceMarkup += $o["price"];
                        $customOptionName .= " - " . $o["title"];
                    }
                    if ($customOptionSku != '') {
                        $item['SKU'] = $sku . "_" . $customOptionSku;
                    } else {
                        $item['SKU'] = $sku;
                    }
                    $item['CurrentPrice'] = (float)( $price + $customOptionPriceMarkup );
                    //append the product name with the custom option
                    $item['ProductName'] = $name . $customOptionName;
                    $items['SKUs'][] = $item;
                }
            } else {
                $items['SKUs'][] = $item;
            }

        }

        return $items;
    }

    /**
     * Performs the work of handling Simple Products with Custom Options
     *
     * @param Mage_Catalog_Model_Product $product Product with Options
     * @return array Options array with all product variations
     */
    private function _findSimpleProductVariants($product)
    {
        // get product options collection object
        $options = Mage::getModel('catalog/product_option')
            ->getCollection()
            ->addProductToFilter($product->getId())
            ->addPriceToResult(0)
            ->addValuesToResult();

        // array indeces for products and product options
        $optCount = 0;
        $optTypeCount = 0;

        // arrays for sorted products, sorting key/value lookups
        $sorted = array();
        $productOptions = array();
        $productSorting = array();

        foreach ($options as $option) {
            // array for products custom options
            $customOptions = array();
            $optSortOrder = $option["sort_order"];
            $optSortKey = 0;
            /**
             * If this option has no values (text field, text area, etc.) just
             * build a sku for the option ID, otherwise, build out each value as
             * well.
             */
            if (count($option->getValues()) == 0) {
                $productOption = $product->getOptions();
                $productOption = $productOption[$option->getId()];
                $customOption = array();
                $customOption["sku"] = $option->getId();
                $customOption["price"] = $option->getPrice();
                $customOption["title"] = $productOption->getTitle();;
                $customOption["sort_order"] = $option->getSortOrder();
                if (get_class($option) == 'MageWorx_CustomOptions_Model_Catalog_Product_Option') {
                    $customOption['required'] = $option->getIsRequire(true);
                } else {
                    $customOption['required'] = $option->getIsRequire();
                }
                $customOptions[$optTypeCount] = $customOption;
                $optTypeCount++;
            } else {
                foreach ($option->getValues() as $values) {
                    $customOption = array();
                    // create the sku portion with custom option id and option type id.. 1_2 -> Size_Large
                    $customOption["sku"] = $values["option_id"] . "-" . $values["option_type_id"];
                    $customOption["price"] = $values["price"];
                    $customOption["title"] = $values["title"];
                    $customOption["sort_order"] = $values["sort_order"];
                    /**
                     * Add `required` bit. Used later to build SKU variations.
                     * If this store uses Mageworx_CustomOptions, act as if we are
                     * on a product page
                     */
                    if (get_class($option) == 'MageWorx_CustomOptions_Model_Catalog_Product_Option') {
                        $customOption['required'] = $option->getIsRequire(true);
                    } else {
                        $customOption['required'] = $option->getIsRequire();
                    }
                    // add custom option type to collection
                    $customOptions[$optTypeCount] = $customOption;
                    $optTypeCount++;
                }
            }
            // maintain index to sort order relationship
            $productSorting[$optCount] = $optSortOrder;
            // add custom option type collection to options collections
            $customOptionProducts[$optCount] = $customOptions;
            $optCount++;
        }
        // sort by array sort_order value while maintaining array index
        asort($productSorting);
        $sortCount = 0;

        // iterate sorted indeces and build sorted array of products
        foreach ($productSorting as $k => $v) {
            $sorted[$sortCount] = $customOptionProducts[$k];
            $sortCount++;
        }

        return $this->_findVariations($sorted);
    }

    /**
     * Given an array of options, returns all of the valid option variations
     *
     * @param array $options Array of option arrays (sku, price, title, sort_order, required)
     * @return array Array of arrays, representing the possible option variations
     */
    private function _findVariations($options)
    {
        // filter out empty values
        $options = array_filter($options);
        $result = array(array());
        $optionalProductOptions = array();
        $requiredOptions = true;

        // Remove the optional product options
        foreach ($options as $key => $option) {
            foreach ($option as $product) {
                if ($product['required'] == false) {
                    $optionalProductOptions[$key][$product['sku']] = $product;
                    unset($options[$key]);
                }
            }
        }

        // Add all variations of the required options to the $result array
        foreach ($options as $key => $values) {
            $append = array();
            foreach ($result as $product) {
                foreach ($values as $item) {
                    $product[$key] = $item;
                    $append[] = $product;
                }
            }
            $result = $append;
        }

        // Check for any required options
        if (count($result[0]) != 0 || count($result) == 1) {
            $requiredOptions = false;
        }

        // Add a new product to the $result array for each variation of optional
        // product options + the existing required options.
        foreach ($result as $productConfiguration) {
            foreach ($optionalProductOptions as $option) {
                foreach ($option as $product) {
                    $newVariation = $productConfiguration;
                    $newVariation[] = $product;
                    $result[] = $newVariation;
                }
            }
        }

        // If there were no required options, add all variations of optional
        // product options
        if ($requiredOptions == false && count($optionalProductOptions) > 1) {
            $allOptionals = array();
            foreach ($optionalProductOptions as $key => $option) {
                $allOptionals[] = array_shift($option);
            }
            $result[] = $allOptionals;
        }

        return $result;
    }

    /**
     * Set the URLs for API Calls.
     *
     * Useful for setting the API endpoint URLs to controlled URLs for testing.
     *
     * @param array $urls Array of URLs to set as [var_name] => 'value'
     * @return boolean True on success
     */
    public function setUrls($urls)
    {
        try {
            foreach ($urls as $name => $url) {
                $this->{$name} = $url;
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            return false;
        }

        return true;
    }
}
