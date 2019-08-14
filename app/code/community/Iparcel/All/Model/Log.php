<?php
/**
 * i-parcel XML log model
 *
 * @category Iparcel
 * @package  Iparcel_All
 * @author   Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_All_Model_Log extends Varien_Object
{
    const LOG_FILENAME = "iparcel.log";
    const MAX_LOG_SIZE = 10;

    protected $_jsonData;

    /**
     * Internal constructor
     */
    protected function _construct()
    {
        $this->setLogFilename(Mage::getBaseDir('log').'/'.self::LOG_FILENAME);
        $this->setJson($this->_read());
    }

    /**
     * Read log file method
     *
     * @return Varien_Data_Collection
     */
    protected function _read()
    {
        $response = new Varien_Data_Collection();

        if (!file_exists($this->getLogFilename())) {
            $this->_jsonData = array();
            return $response;
        }

        $file = fopen($this->getLogFilename(), "r");
        $this->_jsonData = json_decode(fread($file, filesize($this->getLogFilename())), true);
        fclose($file);

        if (!is_array($this->_jsonData)) {
            if (!$this->_clear()) {
                Mage::throwException(Mage::helper('iparcel')->__('Access Forbidden for i-parcel log file'));
            }
            $this->_jsonData = array();
            return $response;
        }

        // for each json log node append Varien Object to Varien Data Collection
        foreach ($this->_jsonData['logs'] as $log) {
            $_log = new Varien_Object(array(
                'timestamp' => $log['Timestamp'],
                'controller' => $log['Controller'],
                'response' => $log['Response'],
                'request' => $log['Request']
            ));
            $response->addItem($_log);
        }
        return $response;
    }

    /**
     * Internal save method
     *
     * @return bool
     */
    protected function _save()
    {
        try {
            $file = fopen($this->getLogFilename(), 'w');
            fwrite($file, json_encode($this->_jsonData));
            fclose($file);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Internal create JSON file method
     *
     * @return bool
     */
    protected function _create()
    {
        $this->_jsonData['created'] = $this->getTimestamp();
        $this->_jsonData['edited'] = $this->getTimestamp();
        $this->_jsonData['logs'] = array();

        $this->_appendLog();
        return $this->_save();
    }

    /**
     * Internal append XML to file method
     *
     * @return bool
     */
    protected function _append()
    {
        $this->_jsonData['edited'] = $this->getTimestamp();

        $this->_appendLog();
        return $this->_save();
    }

    /**
     * Internal clear XML file method
     *
     * @return bool
     */
    protected function _clear()
    {
        return unlink($this->getLogFilename());
    }

    /**
     * Internal append log nodes to SimpleXMLExtended object
     */
    protected function _appendLog()
    {
        $log = array();
        $log['Timestamp'] = $this->getTimestamp();
        $log['Controller'] = $this->getController();
        $log['Request'] = $this->getRequest();
        $log['Response'] = $this->getResponse();

        $this->_jsonData['logs'][] = $log;
        if (self::MAX_LOG_SIZE < count($this->_jsonData['logs'])) {
            array_shift($this->_jsonData['logs']);
        }
    }

    /**
     * Save log file method
     *
     * @return bool
     */
    public function save()
    {
        $this->setTimestamp(date('d/m/Y h:i:sA T'));
        $_request = $this->getRequest();
        $_response = $this->getResponse();
        $_controller = $this->getController();
        if (!($_request !== null && $_response !== null && $_controller)) {
            Mage::throwException(Mage::helper('iparcel')->__('Log data is not filled'));
        }
        // append if file exists, if not create new
        return file_exists($this->getLogFilename()) ? $this->_append() : $this->_create();
    }

    /**
     * Clear log file method
     *
     * @return bool
     */
    public function clear()
    {
        return $this->_clear();
    }
}
