<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$kernel=new \App\Kernel('dev',false);
$kernel->boot();
$db=$kernel->getContainer()->get('doctrine')->getConnection();
$tables=['cc'=>['driver_cc_unmatched','driver_contact_compliance'],'dsp'=>['driver_dsp_unmatched','driver_dsp_summary'],'pod'=>['driver_pod_unmatched','driver_pod_report'],'dwc'=>['driver_dwc_unmatched','driver_dwc_iadc']];
foreach($tables as $tab=>[$unmatched,$report]){
    $db->executeQuery("SELECT u.transporter_id FROM $unmatched u WHERE u.week_start = ? AND NOT EXISTS (SELECT 1 FROM $report r WHERE r.week_start=u.week_start AND r.transporter_id=u.transporter_id) LIMIT 1",['2099-01-05'])->fetchAllAssociative();
    echo "PASS $tab unmatched reconciliation SQL\n";
}
foreach ([[9.00032,9],[6.00032,6],[3.999954,4],[2.000526,2]] as [$raw,$expected]) {
    if((int)round((float)$raw)!==$expected)throw new RuntimeException("Rounding failed");
}
echo "PASS quantity rounding\n";
