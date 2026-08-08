<?php
require_once __DIR__ . '/lib/bootstrap.php';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET'); api_error(405, 'Only GET is supported.');
}
$permissions = api_authenticate();
$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
$prefix = '/api/v1';
if (strpos($path, $prefix) === 0) $path = substr($path, strlen($prefix));
$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

if ((count($segments) === 2 && $segments[0] === 'catalog' && $segments[1] === 'categories') || (count($segments) === 3 && $segments[0] === 'stores' && $segments[2] === 'categories')) {
    $storeId = $segments[0] === 'stores' ? $segments[1] : null;
    if ($storeId === null) api_authorise_catalog($permissions); else api_authorise_store($permissions, $storeId);
    $parameters = array();
    $where = api_hyper_public_where($storeId, $parameters);
    $sql = 'SELECT c1.class1_no, c1.class1_name, c2.class2_no, c2.class2_name, COUNT(*) AS product_count '
         . api_hyper_product_from() . ' WHERE ' . $where
         . ' GROUP BY c1.class1_no,c1.class1_name,c2.class2_no,c2.class2_name ORDER BY c1.class1_no,c2.class2_no';
    $statement = api_db()->prepare($sql); $statement->execute($parameters); $groups = array();
    foreach ($statement->fetchAll() as $row) {
        $name = trim((string) $row['class1_name']);
        if (!isset($groups[$name])) $groups[$name] = array('id' => (int)$row['class1_no'], 'name' => $name, 'product_count' => 0, 'children' => array());
        $count = (int)$row['product_count'];
        $groups[$name]['product_count'] += $count;
        $groups[$name]['children'][] = array('id'=>(int)$row['class2_no'],'name'=>trim((string)$row['class2_name']),'product_count'=>$count);
    }
    api_json(200, array('data'=>array_values($groups),'meta'=>array('scope'=>$storeId===null?'all_stores':'store','store_id'=>$storeId,'source'=>'hyper')), 300);
}

if ((count($segments) === 2 && $segments[0] === 'catalog' && $segments[1] === 'products') || (count($segments) === 3 && $segments[0] === 'stores' && $segments[2] === 'products')) {
    $storeId = $segments[0] === 'stores' ? $segments[1] : null;
    if ($storeId === null) api_authorise_catalog($permissions); else api_authorise_store($permissions, $storeId);
    $page=max(1,isset($_GET['page'])?(int)$_GET['page']:1); $max=max(1,(int)api_config('max_per_page',60)); $perPage=min($max,max(1,isset($_GET['per_page'])?(int)$_GET['per_page']:24)); $offset=($page-1)*$perPage;
    $parameters=array(); $where=api_hyper_public_where($storeId,$parameters);
    if(isset($_GET['class1'])&&trim($_GET['class1'])!==''){ $where.=' AND c1.class1_name=:class1'; $parameters[':class1']=trim($_GET['class1']); }
    if(isset($_GET['class2_id'])&&(int)$_GET['class2_id']>0){ $where.=' AND c2.class2_no=:class2_no'; $parameters[':class2_no']=(int)$_GET['class2_id']; }
    if($storeId===null&&isset($_GET['store_id'])&&preg_match('/^[0-9A-Za-z_-]{1,10}$/',$_GET['store_id'])){ $where.=' AND d.shop_id=:filter_store_id'; $parameters[':filter_store_id']=(string)$_GET['store_id']; }
    if(isset($_GET['status'])&&trim($_GET['status'])!==''){ $map=array('available'=>1,'shipped'=>2,'negotiating'=>4,'reserved'=>5); if(!isset($map[$_GET['status']]))api_error(400,'Status is invalid.'); $where.=' AND d.status_no=:filter_status'; $parameters[':filter_status']=$map[$_GET['status']]; }
    $count=api_db()->prepare('SELECT COUNT(*) '.api_hyper_product_from().' WHERE '.$where); $count->execute($parameters); $total=(int)$count->fetchColumn();
    $sql=api_hyper_product_select().' WHERE '.$where.' ORDER BY d.datetime_stock DESC,d.item_data_no DESC LIMIT '.$offset.','.$perPage;
    $statement=api_db()->prepare($sql); $statement->execute($parameters); $products=array(); foreach($statement->fetchAll() as $row)$products[]=api_normalise_hyper_product($row,false);
    api_json(200,array('data'=>$products,'meta'=>array('scope'=>$storeId===null?'all_stores':'store','store_id'=>$storeId,'source'=>'hyper','page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$total===0?0:(int)ceil($total/$perPage))),60);
}

if(count($segments)===2&&$segments[0]==='products'){
    $jancode=$segments[1]; if(!preg_match('/^[0-9]{13}$/',$jancode))api_error(400,'Product ID is invalid.');
    $all=!empty($permissions['all_stores']); $stores=isset($permissions['stores'])?array_values((array)$permissions['stores']):array(); if(!$all&&$stores===array())api_error(403,'No stores are available for this API key.');
    $parameters=array(':jancode'=>$jancode); $where=api_hyper_public_where(null,$parameters).' AND d.jancode=:jancode';
    if(!$all){$holders=array();foreach($stores as $index=>$id){$key=':allowed_store_'.$index;$holders[]=$key;$parameters[$key]=(string)$id;}$where.=' AND d.shop_id IN ('.implode(',',$holders).')';}
    $statement=api_db()->prepare(api_hyper_product_select().' WHERE '.$where.' LIMIT 1'); $statement->execute($parameters); $row=$statement->fetch(); if(!$row)api_error(404,'Product was not found.');
    api_json(200,array('data'=>api_normalise_hyper_product($row,true)),60);
}
api_error(404,'Endpoint was not found.');
