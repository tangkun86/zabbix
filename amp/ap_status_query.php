<?php

require dirname(__FILE__).'/include/config.inc.php';

$page['title'] = '查询AP状态';
//$page['type'] = PAGE_TYPE_HTML;
require dirname(__FILE__).'/include/page_header.php';





/*
 * Display
 */

// prepare data
$data = [
    'sort'=>'num',
    'sortorder'=>'desc',
    /*'aps'=>[
        ['num'=>1,'disname'=>'省医院','apname'=>'AP_name1','device'=>'华为','device_number'=>'HW995-D','union_ac'=>'HW-AC','status'=>1,'address'=>'四川省成都市省医院一环路西二段32号'],
        ['num'=>2,'disname'=>'省医院','apname'=>'AP_name2','device'=>'华为','device_number'=>'HW995-D','union_ac'=>'HW-AC','status'=>1,'address'=>'四川省成都市省医院一环路西二段32号'],
        ['num'=>3,'disname'=>'省医院','apname'=>'AP_name3','device'=>'华为','device_number'=>'HW995-D','union_ac'=>'HW-AC','status'=>1,'address'=>'四川省成都市省医院一环路西二段32号'],
        ['num'=>4,'disname'=>'省医院','apname'=>'AP_name4','device'=>'华为','device_number'=>'HW995-D','union_ac'=>'HW-AC','status'=>1,'address'=>'四川省成都市省医院一环路西二段32号'],
        ['num'=>5,'disname'=>'省医院','apname'=>'AP_name5','device'=>'华为','device_number'=>'HW995-D','union_ac'=>'HW-AC','status'=>1,'address'=>'四川省成都市省医院一环路西二段32号'],
        ['num'=>6,'disname'=>'华西医院','apname'=>'AP_name6','device'=>'敦崇','device_number'=>'WA722M-E','union_ac'=>'DC-AC','status'=>0,'address'=>'四川省成都市华西医院一环路南三段22号'],
        ['num'=>7,'disname'=>'华西医院','apname'=>'AP_name7','device'=>'敦崇','device_number'=>'WA722M-D','union_ac'=>'DC-AC','status'=>0,'address'=>'四川省成都市华西医院一环路南三段22号'],
        ['num'=>8,'disname'=>'华西医院','apname'=>'AP_name8','device'=>'敦崇','device_number'=>'WA722M-D','union_ac'=>'DC-AC','status'=>1,'address'=>'四川省成都市华西医院一环路南三段22号'],
        ['num'=>9,'disname'=>'华西医院','apname'=>'AP_name9','device'=>'敦崇','device_number'=>'WA722M-D','union_ac'=>'DC-AC','status'=>1,'address'=>'四川省成都市华西医院一环路南三段22号'],
        ['num'=>10,'disname'=>'华西医院','apname'=>'AP_name10','device'=>'敦崇','device_number'=>'WA722M-D','union_ac'=>'DC-AC','status'=>0,'address'=>'四川省成都市华西医院一环路南三段22号'],
        ['num'=>11,'disname'=>'华西医院','apname'=>'AP_name10','device'=>'敦崇','device_number'=>'WA722M-D','union_ac'=>'DC-AC','status'=>1,'address'=>'四川省成都市华西医院一环路南三段22号'],
        ['num'=>12,'disname'=>'华西医院','apname'=>'AP_name10','device'=>'敦崇','device_number'=>'WA722M-D','union_ac'=>'DC-AC','status'=>0,'address'=>'四川省成都市华西医院一环路南三段22号'],
    ]*/
];

$sortOrder = 'sortorder';
// pagenation
$req_rows_per_page = isset($_REQUEST['req_rows_per_page']) ? $_REQUEST['req_rows_per_page'] : 10;

//data from database
$sql = 'select * from `device_ap`';

$where = ' where 1';
if (isset($_REQUEST['distribution']) && $_REQUEST['distribution']) {
        $where .= ' && hotspot_name like "%'.$_REQUEST['distribution'].'%"';
}
if(isset($_REQUEST['apname']) && $_REQUEST['apname']){
    $where .= ' && name like "%'.$_REQUEST['apname'].'%"';
}
if(isset($_REQUEST['filter_status']) && $_REQUEST['filter_status']){
    $use_status = $_REQUEST['filter_status'];
    $use_status --;
    $where .= ' && use_status='.$use_status;
}

$sql .= $where;
//echo $sql;
$rows = DBselect($sql);
while ($row = DBfetch($rows)) {
    $data['aps'][] = [
        'num' => $row['id'],
        'disname' => $row['hotspot_name'],
        'apname' => $row['name'],
        'device' => $row['factory'],
        'device_number' => $row['device_type'],
        'union_ac' => $row['ac_name'],
        'status' => $row['use_status'],
        'address' => $row['region_province_name'].$row['region_city_name'].$row['region_county_name'].$row['region_address']
    ];
}

//var_dump($data);exit;

$data['paging'] = getPagingLine($data['aps'], $sortOrder, $req_rows_per_page);

// view
$usersView = new CView('administration.apstatusquery.list', $data);
$usersView->render();
$usersView->show();

require dirname(__FILE__).'/include/page_footer.php';



