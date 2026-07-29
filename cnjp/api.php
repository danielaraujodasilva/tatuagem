<?php
declare(strict_types=1);
require __DIR__ . '/../plan/includes/bootstrap.php';
$action = $_GET['action'] ?? '';
try {
    if ($action === 'login') {
        verify_csrf(); $input=json_input();
        $stmt=db()->prepare('SELECT * FROM users WHERE email=? AND is_active=1 LIMIT 1');
        $stmt->execute([trim((string)($input['email']??''))]); $user=$stmt->fetch();
        if(!$user || !password_verify((string)($input['password']??''),$user['password_hash'])) json_response(['ok'=>false,'message'=>'E-mail ou senha invalidos.'],422);
        $_SESSION['user_id']=(int)$user['id']; audit('login','user',(int)$user['id']); json_response(['ok'=>true]);
    }
    $user=require_auth(); if($_SERVER['REQUEST_METHOD']!=='GET') verify_csrf();
    ensure_cnjp_schema(); seed_cnjp_tasks(); seed_known_cnjp_facts();
    match($action){
        'bootstrap'=>cnjp_bootstrap($user),
        'history'=>cnjp_history(),
        'undo_history'=>undo_history(),
        'save_task'=>save_task(),
        'save_project_note'=>save_project_note(),
        'save_decision'=>save_decision(),
        'delete_decision'=>delete_decision(),
        default=>json_response(['ok'=>false,'message'=>'Acao desconhecida.'],404),
    };
} catch(Throwable $e){global $config;$p=['ok'=>false,'message'=>'Erro interno ao carregar o roadmap CNJP.'];if(!empty($config['debug']))$p['detail']=$e->getMessage();json_response($p,500);}

function ensure_cnjp_schema():void{
    db()->exec("CREATE TABLE IF NOT EXISTS cnjp_tasks (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,task_key VARCHAR(100) NOT NULL UNIQUE,phase TINYINT UNSIGNED NOT NULL,sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,title VARCHAR(255) NOT NULL,explanation TEXT NULL,question TEXT NULL,status ENUM('todo','doing','done','blocked') NOT NULL DEFAULT 'todo',answer MEDIUMTEXT NULL,notes MEDIUMTEXT NULL,updated_by INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_cnjp_phase(phase,sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS cnjp_project_notes (note_key VARCHAR(80) PRIMARY KEY,content MEDIUMTEXT NULL,updated_by INT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS cnjp_decisions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(255) NOT NULL,description TEXT NULL,decision_status ENUM('open','decided','discarded') NOT NULL DEFAULT 'open',created_by INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS cnjp_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,entity_type ENUM('task','note','decision') NOT NULL,entity_key VARCHAR(100) NOT NULL,action_type ENUM('create','update','delete','undo') NOT NULL,before_json LONGTEXT NULL,after_json LONGTEXT NULL,user_id INT NULL,source_history_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_cnjp_history_created(created_at),INDEX idx_cnjp_history_entity(entity_type,entity_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function cnjp_task_definitions():array{return [
['company_cnaes',1,10,'Levantar CNPJ e CNAEs atuais','Precisamos saber o que a empresa ja possui formalmente antes de inventar novos bracos.','Cole aqui os CNAEs atuais, codigo + descricao, e marque quais parecem ser da Fran, da tecnologia ou apenas cadastrais.'],
['fran_qualifications',1,20,'Mapear formacao e habilitacoes da Fran','Essa resposta define o limite real do que pode ser oferecido diretamente pela CNJP e o que dependera de parceiro.','Liste cursos, certificados, registros, experiencia, formacao e habilitacoes da Fran.'],
['past_services',1,30,'Listar tudo que a CNJP ja fez','Antes de criar produtos novos, precisamos enxergar o que ja foi vendido e entregue na pratica.','Liste casos, atendimentos, documentos, acordos, clientes, demandas recorrentes e servicos que a CNJP ja executou.'],
['tech_capabilities',1,40,'Mapear o que Daniel entrega em tecnologia','Nao queremos vender tecnologia abstrata. Queremos saber o que ja pode ser entregue hoje com confianca.','Liste sites, sistemas, CRM, WhatsApp, IA, automacoes, integracoes, servidores, hospedagem, analytics etc.'],
['current_assets',1,50,'Inventariar ativos existentes','Site, dominios, sistemas, contatos, base de clientes e modelos economizam meses quando sabemos que existem.','Liste tudo que a CNJP ja possui e pode reaproveitar.'],
['partners',1,60,'Mapear parceiros e lacunas','Uma empresa nao precisa executar tudo internamente. Precisamos saber quem entra quando a demanda ultrapassa a competencia da CNJP.','Liste parceiros atuais e profissionais que ainda precisamos encontrar.'],
['service_universe',2,10,'Criar universo de servicos possiveis','Aqui vale quantidade. Primeiro levantamos possibilidades, depois filtramos.','Registre servicos juridicos/extrajudiciais, empresariais e tecnologicos que parecem fazer sentido.'],
['service_limits',2,20,'Separar pode fazer / pode intermediar / nao pode','Essa etapa evita vender algo de forma errada e ajuda a desenhar a rede de parceiros.','Para cada grupo de servico, anote se a CNJP executa, coordena com parceiro ou nao deve oferecer.'],
['target_problems',2,30,'Mapear problemas que o cliente quer resolver','Cliente compra solucao para uma dor, nao nome tecnico de procedimento.','Liste problemas claros: inadimplencia, conflito contratual, perda de leads, falta de sistema etc.'],
['product_shortlist',3,10,'Escolher 8 a 12 produtos principais','Depois do universo amplo, reduzimos para poucos produtos claros.','Quais produtos unem demanda, margem, facilidade de entrega e capacidade atual?'],
['product_models',3,20,'Definir formato de cada produto','Cada produto precisa dizer o que entra, o que nao entra e como termina.','Para cada produto, descreva entrada, entrega, prazo, dependencias e resultado esperado.'],
['recurring_offers',3,30,'Identificar servicos recorrentes','Receita recorrente reduz dependencia de venda nova todo mes.','Quais servicos podem virar mensalidade, pacote, suporte ou manutencao?'],
['workflow',4,10,'Desenhar fluxo do atendimento','Precisamos conseguir apontar onde cada lead esta e qual o proximo passo.','Descreva entrada, triagem, proposta, pagamento, execucao, parceiros, entrega e pos-venda.'],
['responsibilities',4,20,'Definir quem faz o que','Sem dono claro, tarefa vira patrimonio cultural da humanidade e ninguem faz.','Liste responsabilidades da Fran, Daniel, secretaria, parceiros e automacoes.'],
['documents_templates',4,30,'Mapear documentos e modelos necessarios','Produtos repetiveis precisam de checklists, modelos, termos e mensagens padrao.','Liste documentos, formularios, contratos, scripts e modelos necessarios.'],
['systems',5,10,'Definir sistemas e automacoes','Somente agora escolhemos o que vale automatizar.','Liste o que vai para CRM, WhatsApp, agenda, financeiro, automacao e IA.'],
['metrics',5,20,'Definir numeros que importam','Sem medicao nao sabemos se estamos melhorando ou apenas mais ocupados.','Escolha indicadores como leads, conversao, ticket, recorrencia, tempo de atendimento e acordos.'],
['positioning',6,10,'Definir arquitetura e posicionamento das marcas','Precisamos decidir como CNJP institucional e CNJP Tech aparecem sem virar uma feira livre de CNAEs.','Registre a estrutura de marcas e uma frase simples para cada uma.'],
['site_structure',6,20,'Planejar site e paginas de venda','O site vem depois dos produtos.','Liste paginas e landing pages realmente necessarias.'],
['acquisition',7,10,'Escolher canais de aquisicao','Agora entram Google Ads, Meta, prospeccao B2B, parceiros, indicacoes e conteudo.','Para cada produto prioritario, registre onde esse cliente pode ser encontrado.'],
['launch',7,20,'Executar lancamento controlado','Comecar pequeno permite corrigir antes de jogar dinheiro em trafego.','Defina quais 1 a 3 produtos serao lancados primeiro e como medir sucesso.']];}
function seed_cnjp_tasks():void{$s=db()->prepare('INSERT INTO cnjp_tasks(task_key,phase,sort_order,title,explanation,question) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE phase=VALUES(phase),sort_order=VALUES(sort_order),title=VALUES(title),explanation=VALUES(explanation),question=VALUES(question)');foreach(cnjp_task_definitions() as $t)$s->execute($t);}
function seed_known_cnjp_facts():void{
$answer=<<<'TXT'
CNPJ: 49.942.668/0001-47

NÚCLEO CNJP
- 69.11-7/02 - Atividades auxiliares da justiça.
- Natureza jurídica 311-5 - Entidade de Mediação e Arbitragem.

ATIVIDADES SECUNDÁRIAS INFORMADAS
- 9609-2/06 - Serviços de tatuagem e colocação de piercing. [fora do posicionamento CNJP]
- 62.0 - Atividades dos serviços de tecnologia da informação. [Tecnologia; conferir subclasse exata]
- 8599-6/03 - Treinamento em informática. [Tecnologia / educação]
- 951 - Reparação e manutenção de equipamentos de informática e comunicação. [conferir subclasse exata]
- 7319-0/03 - Marketing direto. [Marketing]
- 5819-1/00 - Edição de cadastros, listas e outros produtos digitais. [Digital]
- 4789-0/99 - Comércio varejista. [Paralelo]
- 9602-5/02 - Atividades de estética e outros serviços de cuidados com a beleza. [Paralelo]
- 8599-6/04 - Cursos de aperfeiçoamento jurídico, aprendizagem, treinamento, cursos online e ebooks. [Educação / CNJP]
- 8650-0/05 - Atividades de terapia ocupacional. [Paralelo / depende de habilitação]
- 5611-2/03 - Lanchonete. [Paralelo]
- 6202-3/00 - Desenvolvimento de software. [Daniel / CNJP Tech]
TXT;
$notes='Dados fornecidos por Daniel em 29/07/2026. 62.0 e 951 ainda precisam da subclasse completa do cartão CNPJ.';
$s=db()->prepare("UPDATE cnjp_tasks SET status='done',answer=?,notes=?,updated_by=?,updated_at=NOW() WHERE task_key='company_cnaes' AND (answer IS NULL OR TRIM(answer)='')");$s->execute([$answer,$notes,$_SESSION['user_id']??null]);}
function row_task(string $key):?array{$s=db()->prepare('SELECT status,answer,notes FROM cnjp_tasks WHERE task_key=?');$s->execute([$key]);$r=$s->fetch();return $r?:null;}
function history_add(string $type,string $key,string $action,?array $before,?array $after,?int $source=null):void{$s=db()->prepare('INSERT INTO cnjp_history(entity_type,entity_key,action_type,before_json,after_json,user_id,source_history_id) VALUES(?,?,?,?,?,?,?)');$s->execute([$type,$key,$action,$before?json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$after?json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$_SESSION['user_id']??null,$source]);}
function cnjp_bootstrap(array $user):never{$tasks=db()->query('SELECT t.*,u.name updated_by_name FROM cnjp_tasks t LEFT JOIN users u ON u.id=t.updated_by ORDER BY phase,sort_order,id')->fetchAll();$notes=db()->query('SELECT * FROM cnjp_project_notes')->fetchAll();$nm=[];foreach($notes as $n)$nm[$n['note_key']]=$n['content'];$dec=db()->query('SELECT d.*,u.name created_by_name FROM cnjp_decisions d LEFT JOIN users u ON u.id=d.created_by ORDER BY d.id DESC')->fetchAll();json_response(['ok'=>true,'user'=>$user,'csrf'=>csrf_token(),'tasks'=>$tasks,'notes'=>$nm,'decisions'=>$dec]);}
function cnjp_history():never{$q="SELECT h.*,u.name user_name FROM cnjp_history h LEFT JOIN users u ON u.id=h.user_id ORDER BY h.id DESC LIMIT 300";$rows=db()->query($q)->fetchAll();json_response(['ok'=>true,'history'=>$rows]);}
function save_task():never{$i=json_input();$k=trim((string)($i['task_key']??''));$st=(string)($i['status']??'todo');if(!in_array($st,['todo','doing','done','blocked'],true))$st='todo';$before=row_task($k);if(!$before)json_response(['ok'=>false,'message'=>'Tarefa nao encontrada.'],404);$after=['status'=>$st,'answer'=>trim((string)($i['answer']??'')),'notes'=>trim((string)($i['notes']??''))];if($before!=$after){$s=db()->prepare('UPDATE cnjp_tasks SET status=?,answer=?,notes=?,updated_by=?,updated_at=NOW() WHERE task_key=?');$s->execute([$after['status'],$after['answer'],$after['notes'],$_SESSION['user_id'],$k]);history_add('task',$k,'update',$before,$after);}json_response(['ok'=>true,'saved_at'=>date('c')]);}
function save_project_note():never{$i=json_input();$k=preg_replace('/[^a-z0-9_\-]/i','',(string)($i['note_key']??'general'))?:'general';$content=trim((string)($i['content']??''));$s=db()->prepare('SELECT content FROM cnjp_project_notes WHERE note_key=?');$s->execute([$k]);$r=$s->fetch();$before=$r?['content'=>$r['content']]:null;$after=['content'=>$content];$st=db()->prepare('INSERT INTO cnjp_project_notes(note_key,content,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE content=VALUES(content),updated_by=VALUES(updated_by),updated_at=NOW()');$st->execute([$k,$content,$_SESSION['user_id']]);if($before!=$after)history_add('note',$k,$before?'update':'create',$before,$after);json_response(['ok'=>true]);}
function decision_row(int $id):?array{$s=db()->prepare('SELECT id,title,description,decision_status,created_by FROM cnjp_decisions WHERE id=?');$s->execute([$id]);$r=$s->fetch();return $r?:null;}
function save_decision():never{$i=json_input();$id=(int)($i['id']??0);$title=trim((string)($i['title']??''));$desc=trim((string)($i['description']??''));$status=(string)($i['decision_status']??'open');if($title==='')json_response(['ok'=>false,'message'=>'Informe o titulo da decisao.'],422);if(!in_array($status,['open','decided','discarded'],true))$status='open';$before=$id?decision_row($id):null;if($id){$s=db()->prepare('UPDATE cnjp_decisions SET title=?,description=?,decision_status=?,updated_at=NOW() WHERE id=?');$s->execute([$title,$desc,$status,$id]);}else{$s=db()->prepare('INSERT INTO cnjp_decisions(title,description,decision_status,created_by) VALUES(?,?,?,?)');$s->execute([$title,$desc,$status,$_SESSION['user_id']]);$id=(int)db()->lastInsertId();}$after=decision_row($id);history_add('decision',(string)$id,$before?'update':'create',$before,$after);json_response(['ok'=>true,'id'=>$id]);}
function delete_decision():never{$id=(int)(json_input()['id']??0);$before=decision_row($id);if(!$before)json_response(['ok'=>false,'message'=>'Decisao nao encontrada.'],404);db()->prepare('DELETE FROM cnjp_decisions WHERE id=?')->execute([$id]);history_add('decision',(string)$id,'delete',$before,null);json_response(['ok'=>true]);}
function undo_history():never{$id=(int)(json_input()['history_id']??0);$s=db()->prepare('SELECT * FROM cnjp_history WHERE id=?');$s->execute([$id]);$h=$s->fetch();if(!$h)json_response(['ok'=>false,'message'=>'Historico nao encontrado.'],404);$before=$h['before_json']?json_decode($h['before_json'],true):null;$type=$h['entity_type'];$key=$h['entity_key'];if($type==='task'){if(!$before)json_response(['ok'=>false,'message'=>'Esta alteracao nao possui estado anterior.'],422);$current=row_task($key);$u=db()->prepare('UPDATE cnjp_tasks SET status=?,answer=?,notes=?,updated_by=?,updated_at=NOW() WHERE task_key=?');$u->execute([$before['status'],$before['answer'],$before['notes'],$_SESSION['user_id'],$key]);history_add('task',$key,'undo',$current,$before,$id);}elseif($type==='note'){ $q=db()->prepare('SELECT content FROM cnjp_project_notes WHERE note_key=?');$q->execute([$key]);$r=$q->fetch();$current=$r?['content'=>$r['content']]:null;if($before===null){db()->prepare('DELETE FROM cnjp_project_notes WHERE note_key=?')->execute([$key]);}else{$u=db()->prepare('INSERT INTO cnjp_project_notes(note_key,content,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE content=VALUES(content),updated_by=VALUES(updated_by),updated_at=NOW()');$u->execute([$key,$before['content'],$_SESSION['user_id']]);}history_add('note',$key,'undo',$current,$before,$id);}elseif($type==='decision'){ $did=(int)$key;$current=decision_row($did);if($before===null){if($current)db()->prepare('DELETE FROM cnjp_decisions WHERE id=?')->execute([$did]);}else{$u=db()->prepare('INSERT INTO cnjp_decisions(id,title,description,decision_status,created_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),decision_status=VALUES(decision_status),created_by=VALUES(created_by),updated_at=NOW()');$u->execute([$did,$before['title'],$before['description'],$before['decision_status'],$before['created_by']]);}history_add('decision',$key,'undo',$current,$before,$id);}json_response(['ok'=>true]);}
