<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$dir=__DIR__.'/data'; if(!is_dir($dir)) mkdir($dir,0750,true);
$db=new PDO('sqlite:'.$dir.'/cartorio.sqlite'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS requests (id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE NOT NULL,name TEXT NOT NULL,phone TEXT NOT NULL,service TEXT NOT NULL,description TEXT,channel TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'novo',amount REAL NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
if((int)$db->query('SELECT COUNT(*) FROM requests')->fetchColumn()===0){$s=$db->prepare('INSERT INTO requests(code,name,phone,service,description,channel,status,amount) VALUES(?,?,?,?,?,?,?,?)');$s->execute(['CD-1086','Mariana Alves','(11) 9 9222-1840','2ª via de certidão de casamento','Casamento em Guarulhos, 2014.','Triagem digital','protocolado',168]);}
function out(array $d,int $s=200):never{http_response_code($s);echo json_encode($d,JSON_UNESCAPED_UNICODE);exit;}
$action=$_GET['action']??'';
if($_SERVER['REQUEST_METHOD']==='GET'&&$action==='track'){$s=$db->prepare('SELECT code,name AS client,service,description,channel,status,amount,created_at FROM requests WHERE code=?');$s->execute([strtoupper(trim((string)($_GET['code']??'')))]);$r=$s->fetch(PDO::FETCH_ASSOC);out($r?['ok'=>true,'request'=>$r]:['ok'=>false,'message'=>'Pedido não encontrado.'],$r?200:404);}
if($_SERVER['REQUEST_METHOD']==='POST'&&$action==='request'){$d=json_decode(file_get_contents('php://input')?:'{}',true)?:[];foreach(['name','phone','service'] as $f)if(trim((string)($d[$f]??''))==='')out(['ok'=>false,'message'=>'Preencha nome, telefone e serviço.'],422);$n=(int)$db->query('SELECT COALESCE(MAX(id),1090)+1 FROM requests')->fetchColumn();$s=$db->prepare('INSERT INTO requests(code,name,phone,service,description,channel,status) VALUES(?,?,?,?,?,?,?)');$s->execute(['CD-'.$n,trim($d['name']),trim($d['phone']),trim($d['service']),trim((string)($d['description']??'')),trim((string)($d['channel']??'Site')),'novo']);out(['ok'=>true,'code'=>'CD-'.$n]);}
out(['ok'=>false,'message'=>'Ação inválida.'],404);
