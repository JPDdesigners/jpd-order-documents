<?php
require __DIR__.'/bootstrap.php';
$out=dirname(__DIR__).'/artifacts/order-documents';
if (!is_dir($out) && !mkdir($out,0700,true)) { throw new RuntimeException('Cannot create fixture output directory.'); }
$order=$GLOBALS['orders'][300];
$data=JPD_OD_Documents::collect($order);
$first=$data['rows'][0];$data['rows']=array();$data['qty']=0;$data['total']=0;
$rows=isset($argv[1])?(int)$argv[1]:80;
for($i=0;$i<$rows;$i++){
    $r=$first;$r['item_id']=$i+1;$r['parent_sku']=(string)(2413851+$i);$r['group']='product_'.$i;
    $r['root']=$i<($rows/2)?'ΓΥΝΑΙΚΕΙΑ':'ΑΝΔΡΙΚΑ';$r['sub']='ΠΟΥΚΑΜΙΣΑ';$r['color']=$i%2?'Μπλε':'Λευκό';
    $r['name']='Πουκάμισο καλοκαιρινό με μακρύ μανίκι';$r['notes']=$i%17===0?array('Χρησιμοποιείται η αποθηκευμένη αξία γραμμής'):array();
    $data['rows'][]=$r;$data['qty']+=$r['qty'];$data['total']+=$r['total'];
}
$data['warnings']=count(array_filter($data['rows'],fn($r)=>!empty($r['notes'])));
$start=microtime(true);$path=JPD_OD_Documents::generate('compact',$order,$data,$out);
echo json_encode(array('path'=>$path,'rows'=>$rows,'bytes'=>filesize($path),'seconds'=>round(microtime(true)-$start,2),'peakMiB'=>round(memory_get_peak_usage(true)/1048576,2)),JSON_UNESCAPED_UNICODE)."\n";
