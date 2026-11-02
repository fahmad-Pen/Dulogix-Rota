<?php
require '/app/vendor/autoload.php';
$kernel=new App\Kernel('dev',true);
$kernel->boot();
$db=$kernel->getContainer()->get('doctrine')->getConnection();
echo 'Stored: '.$db->fetchOne('SELECT COUNT(*) FROM driver_ementor_weekly').PHP_EOL;
echo 'Matched: '.$db->fetchOne('SELECT COUNT(*) FROM driver_ementor_weekly e JOIN app_user u ON u.transporter_id=e.transporter_id').PHP_EOL;
echo 'Zero: '.$db->fetchOne('SELECT COUNT(*) FROM driver_ementor_weekly WHERE ementor_score=0').PHP_EOL;
