<?php
/**
 * Adminhtml i-parcel logs controller
 *
 * @category    Iparcel
 * @package     Iparcel_All
 * @author      Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_All_Adminhtml_Iparcel_LogController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Init adminhtml response
     *
     * @return Iparcel_All_Adminhtml_Iparcel_LogController
     */
    protected function _init()
    {
        $this->loadLayout()
            ->_setActiveMenu('iparcel/log')
            ->_title($this->__('Logs'))->_title($this->__('i-parcel'))
            ->_addBreadcrumb($this->__('Logs'), $this->__('Logs'));
        return $this;
    }

    /**
     * Show grid action
     */
    public function indexAction()
    {
        $this->_init()
            ->renderLayout();
    }

    /**
     * Clear log action
     */
    public function clearAction()
    {
        if (Mage::getModel('iparcel/log')->clear() === false) {
            Mage::getSingleton('core/session')->addError($this->__('Unable to write to the log file.'));
        }
        $this->_redirect('*/*/index');
    }
}
