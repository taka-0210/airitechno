<?php

require_once __DIR__ . '/lib/bootstrap.php';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    api_error(405, 'Only GET is supported.');
}

$permissions = api_authenticate();
$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
$prefix = '/api/v1';
if (strpos($path, $prefix) === 0) {
    $path = substr($path, strlen($prefix));
}
$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

if (count($segments) === 2 && $segments[0] === 'catalog' && $segments[1] === 'categories') {
    api_authorise_catalog($permissions);
    $parameters = array();
    $where = api_public_catalog_where($parameters);
    $sql = 'SELECT class1, class2_id, class2, COUNT(*) AS product_count FROM stock_list_rubs WHERE ' . $where . ' GROUP BY class1, class2_id, class2 ORDER BY class1, class2';
    $statement = api_db()->prepare($sql);
    $statement->execute($parameters);
    $groups = array();
    foreach ($statement->fetchAll() as $row) {
        $row = api_utf8($row);
        $class1 = trim($row['class1']);
        if (!isset($groups[$class1])) {
            $groups[$class1] = array('name' => $class1, 'product_count' => 0, 'children' => array());
        }
        $count = (int) $row['product_count'];
        $groups[$class1]['product_count'] += $count;
        $groups[$class1]['children'][] = array('id' => (int) $row['class2_id'], 'name' => trim($row['class2']), 'product_count' => $count);
    }
    api_json(200, array('data' => array_values($groups), 'meta' => array('scope' => 'all_stores')), 300);
}

if (count($segments) === 2 && $segments[0] === 'catalog' && $segments[1] === 'products') {
    api_authorise_catalog($permissions);
    $page = max(1, isset($_GET['page']) ? (int) $_GET['page'] : 1);
    $maxPerPage = max(1, (int) api_config('max_per_page', 60));
    $perPage = min($maxPerPage, max(1, isset($_GET['per_page']) ? (int) $_GET['per_page'] : 24));
    $offset = ($page - 1) * $perPage;
    $parameters = array();
    $where = api_public_catalog_where($parameters);
    if (isset($_GET['class1']) && trim($_GET['class1']) !== '') {
        $where .= ' AND class1 = :class1';
        $parameters[':class1'] = api_euc(trim($_GET['class1']));
    }
    if (isset($_GET['class2_id']) && (int) $_GET['class2_id'] > 0) {
        $where .= ' AND class2_id = :class2_id';
        $parameters[':class2_id'] = (int) $_GET['class2_id'];
    }
    if (isset($_GET['store_id']) && preg_match('/^[0-9A-Za-z_-]{1,10}$/', $_GET['store_id'])) {
        $where .= ' AND shop_id = :store_id';
        $parameters[':store_id'] = (string) $_GET['store_id'];
    }
    $countStatement = api_db()->prepare('SELECT COUNT(*) FROM stock_list_rubs WHERE ' . $where);
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();
    $sql = 'SELECT shop_id,jan13,status,date,rank,class1,maker,class2,class2_id,shohin,model,year,w,d,h,price2,price2_notax,stock,date_out,status2,ask,flag_close,tokucho,comment,hosyo FROM stock_list_rubs WHERE ' . $where . ' ORDER BY date DESC, jan13 DESC LIMIT ' . $offset . ',' . $perPage;
    $statement = api_db()->prepare($sql);
    $statement->execute($parameters);
    $products = array();
    foreach ($statement->fetchAll() as $row) {
        $products[] = api_normalise_product($row, false);
    }
    api_json(200, array('data' => $products, 'meta' => array('scope' => 'all_stores', 'page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $total === 0 ? 0 : (int) ceil($total / $perPage))), 60);
}

if (count($segments) === 3 && $segments[0] === 'stores' && $segments[2] === 'categories') {
    $storeId = $segments[1];
    if (!preg_match('/^[0-9A-Za-z_-]{1,10}$/', $storeId)) {
        api_error(400, 'Store ID is invalid.');
    }
    api_authorise_store($permissions, $storeId);
    $parameters = array();
    $where = api_public_where($storeId, $parameters);
    $sql = 'SELECT class1, class2_id, class2, COUNT(*) AS product_count FROM stock_list_rubs WHERE ' . $where . ' GROUP BY class1, class2_id, class2 ORDER BY class1, class2';
    $statement = api_db()->prepare($sql);
    $statement->execute($parameters);
    $groups = array();
    foreach ($statement->fetchAll() as $row) {
        $row = api_utf8($row);
        $class1 = trim($row['class1']);
        if (!isset($groups[$class1])) {
            $groups[$class1] = array('name' => $class1, 'product_count' => 0, 'children' => array());
        }
        $count = (int) $row['product_count'];
        $groups[$class1]['product_count'] += $count;
        $groups[$class1]['children'][] = array('id' => (int) $row['class2_id'], 'name' => trim($row['class2']), 'product_count' => $count);
    }
    api_json(200, array('data' => array_values($groups), 'meta' => array('store_id' => $storeId)), 300);
}

if (count($segments) === 3 && $segments[0] === 'stores' && $segments[2] === 'products') {
    $storeId = $segments[1];
    if (!preg_match('/^[0-9A-Za-z_-]{1,10}$/', $storeId)) {
        api_error(400, 'Store ID is invalid.');
    }
    api_authorise_store($permissions, $storeId);
    $page = max(1, isset($_GET['page']) ? (int) $_GET['page'] : 1);
    $maxPerPage = max(1, (int) api_config('max_per_page', 60));
    $perPage = min($maxPerPage, max(1, isset($_GET['per_page']) ? (int) $_GET['per_page'] : 24));
    $offset = ($page - 1) * $perPage;
    $parameters = array();
    $where = api_public_where($storeId, $parameters);

    if (isset($_GET['class1']) && trim($_GET['class1']) !== '') {
        $where .= ' AND class1 = :class1';
        $parameters[':class1'] = api_euc(trim($_GET['class1']));
    }
    if (isset($_GET['class2_id']) && (int) $_GET['class2_id'] > 0) {
        $where .= ' AND class2_id = :class2_id';
        $parameters[':class2_id'] = (int) $_GET['class2_id'];
    }
    if (isset($_GET['status']) && trim($_GET['status']) !== '') {
        $statusMap = array('available' => '販売中', 'negotiating' => '商談中', 'reserved' => '売約済み', 'shipped' => '出荷済み');
        if (!isset($statusMap[$_GET['status']])) {
            api_error(400, 'Status is invalid.');
        }
        $where .= ' AND status = :status';
        $parameters[':status'] = api_euc($statusMap[$_GET['status']]);
    }

    $countStatement = api_db()->prepare('SELECT COUNT(*) FROM stock_list_rubs WHERE ' . $where);
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();
    $sql = 'SELECT shop_id,jan13,status,date,rank,class1,maker,class2,class2_id,shohin,model,year,w,d,h,price2,price2_notax,stock,date_out,status2,ask,flag_close,tokucho,comment,hosyo FROM stock_list_rubs WHERE ' . $where . ' ORDER BY date DESC, jan13 DESC LIMIT ' . $offset . ',' . $perPage;
    $statement = api_db()->prepare($sql);
    $statement->execute($parameters);
    $products = array();
    foreach ($statement->fetchAll() as $row) {
        $products[] = api_normalise_product($row, false);
    }
    api_json(200, array(
        'data' => $products,
        'meta' => array(
            'store_id' => $storeId,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ),
    ), 60);
}

if (count($segments) === 2 && $segments[0] === 'products') {
    $jancode = $segments[1];
    if (!preg_match('/^[0-9]{13}$/', $jancode)) {
        api_error(400, 'Product ID is invalid.');
    }
    $allStores = !empty($permissions['all_stores']);
    $allowedStores = isset($permissions['stores']) ? array_values((array) $permissions['stores']) : array();
    if (!$allStores && $allowedStores === array()) {
        api_error(403, 'No stores are available for this API key.');
    }
    $parameters = array(':jancode' => $jancode, ':public_status' => api_euc('公開在庫'));
    $storeClause = '';
    if (!$allStores) {
        $placeholders = array();
        foreach ($allowedStores as $index => $storeId) {
            $placeholder = ':store_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = (string) $storeId;
        }
        $storeClause = ' AND shop_id IN (' . implode(',', $placeholders) . ')';
    }
    $sql = 'SELECT shop_id,jan13,status,date,rank,class1,maker,class2,class2_id,shohin,model,year,w,d,h,price2,price2_notax,stock,date_out,status2,ask,flag_close,tokucho,comment,hosyo FROM stock_list_rubs WHERE jan13 = :jancode' . $storeClause . ' AND status2 = :public_status AND COALESCE(flag_close, 0) = 0 LIMIT 1';
    $statement = api_db()->prepare($sql);
    $statement->execute($parameters);
    $row = $statement->fetch();
    if (!$row) {
        api_error(404, 'Product was not found.');
    }
    api_json(200, array('data' => api_normalise_product($row, true)), 60);
}

api_error(404, 'Endpoint was not found.');
