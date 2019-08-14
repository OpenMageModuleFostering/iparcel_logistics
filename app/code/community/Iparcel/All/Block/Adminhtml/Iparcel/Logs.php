<?php
/**
 * Adminhtml i-parcel logs grid container block
 *
 * @category   Iparcel
 * @package    Iparcel_Shipping
 * @author     Bobby Burden <bburden@i-parcel.com>
 */
class Iparcel_All_Block_Adminhtml_Iparcel_Logs extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Initialize factory instance
     */
    public function __construct()
    {
        $this->_blockGroup = 'iparcel';
        $this->_controller = 'adminhtml_iparcel_logs';
        $this->_headerText = $this->__('i-parcel Logs');
        parent::__construct();
    }

    /**
     * Preparing child blocks for each added button, removing add button, adding clear button
     *
     * @return Iparcel_All_Block_Adminhtml_Logs
     */
    protected function _prepareLayout()
    {
        $this->_removeButton('add');

        $this->_addButton('clear', array(
            'label' => $this->__('Clear'),
            'onclick' => 'setLocation(\''.$this->getUrl('*/*/clear').'\')'
        ));

        return parent::_prepareLayout();
    }
}
