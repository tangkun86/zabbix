<?php
/**
 * Created by PhpStorm.
 * User: tangk
 * Date: 2016/5/23
 * Time: 17:26
 */

//状态下拉列表
$statusComboBox = (new CComboBox('filter_status', isset($_REQUEST['filter_status']) ? $_REQUEST['filter_status'] : null, 'submit()'))
    ->addItem(0, '请选择')
    ->addItem(1, '在线')
    ->addItem(2, '离线');

$widget = (new CWidget())
    ->setTitle('AP状态查询')
    ->setControls((new CForm('get'))
        ->cleanItems()
        ->addItem((new CList())
            ->addItem(['所属热点：',new CInput('text','distribution','')])
            ->addItem(['AP名称：',new CInput('text','apname','')])
            ->addItem(['状态：', SPACE, $statusComboBox])
//            ->addItem(new CButton('button','数据同步'))
            ->addItem(new CSubmit('form','查询'))
        )
    );

//var_dump($this->data);
// create table
$apTables = (new CTableInfo())
    ->setHeader([
//        make_sorting_header('序号', 'num', $this->data['sort'], $this->data['sortorder']),
        '序号',
        '热点名称',
        'AP名称',
        '设备厂商',
        '设备型号',
        '关联AC',
        '设备状态',
        '地址'
    ]);

$aps = $this->data['aps'];

foreach($aps as $ap){
    $status = $ap['status']==1
        ? (new CSpan('离线'))->addClass(ZBX_STYLE_RED)
        : (new CSpan('在线'))->addClass(ZBX_STYLE_GREEN);

    $apTables->addRow([
        $apTables->getNumRows()+1,
        $ap['disname'],
        $ap['apname'],
        $ap['device'],
        $ap['device_number'],
        $ap['union_ac'],
        $status,
        $ap['address']
    ]);
}

$widget->addItem([$apTables,$this->data['paging']]);

return $widget;
?>
