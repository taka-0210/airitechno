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
    if ($mode === 'full' || !is_file($livePath) || snapshot_schema_version($livePath) < 4) full_sync($source, $livePath, $previousPath, $cacheDirectory);
    else delta_sync($source, $livePath);
    $snapshot = new PDO('sqlite:' . $livePath);
    $count = (int)$snapshot->query('SELECT COUNT(*) FROM products')->fetchColumn();
    echo json_encode(array('status'=>'ok','mode'=>$mode,'products'=>$count,'elapsed_ms'=>(int)round((microtime(true)-$started)*1000),'completed_at'=>date('c')),JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Exception $error) {
    fwrite(STDERR,json_encode(array('status'=>'error','mode'=>$mode,'message'=>$error->getMessage(),'failed_at'=>date('c')),JSON_UNESCAPED_UNICODE).PHP_EOL); exit(1);
}

function source_from()
{
    return ' FROM tblItemData d INNER JOIN mstItemModel m ON d.item_model_no=m.item_model_no INNER JOIN mstItemClass1 c1 ON m.class1_no=c1.class1_no INNER JOIN mstItemClass2 c2 ON m.class2_no=c2.class2_no LEFT JOIN mstMaker mk ON m.maker_no=mk.maker_no LEFT JOIN mstShop s ON d.shop_id=s.shop_id LEFT JOIN mstItemRank r ON d.rank_no=r.rank_no LEFT JOIN mstItemStatus st ON d.status_no=st.status_no';
}
function source_columns($source)
{
    static $rows=null;if($rows===null)$rows=$source->query('SHOW FULL COLUMNS FROM tblItemData')->fetchAll();return $rows;
}
function source_web_publish_column($source)
{
    global $apiConfig;
    if(!empty($apiConfig['web_publish_column']))return (string)$apiConfig['web_publish_column'];
    $candidates=array('flag_web','web_flag','web_flg','flag_web_publish','web_publish_flag','status_web_no','web_status_no','publish_web','display_web','flag_display_web');
    foreach(source_columns($source) as $row){$field=(string)$row['Field'];$key=strtolower($field);$comment=preg_replace('/\s+/u','',trim((string)$row['Comment']));
        if(in_array($key,$candidates,true)||strpos($comment,'Web掲載')!==false||strpos($comment,'WEB掲載')!==false)return $field;
    }
    throw new RuntimeException('The tblItemData Web publication column could not be detected. Set web_publish_column in api-config.php.');
}
function source_web_publish_condition($source)
{
    global $apiConfig;$field=source_web_publish_column($source);$quoted='d.`'.str_replace('`','``',$field).'`';
    $values=isset($apiConfig['web_publish_values'])?(array)$apiConfig['web_publish_values']:array('1','掲載する','掲載','公開','公開する');
    $escaped=array();foreach($values as $value)$escaped[]=$source->quote((string)$value);
    return 'CAST('.$quoted.' AS CHAR) IN ('.implode(',',$escaped).')';
}
function source_where($source)
{
    return " WHERE d.status_inside_no=1 AND ".source_web_publish_condition($source)." AND d.status_no<>7 AND d.jancode REGEXP '^[0-9]{13}$' AND (d.status_no<>2 OR (d.date_ship IS NOT NULL AND d.date_ship>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)))";
}
function source_warranty_columns($source)
{
    static $columns = null;
    if ($columns !== null) return $columns;
    $columns=array('enabled'=>null,'special'=>null);$rows=source_columns($source);
    $enabledCandidates=array('flag_warranty','warranty_flag','flag_guarantee','guarantee_flag','flag_hosyo','hosyo_flag','flag_hosho','hosho_flag');
    $specialCandidates=array('special_warranty_period','warranty_special_period','warranty_period','guarantee_period','special_guarantee_period','hosyo_period','hosho_period');
    foreach($rows as $row){$field=(string)$row['Field'];$key=strtolower($field);$comment=trim((string)$row['Comment']);
        if($columns['special']===null&&(in_array($key,$specialCandidates,true)||strpos($comment,'特例保証期間')!==false))$columns['special']=$field;
        if($columns['enabled']===null&&(in_array($key,$enabledCandidates,true)||$comment==='保証あり'||strpos($comment,'保証有無')!==false))$columns['enabled']=$field;
    }
    return $columns;
}
function source_column_expression($field,$alias)
{
    if($field===null)return 'NULL AS '.$alias;
    return 'd.`'.str_replace('`','``',$field).'` AS '.$alias;
}
function source_select($source)
{
    $warranty=source_warranty_columns($source);
    return 'SELECT d.item_data_no,d.jancode,d.shop_id,d.status_no,d.rank_no,d.year,d.size_w,d.size_d,d.size_h,d.refrigerant_gas_no,d.price_sales,d.flag_ask_price,d.comment,d.date_ship,d.datetime_stock,d.datetime_update,d.count_photo,d.cmn_photo_no,'.source_column_expression($warranty['enabled'],'warranty_enabled').','.source_column_expression($warranty['special'],'warranty_special_period').',m.name,m.supple,COALESCE(NULLIF(m.maker,\'\'),mk.maker_name) AS maker,m.model,m.price_fixed,m.flag_openprice,m.details,m.movie_url,m.opt_name1,m.opt_data1,m.opt_name2,m.opt_data2,m.opt_name3,m.opt_data3,m.opt_name4,m.opt_data4,m.opt_name5,m.opt_data5,m.opt_name6,m.opt_data6,m.opt_name7,m.opt_data7,m.opt_name8,m.opt_data8,m.opt_name9,m.opt_data9,m.opt_name10,m.opt_data10,m.opt_name11,m.opt_data11,m.opt_name12,m.opt_data12,m.opt_name13,m.opt_data13,m.opt_name14,m.opt_data14,m.opt_name15,m.opt_data15,c1.class1_no,c1.class1_name,c2.class2_no,c2.class2_name,s.shop_name,r.rank_name,st.status_name'.source_from();
}
function open_snapshot($path)
{
    $db=new PDO('sqlite:'.$path);$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);return $db;
}
function create_schema($db)
{
    $db->exec('CREATE TABLE products (item_data_no INTEGER NOT NULL UNIQUE,id TEXT PRIMARY KEY,store_id TEXT,store_name TEXT,status_no INTEGER,status_label TEXT,rank_no INTEGER,rank_name TEXT,year INTEGER,size_w INTEGER,size_d INTEGER,size_h INTEGER,price_sales INTEGER,flag_ask_price INTEGER,comment TEXT,date_ship TEXT,datetime_stock TEXT,datetime_update TEXT,count_photo INTEGER,cmn_photo_no INTEGER,photo_comments_json TEXT,warranty_enabled INTEGER,warranty_special_period TEXT,name TEXT,supple TEXT,maker TEXT,model TEXT,price_fixed INTEGER,flag_openprice INTEGER,details TEXT,movie_url TEXT,specifications_json TEXT,class1_id INTEGER,class1_name TEXT,class2_id INTEGER,class2_name TEXT)');
    $db->exec('CREATE TABLE metadata (key TEXT PRIMARY KEY,value TEXT NOT NULL)');
    $db->exec('CREATE INDEX idx_products_category ON products(class1_id,class2_id,datetime_stock DESC)');
    $db->exec('CREATE INDEX idx_products_store ON products(store_id,datetime_stock DESC)');
    $db->exec('CREATE INDEX idx_products_status ON products(status_no,datetime_stock DESC)');
    $db->exec('CREATE INDEX idx_products_updated ON products(datetime_update)');
}
function insert_statement($db)
{
    $columns=array('item_data_no','id','store_id','store_name','status_no','status_label','rank_no','rank_name','year','size_w','size_d','size_h','price_sales','flag_ask_price','comment','date_ship','datetime_stock','datetime_update','count_photo','cmn_photo_no','photo_comments_json','warranty_enabled','warranty_special_period','name','supple','maker','model','price_fixed','flag_openprice','details','movie_url','specifications_json','class1_id','class1_name','class2_id','class2_name');
    $holders=array();foreach($columns as $column)$holders[]=':'.$column;
    return array($db->prepare('INSERT OR REPLACE INTO products('.implode(',',$columns).') VALUES('.implode(',',$holders).')'),$columns);
}
function mapped_row($row)
{
    $specifications=source_specifications($row);
    return array('item_data_no'=>(int)$row['item_data_no'],'id'=>(string)$row['jancode'],'store_id'=>(string)$row['shop_id'],'store_name'=>trim(strip_tags((string)$row['shop_name'])),'status_no'=>(int)$row['status_no'],'status_label'=>(string)$row['status_name'],'rank_no'=>(int)$row['rank_no'],'rank_name'=>(string)$row['rank_name'],'year'=>(int)$row['year'],'size_w'=>(int)$row['size_w'],'size_d'=>(int)$row['size_d'],'size_h'=>(int)$row['size_h'],'price_sales'=>(int)$row['price_sales'],'flag_ask_price'=>(int)$row['flag_ask_price'],'comment'=>(string)$row['comment'],'date_ship'=>(string)$row['date_ship'],'datetime_stock'=>(string)$row['datetime_stock'],'datetime_update'=>(string)$row['datetime_update'],'count_photo'=>(int)$row['count_photo'],'cmn_photo_no'=>(int)$row['cmn_photo_no'],'photo_comments_json'=>json_encode(source_photo_comments($row),JSON_UNESCAPED_UNICODE),'warranty_enabled'=>$row['warranty_enabled']===null?null:(int)$row['warranty_enabled'],'warranty_special_period'=>(string)$row['warranty_special_period'],'name'=>(string)$row['name'],'supple'=>(string)$row['supple'],'maker'=>(string)$row['maker'],'model'=>(string)$row['model'],'price_fixed'=>(int)$row['price_fixed'],'flag_openprice'=>(int)$row['flag_openprice'],'details'=>(string)$row['details'],'movie_url'=>(string)$row['movie_url'],'specifications_json'=>json_encode($specifications,JSON_UNESCAPED_UNICODE),'class1_id'=>(int)$row['class1_no'],'class1_name'=>(string)$row['class1_name'],'class2_id'=>(int)$row['class2_no'],'class2_name'=>(string)$row['class2_name']);
}
function source_photo_comments($row)
{
    global $source;static $individual=null,$common=null;
    if($individual===null){$individual=$source->prepare('SELECT no,comment FROM tblItemPhotoComment WHERE type=0 AND jancode=:key ORDER BY no');$common=$source->prepare('SELECT no,comment FROM tblItemPhotoComment WHERE type=1 AND photo_no=:key ORDER BY no');}
    $isCommon=(int)$row['cmn_photo_no']>0;$statement=$isCommon?$common:$individual;$statement->execute(array(':key'=>$isCommon?(int)$row['cmn_photo_no']:(string)$row['jancode']));$comments=array();
    while($comment=$statement->fetch()){$text=trim((string)$comment['comment']);if($text!=='')$comments[(string)(int)$comment['no']]=$text;}
    return $comments;
}
function source_specifications($row)
{
    global $source;
    $class=(int)$row['class1_no'];$value=function($index)use($row){return trim((string)$row['opt_data'.$index]);};$items=array();
    $add=function($label,$data)use(&$items){$label=trim((string)$label);$data=trim((string)$data);if($label!==''&&$data!=='')$items[]=array('label'=>$label,'value'=>$data);};
    if($class===1){$add('電源',$value(1));if(function_exists('getRefrigerantGasName'))$add('冷媒',getRefrigerantGasName($source,$row['refrigerant_gas_no']));$add($value(4),$value(5)===''?'':$value(5).' L');$add($value(6),$value(7)===''?'':$value(7).' L');$add($value(8),$value(9));}
    elseif($class===2){$add('電源',$value(1));if(function_exists('getRefrigerantGasName'))$add('冷媒',getRefrigerantGasName($source,$row['refrigerant_gas_no']));$add('製氷能力',$value(10)===''?'':$value(10).' kg/日');}
    elseif($class===3){$unit=$value(1)==='都市ガス'?' kcal/h':($value(1)===''?'':' kg/h');$add('ガス種',$value(1));$add('ガス消費量1',$value(3)===''?'':$value(3).' kW');$add('ガス消費量2',$value(4).$unit);$add($value(5),$value(6));}
    elseif(in_array($class,array(4,5,6),true)){$add('電源',$value(1));$add('消費電力',trim($value(2).' '.$value(4)));$add('Hz',$value(3));$add($value(5),$value(6));}
    elseif($class===7){$add('電源',$value(1));$add('洗浄能力',$value(2)===''?'':$value(2).' ラック/時（標準）');$add('タイプ',$value(7));$add('貯湯タンク',$value(3));$add($value(6),trim($value(4).' '.$value(5)));}
    elseif($class===8){$add('電源',$value(1));$add('出力',$value(2)===''?'':$value(2).' W');$add('メモリー',$value(4));$add('庫内タイプ',$value(5));}
    elseif($class===9){$add('印字方式',$value(1));$add('客面表示',$value(2));$add('部門数',$value(3));$add('ロール紙',trim($value(4).' ロール '.$value(5).' × '.$value(6).' mm'));$add('カギ',trim('責任者：'.$value(7).' スタッフ：'.$value(8).' 引出：'.$value(9)));}
    elseif($class===10){$add('備考1',$value(2));$add('備考2',$value(3));}
    elseif($class===11){$add($value(4),$value(1));$add('販売区分',$value(5));}
    elseif($class===12){$add('電源',$value(1));if(function_exists('getRefrigerantGasName'))$add('冷媒',getRefrigerantGasName($source,$row['refrigerant_gas_no']));$add('外機型番',$value(2));$add('外機サイズ',trim('W：'.$value(5).' D：'.$value(6).' H：'.$value(7)));$add('冷暖房',$value(4));$add('リモコン',$value(8));}
    elseif(in_array($class,array(13,14),true)){$add($value(4),$value(1));$add($value(5),$value(3));$add($value(6),$value(7));$add($value(8),$value(9));}
    else{for($index=1;$index<=15;$index++)$add($row['opt_name'.$index],$value($index));}
    return $items;
}
function snapshot_schema_version($path)
{
    try{$db=open_snapshot($path);$value=$db->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn();return (int)$value;}catch(Exception $error){return 0;}
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
    $query=$source->query(source_select($source).source_where($source).' ORDER BY d.item_data_no');$db->beginTransaction();while($row=$query->fetch())insert_row($insert,$columns,$row);set_metadata($db,'source_cursor',$cursor);set_metadata($db,'last_full_sync',date('c'));set_metadata($db,'last_delta_sync',date('c'));set_metadata($db,'schema_version','4');$db->commit();$db=null;
    if(is_file($previousPath))unlink($previousPath);if(is_file($livePath)&&!rename($livePath,$previousPath)){unlink($temporary);throw new RuntimeException('Could not preserve previous snapshot.');}if(!rename($temporary,$livePath)){if(is_file($previousPath))rename($previousPath,$livePath);throw new RuntimeException('Could not activate new snapshot.');}chmod($livePath,0640);
}
function delta_sync($source,$livePath)
{
    $db=open_snapshot($livePath);$cursor=(string)$db->query("SELECT value FROM metadata WHERE key='source_cursor'")->fetchColumn();if($cursor===''){throw new RuntimeException('Snapshot cursor is missing.');}$next=source_time($source);
    $eligible=$source->query('SELECT d.item_data_no'.source_from().source_where($source));$db->beginTransaction();$db->exec('CREATE TEMP TABLE eligible_ids(item_data_no INTEGER PRIMARY KEY)');$eligibleInsert=$db->prepare('INSERT INTO eligible_ids(item_data_no) VALUES(:id)');while($row=$eligible->fetch())$eligibleInsert->execute(array(':id'=>(int)$row['item_data_no']));$db->exec('DELETE FROM products WHERE item_data_no NOT IN (SELECT item_data_no FROM eligible_ids)');
    $query=$source->prepare(source_select($source).source_where($source).' AND d.datetime_update>=:cursor AND d.datetime_update<=:next ORDER BY d.datetime_update');$query->execute(array(':cursor'=>$cursor,':next'=>$next));list($insert,$columns)=insert_statement($db);while($row=$query->fetch())insert_row($insert,$columns,$row);set_metadata($db,'source_cursor',$next);set_metadata($db,'last_delta_sync',date('c'));$db->commit();
}
