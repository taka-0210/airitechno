<?php
return array(
    'kitchen_config_path' => dirname(__FILE__) . '/public_html/rubs/cmn/config.php',
    'api_keys' => array(
        // hash('sha256', 'replace-with-a-store-api-key') => array('stores' => array('265')),
        // hash('sha256', 'replace-with-a-headquarters-api-key') => array('all_stores' => true),
    ),
    'cache_directory' => dirname(__FILE__) . '/api-cache',
    'source_image_directory' => dirname(__FILE__) . '/public_html/rubs/img',
    'source_image_base_url' => 'https://rise-up.net/rubs/img',
    'thumbnail_base_url' => 'https://rise-up.net/api/v1/thumb.php',
    'video_directory' => dirname(__FILE__) . '/public_html/rubs/img/item_movie',
    'video_base_url' => 'https://rise-up.net/rubs/img/item_movie',
    'legacy_video_directory' => dirname(dirname(__FILE__)) . '/pro-chubo.com/public_html/img_item_movie',
    'max_per_page' => 60,
    'shipped_retention_days' => 30,
    'catalog_database_path' => dirname(__FILE__) . '/api-cache/catalog.sqlite',
);
