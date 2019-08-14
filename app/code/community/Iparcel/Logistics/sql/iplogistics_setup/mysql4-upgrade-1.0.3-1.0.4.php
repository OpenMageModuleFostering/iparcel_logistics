<?php

$installer = $this;

// Create a table to store tax & duty calculations

$installer->run("
ALTER TABLE {$this->getTable('iplogistics/api_quote')}
    MODIFY `service_levels` TEXT
");

$installer->endSetup();
