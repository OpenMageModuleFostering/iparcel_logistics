<?php
/**
 * Updates default Service Levels and names
 */
$installer = $this;

$installer->startSetup();

$data = array(
    array(
        'service_id' => 112,
        'title' => 'UPS i-Parcel Express'
    ),
    array(
        'service_id' => 115,
        'title' => 'UPS i-Parcel Select'
    ),
    array(
        'service_id' => 119,
        'title' => 'UPS i-Parcel Saver'
    ),
    array(
        'service_id' => 211,
        'title' => 'UPS Standard'
    ),
    array(
        'service_id' => 208,
        'title' => 'UPS Expedited'
    ),
    array(
        'service_id' => 265,
        'title' => 'UPS Saver'
    ),
    array(
        'service_id' => 207,
        'title' => 'UPS Express'
    ),
    array(
        'service_id' => 254,
        'title' => 'UPS Express Plus'
    )
);

Mage::getModel('core/config_data')
    ->setScope('default')
    ->setScopeId(0)
    ->setPath('carriers/iplogistics/name')
    ->setValue(serialize($data))
    ->save();

$installer->endSetup();
