<?php
// Zabbix GUI configuration file.
global $DB;

$DB['TYPE']     = 'MYSQL';
$DB['SERVER']   = '192.168.1.72';
$DB['PORT']     = '3306';
$DB['DATABASE'] = 'zabbix';
$DB['USER']     = 'zabbix';
$DB['PASSWORD'] = 'My(sql888';

// Schema name. Used for IBM DB2 and PostgreSQL.
$DB['SCHEMA'] = '';

$ZBX_SERVER      = '192.168.1.72';
$ZBX_SERVER_PORT = '10051';
$ZBX_SERVER_NAME = 'AMP-建飞自动预警监控平台';

$IMAGE_FORMAT_DEFAULT = IMAGE_FORMAT_PNG;
?>
