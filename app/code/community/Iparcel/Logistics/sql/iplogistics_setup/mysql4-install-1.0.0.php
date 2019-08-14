<?php
/**
 * Adds default Service Levels to "Methods Names" section of the i-parcel Shipping Method
 */
$installer = $this;

$installer->startSetup();

$data = array(
    array(
        'service_id' => 112,
        'title' => 'i-parcel UPS Express'
    ),
    array(
        'service_id' => 115,
        'title' => 'i-parcel UPS Select'
    )
);
Mage::getModel('core/config_data')
    ->setScope('default')
    ->setScopeId(0)
    ->setPath('carriers/i-parcel/name')
    ->setValue(serialize($data))
    ->save();

$installer->endSetup();
