<?php
return array(
    'api_keys' => array(
        // hash('sha256', 'replace-with-a-store-api-key') => array('stores' => array('265')),
        // hash('sha256', 'replace-with-a-headquarters-api-key') => array('all_stores' => true),
    ),
    'cache_directory' => dirname(__FILE__) . '/api-cache',
    'image_base_url' => 'https://pro-chubo.com/img_item',
    'thumbnail_base_url' => 'https://pro-chubo.com/api/v1/thumb.php',
    'max_per_page' => 60,
);
