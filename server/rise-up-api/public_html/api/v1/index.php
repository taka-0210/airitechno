<?php
require_once __DIR__ . '/lib/bootstrap.php';
if(isset($_SERVER['REQUEST_METHOD'])&&$_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');api_error(405,'Only GET is supported.');}
$permissions=api_authenticate();$path=parse_url(isset($_SERVER['REQUEST_URI'])?$_SERVER['REQUEST_URI']:'',PHP_URL_PATH);$prefix='/api/v1';if(strpos($path,$prefix)===0)$path=substr($path,strlen($prefix));$segments=array_values(array_filter(explode('/',trim($path,'/')),'strlen'));

if(count($segments)===2&&$segments[0]==='catalog'&&$segments[1]==='video-products'){
    api_authorise_catalog($permissions);$statement=api_db()->query('SELECT * FROM products ORDER BY datetime_stock DESC,item_data_no DESC');$products=array();
    foreach($statement->fetchAll() as $row){if(api_hyper_videos($row)!==array())$products[]=api_normalise_snapshot_product($row,false);}
    api_json(200,array('data'=>$products,'meta'=>array_merge(snapshot_meta(null),array('total'=>count($products)))),60);
}

if((count($segments)===2&&$segments[0]==='catalog'&&$segments[1]==='categories')||(count($segments)===3&&$segments[0]==='stores'&&$segments[2]==='categories')){
    $storeId=$segments[0]==='stores'?$segments[1]:null;if($storeId===null)api_authorise_catalog($permissions);else api_authorise_store($permissions,$storeId);
    $parameters=array();$where=snapshot_where($storeId,$parameters);$sql='SELECT class1_id,class1_name,class2_id,class2_name,COUNT(*) AS product_count FROM products WHERE '.$where.' GROUP BY class1_id,class1_name,class2_id,class2_name ORDER BY class1_id,class2_name ASC,class2_id';$statement=api_db()->prepare($sql);$statement->execute($parameters);$groups=array();
    foreach($statement->fetchAll() as $row){$name=$row['class1_name'];if(!isset($groups[$name]))$groups[$name]=array('id'=>(int)$row['class1_id'],'name'=>$name,'product_count'=>0,'children'=>array());$count=(int)$row['product_count'];$groups[$name]['product_count']+=$count;$groups[$name]['children'][]=array('id'=>(int)$row['class2_id'],'name'=>$row['class2_name'],'product_count'=>$count);}
    api_json(200,array('data'=>array_values($groups),'meta'=>snapshot_meta($storeId)),300);
}
if((count($segments)===2&&$segments[0]==='catalog'&&$segments[1]==='products')||(count($segments)===3&&$segments[0]==='stores'&&$segments[2]==='products')){
    $storeId=$segments[0]==='stores'?$segments[1]:null;if($storeId===null)api_authorise_catalog($permissions);else api_authorise_store($permissions,$storeId);$page=max(1,isset($_GET['page'])?(int)$_GET['page']:1);$perPage=min(max(1,(int)api_config('max_per_page',60)),max(1,isset($_GET['per_page'])?(int)$_GET['per_page']:24));$offset=($page-1)*$perPage;$parameters=array();$where=snapshot_where($storeId,$parameters);
    if(isset($_GET['class1'])&&trim($_GET['class1'])!==''){$where.=' AND class1_name=:class1';$parameters[':class1']=trim($_GET['class1']);}if(isset($_GET['class2_id'])&&(int)$_GET['class2_id']>0){$where.=' AND class2_id=:class2_id';$parameters[':class2_id']=(int)$_GET['class2_id'];}if($storeId===null&&isset($_GET['store_id'])&&preg_match('/^[0-9A-Za-z_-]{1,10}$/',$_GET['store_id'])){$where.=' AND store_id=:filter_store';$parameters[':filter_store']=(string)$_GET['store_id'];}if(isset($_GET['status'])&&trim($_GET['status'])!==''){$map=array('available'=>1,'shipped'=>2,'negotiating'=>4,'reserved'=>5);if(!isset($map[$_GET['status']]))api_error(400,'Status is invalid.');$where.=' AND status_no=:status';$parameters[':status']=$map[$_GET['status']];}
    $count=api_db()->prepare('SELECT COUNT(*) FROM products WHERE '.$where);$count->execute($parameters);$total=(int)$count->fetchColumn();$statement=api_db()->prepare('SELECT * FROM products WHERE '.$where.' ORDER BY CASE WHEN flag_ask_price=1 OR price_sales<=0 THEN 1 ELSE 0 END ASC,price_sales ASC,datetime_stock DESC,item_data_no DESC LIMIT '.$offset.','.$perPage);$statement->execute($parameters);$products=array();foreach($statement->fetchAll() as $row)$products[]=api_normalise_snapshot_product($row,false);$meta=snapshot_meta($storeId);$meta=array_merge($meta,array('page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$total===0?0:(int)ceil($total/$perPage)));api_json(200,array('data'=>$products,'meta'=>$meta),60);
}
if(count($segments)===2&&$segments[0]==='products'){
    $id=$segments[1];if(!preg_match('/^[0-9]{13}$/',$id))api_error(400,'Product ID is invalid.');$all=!empty($permissions['all_stores']);$stores=isset($permissions['stores'])?array_values((array)$permissions['stores']):array();if(!$all&&$stores===array())api_error(403,'No stores are available for this API key.');$parameters=array(':id'=>$id);$where='id=:id';if(!$all){$holders=array();foreach($stores as $index=>$store){$key=':store_'.$index;$holders[]=$key;$parameters[$key]=(string)$store;}$where.=' AND store_id IN ('.implode(',',$holders).')';}$statement=api_db()->prepare('SELECT * FROM products WHERE '.$where.' LIMIT 1');$statement->execute($parameters);$row=$statement->fetch();if(!$row)api_error(404,'Product was not found.');api_json(200,array('data'=>api_normalise_snapshot_product($row,true)),60);
}
api_error(404,'Endpoint was not found.');

function snapshot_where($storeId,&$parameters){if($storeId===null)return '1=1';$parameters[':store_id']=(string)$storeId;return 'store_id=:store_id';}
function snapshot_meta($storeId){$meta=array('scope'=>$storeId===null?'all_stores':'store','store_id'=>$storeId,'source'=>'snapshot');foreach(array('last_full_sync','last_delta_sync','source_cursor') as $key){$statement=api_db()->prepare('SELECT value FROM metadata WHERE key=:key');$statement->execute(array(':key'=>$key));$meta[$key]=(string)$statement->fetchColumn();}return $meta;}
