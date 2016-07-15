<?php

require dirname(__FILE__).'/include/config.inc.php';

if(isset($_GET['sid']) && $_GET['output']==='ajax'){
    $sql = 'select count(distinct if(ap.use_status=0,ap.id,null)) as ap_online_num,count(distinct ap.id) as ap_num,count(distinct e.exchange_id) as exchange_num,count(distinct r.router_id) as router_num,count(distinct f.firewall_id) as firewall_num,h.id,h.region_province_name,h.region_city_name,h.region_county_name,h.region_address,h.longitude,h.latitude from device_hotspot h left join device_ap ap on ap.hotspot=h.id left join hotspot_exchange_r e on e.hotspot_id=h.id left join hotspot_router_r r on r.hotspot_id=h.id left join hotspot_firewall_r f on f.hotspot_id=h.id group by h.id;';
    $rows = DBselect($sql);
    $res_arr = DBfetchArray($rows);
    header('Content-Type:json;Charset=utf-8');
    exit(json_encode([
        'code'=>0,
        'message'=>'',
        'data'=>$res_arr
    ]));
}

$page['title'] = '查询热点分布';
//$page['scripts'][] = 'jquery.js';

require dirname(__FILE__).'/include/page_header.php';

//var_dump($page);
/*
 * display
 */
$widget = (new CWidget())->setTitle('热点分布地图');
$widget->addItem((new CDiv())
    ->setId('main')
    ->addStyle('width:100%;height:500px')
);


$jsScripts = '';
//$jsScripts.='<script src="http://cdn.bootcss.com/echarts/3.1.10/echarts.min.js"></script>';
$jsScripts.='<script src="js/vendors/echarts.min.js""></script>';
$jsScripts.='<script src="js/vendors/china.js"></script>';
$jsScripts.='<script src="js/pages/wifi-distribution/index.js"></script>';


$widget->addItem((new CJsScript($jsScripts)));
$widget->show();
//require dirname(__FILE__).'/include/views/js/administrator.wifi_distribution.js.php';
require dirname(__FILE__).'/include/page_footer.php';



