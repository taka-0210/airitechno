<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
set_time_limit(0);
$mode = isset($argv[1]) ? $argv[1] : 'delta';
if ($mode !== 'full' && $mode !== 'delta') { fwrite(STDERR, "Usage: php sync-catalog.php [full|delta]\n"); exit(2); }

$domainRoot = dirname(__DIR__);
$configPath = $domainRoot . '/api-config.php';
if (!is_file($configPath)) { throw new RuntimeException('API configuration was not found.'); }
$apiConfig = require $configPath;
if (!is_array($apiConfig)) { throw new RuntimeException('API configuration is invalid.'); }
$cacheDirectory = $domainRoot . '/api-cache';
$livePath = $cacheDirectory . '/catalog.sqlite';
$previousPath = $cacheDirectory . '/catalog.previous.sqlite';
$lockPath = $domainRoot . '/api-sync/catalog-sync.lock';
if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0775, true) && !is_dir($cacheDirectory)) { throw new RuntimeException('Could not create cache directory.'); }
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { fwrite(STDOUT, "Catalog sync is already running.\n"); exit(0); }

$kitchenConfigPath = isset($apiConfig['kitchen_config_path']) ? (string)$apiConfig['kitchen_config_path'] : $domainRoot . '/public_html/rubs/cmn/config.php';
if (!is_file($kitchenConfigPath)) { throw new RuntimeException('Kitchen configuration was not found.'); }
ob_start(); require $kitchenConfigPath; ob_end_clean();
$source = new PDO(PDO_DNS, PDO_USER, PDO_PASSWORD, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC));
$started = microtime(true);
try {
    if ($mode === 'full' || !is_file($livePath)) full_sync($source, $livePath, $previousPath, $cacheDirectory);
    else delta_sync($source, $livePath);
    $snapshot = new PDO('sqlite:' . $livePath);
    $count = (int)$snapshot->query('SELECT COUNT(*) FROM products')->fetchColumn();
    echo json_encode(array('status'=>'ok','mode'=>$mode,'products'=>$count,'elapsed_ms'=>(int)round((microtime(true)-$started)*1000),'completed_at'=>date('c')),JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Exception $error) {
    fwrite(STDERR,json_encode(array('status'=>'error','mode'=>$mode,'message'=>$error->getMessage(),'failed_at'=>date('c')),JSON_UNESCAPED_UNICODE).PHP_EOL); exit(1);
}

function source_from()
{
    return ' FROM tblItemData d INNER JOIN mstItemModel m ON d.item_model_no=m.item_model_no INNER JOIN mstItemClass1 c1 ON m.class1_no=c1.class1_no INNER JOIN mstItemClass2 c2 ON m.class2_no=c2.class2_no LEFT JOIN mstShop s ON d.shop_id=s.shop_id LEFT JOIN mstItemRank r ON d.rank_no=r.rank_no LEFT JOIN mstItemStatus st ON d.status_no=st.status_no';
}
function source_where()
{
    return " WHERE d.status_inside_no=1 AND d.status_no<>7 AND d.jancode REGEXP '^[0-9]{13}$' AND (d.status_no<>2 OR (d.date_ship IS NOT NULL AND d.date_ship>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)))";
}
function source_select()
{
    return 'SELECT d.item_data_no,d.jancode,d.shop_id,d.status_no,d.rank_no,d.year,d.size_w,d.size_d,d.size_h,d.price_sales,d.flag_ask_price,d.comment,d.date_ship,d.datetime_stock,d.datetime_update,d.count_photo,d.cmn_photo_no,m.name,m.supple,m.maker,m.model,m.details,m.movie_url,c1.class1_no,c1.class1_name,c2.class2_no,c2.class2_name,s.shop_name,r.rank_name,st.status_name'.source_from();
}
function open_snapshot($path)
{
    $db=new PDO('sqlite:'.$path);$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);return $db;
}
function create_schema($db)
{
    $db->exec('CREATE TABLE products (item_data_no INTEGER NOT NULL UNIQUE,id TEXT PRIMARY KEY,store_id TEXT,store_name TEXT,status_no INTEGER,status_label TEXT,rank_no INTEGER,rank_name TEXT,year INTEGER,size_w INTEGER,size_d INTEGER,size_h INTEGER,price_sales INTEGER,flag_ask_price INTEGER,comment TEXT,date_ship TEXT,datetime_stock TEXT,datetime_update TEXT,count_photo INTEGER,cmn_photo_no INTEGER,name TEXT,supple TEXT,maker TEXT,model TEXT,details TEXT,movie_url TEXT,class1_id INTEGER,class1_name TEXT,class2_id INTEGER,class2_name TEXT)');
    $db->exec('CREATE TABLE metadata (key TEXT PRIMARY KEY,value TEXT NOT NULL)');
    $db->exec('CREATE INDEX idx_products_category ON products(class1_id,class2_id,datetime_stock DESC)');
    $db->exec('CREATE INDEX idx_products_store ON products(store_id,datetime_stock DESC)');
    $db->exec('CREATE INDEX idx_products_status ON products(status_no,datetime_stock DESC)');
    $db->exec('CREATE INDEX idx_products_updated ON products(datetime_update)');
}
function insert_statement($db)
{
    $columns=array('item_data_no','id','store_id','store_name','status_no','status_label','rank_no','rank_name','year','size_w','size_d','size_h','price_sales','flag_ask_price','comment','date_ship','datetime_stock','datetime_update','count_photo','cmn_photo_no','name','supple','maker','model','details','movie_url','class1_id','class1_name','class2_id','class2_name');
    $holders=array();foreach($columns as $column)$holders[]=':'.$column;
    return array($db->prepare('INSERT OR REPLACE INTO products('.implode(',',$columns).') VALUES('.implode(',',$holders).')'),$columns);
}
function mapped_row($row)
{
    return array('item_data_no'=>(int)$row['item_data_no'],'id'=>(string)$row['jancode'],'store_id'=>(string)$row['shop_id'],'store_name'=>trim(strip_tags((string)$row['shop_name'])),'status_no'=>(int)$row['status_no'],'status_label'=>(string)$row['status_name'],'rank_no'=>(int)$row['rank_no'],'rank_name'=>(string)$row['rank_name'],'year'=>(int)$row['year'],'size_w'=>(int)$row['size_w'],'size_d'=>(int)$row['size_d'],'size_h'=>(int)$row['size_h'],'price_sales'=>(int)$row['price_sales'],'flag_ask_price'=>(int)$row['flag_ask_price'],'comment'=>(string)$row['comment'],'date_ship'=>(string)$row['date_ship'],'datetime_stock'=>(string)$row['datetime_stock'],'datetime_update'=>(string)$row['datetime_update'],'count_photo'=>(int)$row['count_photo'],'cmn_photo_no'=>(int)$row['cmn_photo_no'],'name'=>(string)$row['name'],'supple'=>(string)$row['supple'],'maker'=>(string)$row['maker'],'model'=>(string)$row['model'],'details'=>(string)$row['details'],'movie_url'=>(string)$row['movie_url'],'class1_id'=>(int)$row['class1_no'],'class1_name'=>(string)$row['class1_name'],'class2_id'=>(int)$row['class2_no'],'class2_name'=>(string)$row['class2_name']);
}
function insert_row($statement,$columns,$row)
{
    $mapped=mapped_row($row);$params=array();foreach($columns as $column)$params[':'.$column]=$mapped[$column];$statement->execute($params);
}
function set_metadata($db,$key,$value)
{
    $statement=$db->prepare('INSERT OR REPLACE INTO metadata(key,value) VALUES(:key,:value)');$statement->execute(array(':key'=>$key,':value'=>(string)$value));
}
function source_time($source)
{
    return (string)$source->query('SELECT DATE_FORMAT(NOW(),"%Y-%m-%d %H:%i:%s")')->fetchColumn();
}
function full_sync($source,$livePath,$previousPath,$cacheDirectory)
{
    $cursor=source_time($source);$temporary=$cacheDirectory.'/catalog.build-'.getmypid().'.sqlite';if(is_file($temporary))unlink($temporary);$db=open_snapshot($temporary);$db->exec('PRAGMA journal_mode=OFF');$db->exec('PRAGMA synchronous=OFF');create_schema($db);list($insert,$columns)=insert_statement($db);
    $query=$source->query(source_select().source_where().' ORDER BY d.item_data_no');$db->beginTransaction();while($row=$query->fetch())insert_row($insert,$columns,$row);set_metadata($db,'source_cursor',$cursor);set_metadata($db,'last_full_sync',date('c'));set_metadata($db,'last_delta_sync',date('c'));set_metadata($db,'schema_version','1');$db->commit();$db=null;
    if(is_file($previousPath))unlink($previousPath);if(is_file($livePath)&&!rename($livePath,$previousPath)){unlink($temporary);throw new RuntimeException('Could not preserve previous snapshot.');}if(!rename($temporary,$livePath)){if(is_file($previousPath))rename($previousPath,$livePath);throw new RuntimeException('Could not activate new snapshot.');}chmod($livePath,0640);
}
function delta_sync($source,$livePath)
{
    $db=open_snapshot($livePath);$cursor=(string)$db->query("SELECT value FROM metadata WHERE key='source_cursor'")->fetchColumn();if($cursor===''){throw new RuntimeException('Snapshot cursor is missing.');}$next=source_time($source);
    $eligible=$source->query('SELECT d.item_data_no'.source_from().source_where());$db->beginTransaction();$db->exec('CREATE TEMP TABLE eligible_ids(item_data_no INTEGER PRIMARY KEY)');$eligibleInsert=$db->prepare('INSERT INTO eligible_ids(item_data_no) VALUES(:id)');while($row=$eligible->fetch())$eligibleInsert->execute(array(':id'=>(int)$row['item_data_no']));$db->exec('DELETE FROM products WHERE item_data_no NOT IN (SELECT item_data_no FROM eligible_ids)');
    $query=$source->prepare(source_select().source_where().' AND d.datetime_update>=:cursor AND d.datetime_update<=:next ORDER BY d.datetime_update');$query->execute(array(':cursor'=>$cursor,':next'=>$next));list($insert,$columns)=insert_statement($db);while($row=$query->fetch())insert_row($insert,$columns,$row);set_metadata($db,'source_cursor',$next);set_metadata($db,'last_delta_sync',date('c'));$db->commit();
}
