<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use App\Controller\OperationsDriverSwapController as Swap;
$cases=[
 ['swap non-fixed vans',true,[],[10=>'VAN-A',20=>'VAN-B'],[10=>'VAN-B',20=>'VAN-A']],
 ['swap one fixed van',true,[10=>'FIXED-A'],[10=>'FIXED-A',20=>'VAN-B'],[10=>'FIXED-A',20=>'VAN-B']],
 ['swap fixed target',true,[20=>'FIXED-B'],[10=>'VAN-A',20=>'FIXED-B'],[10=>'VAN-A',20=>'FIXED-B']],
 ['swap two fixed vans',true,[10=>'FIXED-A',20=>'FIXED-B'],[10=>'FIXED-A',20=>'FIXED-B'],[10=>'FIXED-A',20=>'FIXED-B']],
 ['change non-fixed driver',false,[],[10=>'VAN-A',20=>''],[10=>'',20=>'VAN-A']],
 ['change fixed source',false,[10=>'FIXED-A'],[10=>'FIXED-A',20=>'VAN-B'],[10=>'FIXED-A',20=>'VAN-B']],
 ['change to fixed replacement',false,[20=>'FIXED-B'],[10=>'VAN-A',20=>'FIXED-B'],[10=>'',20=>'FIXED-B']],
];
foreach($cases as [$label,$swap,$fixed,$before,$expected]){
 $actual=Swap::resolveVans(10,20,$swap,$fixed,$before);
 if($actual!==$expected)throw new RuntimeException($label.' failed: '.json_encode($actual));
 echo "PASS: $label\n";
}

$timeCases=[
 ['swap start times',true,[10=>'10:00',20=>'11:30'],[10=>'11:30',20=>'10:00']],
 ['change start time',false,[10=>'10:00',20=>null],[10=>null,20=>'10:00']],
 ['swap with missing time',true,[10=>'10:00'],[10=>null,20=>'10:00']],
 ['change to existing time',false,[10=>'10:00',20=>'09:00'],[10=>null,20=>'10:00']],
];
foreach($timeCases as [$label,$swap,$before,$expected]){
 $actual=Swap::resolveStartTimes(10,20,$swap,$before);
 if($actual!==$expected)throw new RuntimeException($label.' failed: '.json_encode($actual));
 echo "PASS: $label\n";
}
