<?php

define('RISE_UP_PUBLIC_ROOT', dirname(dirname(dirname(__DIR__))));
define('RISE_UP_DOMAIN_ROOT', dirname(RISE_UP_PUBLIC_ROOT));

$apiConfigPath = RISE_UP_DOMAIN_ROOT . '/api-config.php';
if (!is_file($apiConfigPath)) {
    api_error(503, 'API is not configured.');
}
$apiConfig = require $apiConfigPath;
if (!is_array($apiConfig)) {
    api_error(503, 'API configuration is invalid.');
}

function api_config($key, $default)
{
    global $apiConfig;
    return isset($apiConfig[$key]) ? $apiConfig[$key] : $default;
}

function api_db()
{
    static $db = null;
    if ($db === null) {
        $path = api_config('catalog_database_path', RISE_UP_DOMAIN_ROOT . '/api-cache/catalog.sqlite');
        if (!is_file($path)) {
            api_error(503, 'Catalog snapshot is not available.');
        }
        $db = new PDO('sqlite:' . $path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $db;
}

function api_headers()
{
    $headers = function_exists('getallheaders') ? getallheaders() : array();
    if (!is_array($headers)) {
        $headers = array();
    }
    $normalised = array();
    foreach ($headers as $name => $value) {
        $normalised[strtolower($name)] = $value;
    }
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $normalised['authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $normalised['x-api-key'] = $_SERVER['HTTP_X_API_KEY'];
    }
    return $normalised;
}

function api_secure_equals($known, $given)
{
    if (!is_string($known) || !is_string($given) || strlen($known) !== strlen($given)) {
        return false;
    }
    $difference = 0;
    for ($index = 0, $length = strlen($known); $index < $length; $index++) {
        $difference |= ord($known[$index]) ^ ord($given[$index]);
    }
    return $difference === 0;
}

function api_authenticate()
{
    $headers = api_headers();
    $key = isset($headers['x-api-key']) ? trim($headers['x-api-key']) : '';
    if ($key === '' && isset($headers['authorization']) && preg_match('/^Bearer\s+(.+)$/i', trim($headers['authorization']), $matches)) {
        $key = trim($matches[1]);
    }
    if ($key === '') {
        api_error(401, 'API key is required.');
    }

    $givenHash = hash('sha256', $key);
    foreach ((array) api_config('api_keys', array()) as $knownHash => $permissions) {
        if (api_secure_equals(strtolower($knownHash), strtolower($givenHash))) {
            return is_array($permissions) ? $permissions : array();
        }
    }
    api_error(401, 'API key is invalid.');
}

function api_authorise_store($permissions, $storeId)
{
    $stores = isset($permissions['stores']) ? array_map('strval', (array) $permissions['stores']) : array();
    if (!in_array((string) $storeId, $stores, true)) {
        api_error(403, 'This API key cannot access the requested store.');
    }
}

function api_authorise_catalog($permissions)
{
    if (empty($permissions['all_stores'])) {
        api_error(403, 'This API key cannot access the full catalog.');
    }
}

function api_utf8($value)
{
    if (is_array($value)) {
        $converted = array();
        foreach ($value as $key => $item) {
            $converted[$key] = api_utf8($item);
        }
        return $converted;
    }
    if (!is_string($value) || $value === '') {
        return $value;
    }
    return mb_convert_encoding($value, 'UTF-8', 'EUC-JP');
}

function api_euc($value)
{
    return mb_convert_encoding((string) $value, 'EUC-JP', 'UTF-8');
}

function api_json($status, $payload, $cacheSeconds)
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        api_error(500, 'JSON encoding failed.');
    }
    $etag = '"' . sha1($json) . '"';
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        header('ETag: ' . $etag);
        exit;
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: private, max-age=' . max(0, (int) $cacheSeconds));
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit;
}

function api_error($status, $message)
{
    api_json($status, array('error' => array('status' => $status, 'message' => $message)), 0);
}

function api_status_code($status)
{
    if ($status === '販売中') {
        return 'available';
    }
    if ($status === '商談中') {
        return 'negotiating';
    }
    if ($status === '売約済み' || $status === '売約済') {
        return 'reserved';
    }
    if ($status === '出荷済み' || $status === '販売済み') {
        return 'shipped';
    }
    return 'unavailable';
}

function api_hyper_status_code($statusNo)
{
    if ((int)$statusNo === 1) return 'available';
    if ((int)$statusNo === 2) return 'shipped';
    if ((int)$statusNo === 4) return 'negotiating';
    if ((int)$statusNo === 5 || (int)$statusNo === 7) return 'reserved';
    return 'unavailable';
}

function api_hyper_product_from()
{
    return 'FROM tblItemData d INNER JOIN mstItemModel m ON d.item_model_no=m.item_model_no INNER JOIN mstItemClass1 c1 ON m.class1_no=c1.class1_no INNER JOIN mstItemClass2 c2 ON m.class2_no=c2.class2_no LEFT JOIN mstShop s ON d.shop_id=s.shop_id LEFT JOIN mstItemRank r ON d.rank_no=r.rank_no LEFT JOIN mstItemStatus st ON d.status_no=st.status_no';
}

function api_hyper_product_select()
{
    return 'SELECT d.item_data_no,d.jancode,d.shop_id,d.status_no,d.rank_no,d.year,d.size_w,d.size_d,d.size_h,d.price_sales,d.flag_ask_price,d.comment,d.date_ship,d.datetime_stock,d.count_photo,d.cmn_photo_no,m.name,m.supple,m.maker,m.model,m.details,m.movie_url,c1.class1_no,c1.class1_name,c2.class2_no,c2.class2_name,s.shop_name,r.rank_name,st.status_name ' . api_hyper_product_from();
}

function api_hyper_public_where($storeId, &$parameters)
{
    $days=max(0,(int)api_config('shipped_retention_days',30));
    $where='d.status_inside_no=1 AND d.status_no<>7 AND (d.status_no<>2 OR (d.date_ship IS NOT NULL AND d.date_ship>=DATE_SUB(CURDATE(), INTERVAL '.$days.' DAY)))';
    if($storeId!==null){$where.=' AND d.shop_id=:store_id';$parameters[':store_id']=(string)$storeId;}
    return $where;
}

function api_hyper_images($row, $includeAll)
{
    $count=max(0,(int)$row['count_photo']); if($count===0)return array(); if(!$includeAll)$count=1;
    $jancode=(string)$row['jancode']; $common=(int)$row['cmn_photo_no']; $type=$common>0?'item_cmn':'item'; $base=rtrim(api_config('source_image_base_url','https://rise-up.net/rubs/img'),'/').'/'.$type; $directory=rtrim(api_config('source_image_directory',RISE_UP_PUBLIC_ROOT.'/rubs/img'),'/').'/'.$type; $key=$common>0?(string)$common:$jancode; $thumb=rtrim(api_config('thumbnail_base_url',''),'/'); $images=array();
    for($no=0;$no<$count;$no++){
        $matches=glob($directory.'/'.$key.'-'.$no.'.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}',GLOB_BRACE);
        if(!is_array($matches)||!isset($matches[0])||!is_file($matches[0]))continue;
        $images[]=array('url'=>$base.'/'.rawurlencode(basename($matches[0])),'thumbnail_url'=>$thumb.'?id='.rawurlencode($jancode).'&no='.$no.'&size=480'.($common>0?'&common='.$common:''),'sort_order'=>$no);
    }
    return $images;
}

function api_hyper_videos($row)
{
    $configured = trim(isset($row['movie_url']) ? (string) $row['movie_url'] : '');
    if ($configured !== '') {
        return array(array('url' => $configured));
    }
    $jancode = isset($row['jancode']) ? (string) $row['jancode'] : (isset($row['id']) ? (string) $row['id'] : '');
    if (!preg_match('/^[0-9]{13}$/', $jancode)) {
        return array();
    }
    $directory = rtrim(api_config('video_directory', RISE_UP_PUBLIC_ROOT . '/rubs/img/item_movie'), '/');
    $base = rtrim(api_config('video_base_url', 'https://rise-up.net/rubs/img/item_movie'), '/');
    return is_file($directory . '/' . $jancode . '.mp4') ? array(array('url' => $base . '/' . $jancode . '.mp4')) : array();
}

function api_warranty_period_label($value)
{
    $value=trim((string)$value);if($value===''||$value==='0'||$value==='---')return '';
    $labels=array('1'=>'初期動作','2'=>'2週間','3'=>'1ヶ月','4'=>'2ヶ月','5'=>'3ヶ月','6'=>'4ヶ月','7'=>'5ヶ月','8'=>'6ヶ月','initial'=>'初期動作','2_weeks'=>'2週間','1_month'=>'1ヶ月','2_months'=>'2ヶ月','3_months'=>'3ヶ月','4_months'=>'4ヶ月','5_months'=>'5ヶ月','6_months'=>'6ヶ月');
    return isset($labels[$value])?$labels[$value]:$value;
}

function api_warranty($row)
{
    $enabled=array_key_exists('warranty_enabled',$row)&&$row['warranty_enabled']!==null?(bool)$row['warranty_enabled']:null;
    $special=api_warranty_period_label(isset($row['warranty_special_period'])?$row['warranty_special_period']:'');
    $label='';
    if($enabled===false)$label='初期動作';
    elseif($enabled===true&&$special!=='')$label=$special;
    elseif($enabled===true){$rank=trim(isset($row['rank_name'])?(string)$row['rank_name']:'');$defaults=array('新品'=>'12ヶ月','新品B級'=>'12ヶ月','未使用品'=>'12ヶ月','新品現品'=>'12ヶ月','特A'=>'6ヶ月','A'=>'6ヶ月','B'=>'6ヶ月','C'=>'3ヶ月','D'=>'10日');if(isset($defaults[$rank]))$label=$defaults[$rank];}
    return array('enabled'=>$enabled,'special_period'=>$special,'label'=>$label);
}

function api_normalise_snapshot_product($row, $includeDescription)
{
    $price=(int)$row['price_sales'];$status=api_hyper_status_code($row['status_no']);$name=trim((string)$row['name']);if(trim((string)$row['supple'])!=='')$name.=' '.trim((string)$row['supple']);
    $product=array('id'=>(string)$row['id'],'name'=>$name,'manufacturer'=>trim((string)$row['maker']),'model_number'=>trim((string)$row['model']),'year'=>(int)$row['year'],'condition'=>array('code'=>(string)$row['rank_no'],'label'=>trim((string)$row['rank_name'])),'warranty'=>api_warranty($row),'dimensions_mm'=>array('width'=>(int)$row['size_w'],'depth'=>(int)$row['size_d'],'height'=>(int)$row['size_h']),'price'=>array('tax_excluded'=>(int)round($price/1.1),'tax_included'=>$price,'currency'=>'JPY','ask'=>!empty($row['flag_ask_price'])),'inventory'=>array('status'=>$status,'status_label'=>trim((string)$row['status_label']),'store_id'=>(string)$row['store_id'],'store_name'=>trim((string)$row['store_name']),'quantity'=>1,'shipped_at'=>trim((string)$row['date_ship'])),'category'=>array('class1'=>trim((string)$row['class1_name']),'class1_id'=>(int)$row['class1_id'],'class2_id'=>(int)$row['class2_id'],'class2'=>trim((string)$row['class2_name'])),'images'=>api_hyper_images(array('jancode'=>$row['id'],'count_photo'=>$row['count_photo'],'cmn_photo_no'=>$row['cmn_photo_no']),$includeDescription),'videos'=>api_hyper_videos($row),'listed_at'=>trim((string)$row['datetime_stock']));
    if($includeDescription){$specifications=json_decode(isset($row['specifications_json'])?(string)$row['specifications_json']:'[]',true);$product['description']=trim((string)$row['details']);$product['remarks']=trim((string)$row['comment']);$product['list_price']=array('tax_excluded'=>(int)$row['price_fixed'],'tax_included'=>(int)round((int)$row['price_fixed']*1.1),'open_price'=>!empty($row['flag_openprice']));$product['specifications']=is_array($specifications)?$specifications:array();}return $product;
}

function api_normalise_hyper_product($row, $includeDescription)
{
    $price=(int)$row['price_sales']; $status=api_hyper_status_code($row['status_no']); $name=trim((string)$row['name']); if(trim((string)$row['supple'])!=='')$name.=' '.trim((string)$row['supple']);
    $product=array('id'=>(string)$row['jancode'],'name'=>$name,'manufacturer'=>trim((string)$row['maker']),'model_number'=>trim((string)$row['model']),'year'=>(int)$row['year'],'condition'=>array('code'=>(string)$row['rank_no'],'label'=>trim((string)$row['rank_name'])),'dimensions_mm'=>array('width'=>(int)$row['size_w'],'depth'=>(int)$row['size_d'],'height'=>(int)$row['size_h']),'price'=>array('tax_excluded'=>(int)round($price/1.1),'tax_included'=>$price,'currency'=>'JPY','ask'=>!empty($row['flag_ask_price'])),'inventory'=>array('status'=>$status,'status_label'=>trim((string)$row['status_name']),'store_id'=>(string)$row['shop_id'],'store_name'=>trim(strip_tags((string)$row['shop_name'])),'quantity'=>1,'shipped_at'=>trim((string)$row['date_ship'])),'category'=>array('class1'=>trim((string)$row['class1_name']),'class1_id'=>(int)$row['class1_no'],'class2_id'=>(int)$row['class2_no'],'class2'=>trim((string)$row['class2_name'])),'images'=>api_hyper_images($row,$includeDescription),'videos'=>api_hyper_videos($row),'listed_at'=>trim((string)$row['datetime_stock']));
    if($includeDescription){$product['description']=trim((string)$row['comment']);$product['details']=trim((string)$row['details']);}
    return $product;
}

function api_product_images($jancode)
{
    if (!preg_match('/^[0-9]{13}$/', $jancode)) {
        return array();
    }
    $sourceDirectory = rtrim(api_config('source_image_directory', RISE_UP_PUBLIC_ROOT . '/rubs/img'), '/') . '/item';
    $files = glob($sourceDirectory . '/' . $jancode . '-*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
    if (!is_array($files)) {
        return array();
    }
    natsort($files);
    $images = array();
    $thumbnailBase = rtrim(api_config('thumbnail_base_url', ''), '/');
    $imageBase = rtrim(api_config('source_image_base_url', 'https://rise-up.net/rubs/img'), '/') . '/item';
    foreach (array_values($files) as $file) {
        $filename = basename($file);
        if (!preg_match('/^' . preg_quote($jancode, '/') . '-([0-9]+)\.(jpe?g|png|webp)$/i', $filename, $matches)) {
            continue;
        }
        $number = (int) $matches[1];
        $images[] = array(
            'url' => $imageBase . '/' . rawurlencode($filename),
            'thumbnail_url' => $thumbnailBase . '?id=' . rawurlencode($jancode) . '&no=' . $number . '&size=480',
            'sort_order' => $number,
        );
    }
    return $images;
}

function api_product_primary_image($jancode)
{
    if (!preg_match('/^[0-9]{13}$/', $jancode)) {
        return array();
    }
    $imageBase = rtrim(api_config('source_image_base_url', 'https://rise-up.net/rubs/img'), '/') . '/item';
    $thumbnailBase = rtrim(api_config('thumbnail_base_url', ''), '/');
    return array(array(
        'url' => $imageBase . '/' . rawurlencode($jancode . '-0.jpg'),
        'thumbnail_url' => $thumbnailBase . '?id=' . rawurlencode($jancode) . '&no=0&size=480',
        'sort_order' => 0,
    ));
}

function api_normalise_product($row, $includeDescription)
{
    $row = api_utf8($row);
    $jancode = (string) $row['jan13'];
    $status = (string) $row['status'];
    $product = array(
        'id' => $jancode,
        'name' => trim((string) $row['shohin']),
        'manufacturer' => trim((string) $row['maker']),
        'model_number' => trim((string) $row['model']),
        'year' => (int) $row['year'],
        'condition' => array('code' => trim((string) $row['rank']), 'label' => trim((string) $row['rank']) === '' ? '' : 'ランク' . trim((string) $row['rank'])),
        'dimensions_mm' => array('width' => (int) $row['w'], 'depth' => (int) $row['d'], 'height' => (int) $row['h']),
        'price' => array(
            'tax_excluded' => (int) $row['price2_notax'],
            'tax_included' => (int) $row['price2'],
            'currency' => 'JPY',
            'ask' => !empty($row['ask']),
        ),
        'inventory' => array(
            'status' => api_status_code($status),
            'status_label' => $status,
            'store_id' => (string) $row['shop_id'],
            'store_name' => api_store_name((string) $row['shop_id']),
            'quantity' => (int) $row['stock'],
            'shipped_at' => trim((string) $row['date_out']),
        ),
        'category' => array(
            'class1' => trim((string) $row['class1']),
            'class2_id' => (int) $row['class2_id'],
            'class2' => trim((string) $row['class2']),
        ),
        'images' => $includeDescription ? api_product_images($jancode) : api_product_primary_image($jancode),
        'videos' => array(),
        'listed_at' => trim((string) $row['date']),
    );
    if ($includeDescription) {
        $description = trim((string) $row['tokucho']);
        $comment = trim((string) $row['comment']);
        $product['description'] = $description;
        $product['remarks'] = $comment;
        $product['warranty'] = trim((string) $row['hosyo']);
    }
    return $product;
}

function api_store_name($storeId)
{
    static $stores = null;
    if ($stores === null) {
        $stores = array();
        $statement = api_db()->query('SELECT shop_id, shop FROM shop_tb WHERE status = 1');
        foreach ($statement->fetchAll() as $row) {
            $row = api_utf8($row);
            $stores[(string) $row['shop_id']] = trim((string) $row['shop']);
        }
    }
    return isset($stores[(string) $storeId]) ? $stores[(string) $storeId] : '店舗ID ' . $storeId;
}

function api_public_where($storeId, &$parameters)
{
    $parameters[':store_id'] = (string) $storeId;
    $parameters[':public_status'] = api_euc('公開在庫');
    return 'shop_id = :store_id AND status2 = :public_status AND COALESCE(flag_close, 0) = 0';
}

function api_public_catalog_where(&$parameters)
{
    $parameters[':public_status'] = api_euc('公開在庫');
    return 'status2 = :public_status AND COALESCE(flag_close, 0) = 0';
}
