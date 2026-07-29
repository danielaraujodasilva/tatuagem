<?php
declare(strict_types=1);

require __DIR__ . '/../plan/includes/bootstrap.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'login') {
        verify_csrf();
        $input = json_input();
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([trim((string)($input['email'] ?? ''))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify((string)($input['password'] ?? ''), $user['password_hash'])) {
            json_response(['ok' => false, 'message' => 'E-mail ou senha invalidos.'], 422);
        }
        $_SESSION['user_id'] = (int)$user['id'];
        audit('login', 'user', (int)$user['id']);
        json_response(['ok' => true]);
    }

    $user = require_auth();
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        verify_csrf();
    }

    ensure_cnjp_schema();
    seed_cnjp_tasks();

    match ($action) {
        'bootstrap' => cnjp_bootstrap($user),
        'save_task' => save_task(),
        'save_project_note' => save_project_note(),
        'save_decision' => save_decision(),
        'delete_decision' => delete_decision(),
        default => json_response(['ok' => false, 'message' => 'Acao desconhecida.'], 404),
    };
} catch (Throwable $e) {
    global $config;
    $payload = ['ok' => false, 'message' => 'Erro interno.'];
    if (!empty($config['debug'])) {
        $payload['detail'] = $e->getMessage();
    }
    json_response($payload, 500);
}

function ensure_cnjp_schema(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS cnjp_tasks (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        task_key VARCHAR(100) NOT NULL UNIQUE,
        phase TINYINT UNSIGNED NOT NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        title VARCHAR(255) NOT NULL,
        explanation TEXT NULL,
        question TEXT NULL,
        status ENUM('todo','doing','done','blocked') NOT NULL DEFAULT 'todo',
        answer MEDIUMTEXT NULL,
        notes MEDIUMTEXT NULL,
        updated_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cnjp_phase (phase, sort_order),
        CONSTRAINT fk_cnjp_task_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS cnjp_project_notes (
        note_key VARCHAR(80) PRIMARY KEY,
        content MEDIUMTEXT NULL,
        updated_by INT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_cnjp_note_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS cnjp_decisions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        decision_status ENUM('open','decided','discarded') NOT NULL DEFAULT 'open',
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_cnjp_decision_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function cnjp_task_definitions(): array
{
    return [
        ['company_cnaes',1,10,'Levantar CNPJ e CNAEs atuais','Precisamos saber o que a empresa ja possui formalmente antes de inventar novos bracos. O objetivo nao e interpretar juridicamente cada CNAE agora, apenas registrar a lista completa e o que voces acreditam que cada um cobre.','Cole aqui os CNAEs atuais, codigo + descricao, e marque quais parecem ser da Fran, da tecnologia ou apenas cadastrais.'],
        ['fran_qualifications',1,20,'Mapear formacao e habilitacoes da Fran','Essa resposta define o limite real do que pode ser oferecido diretamente pela CNJP e o que dependera de advogado, contador, cartorio, perito ou outro parceiro.','Liste cursos, certificados, registros, experiencia, formacao e habilitacoes da Fran.'],
        ['past_services',1,30,'Listar tudo que a CNJP ja fez','Antes de criar produtos novos, precisamos enxergar o que ja foi vendido, entregue e aprendido na pratica.','Liste casos, tipos de atendimento, documentos, acordos, clientes, demandas recorrentes e servicos que a CNJP ja executou.'],
        ['tech_capabilities',1,40,'Mapear o que Daniel entrega em tecnologia','Nao queremos vender tecnologia abstrata. Queremos saber o que ja pode ser entregue hoje com confianca.','Liste o que voce ja sabe entregar: sites, sistemas, CRM, WhatsApp, IA, automacoes, integracoes, servidores, hospedagem, analytics etc.'],
        ['current_assets',1,50,'Inventariar ativos existentes','Site, dominios, sistemas, WhatsApp, CRM, contatos, base de clientes, modelos de documento e parceiros economizam meses quando sabemos que existem.','Liste tudo que a CNJP ja possui e pode reaproveitar.'],
        ['partners',1,60,'Mapear parceiros e lacunas','Uma empresa nao precisa executar tudo internamente. Precisamos identificar quem entra quando a demanda ultrapassa a competencia da CNJP.','Liste parceiros atuais e os profissionais que ainda precisamos encontrar.'],

        ['service_universe',2,10,'Criar universo de servicos possiveis','Aqui vale quantidade. Vamos levantar todas as possibilidades antes de selecionar as melhores.','Registre servicos juridicos/extrajudiciais, empresariais e tecnologicos que parecem fazer sentido.'],
        ['service_limits',2,20,'Separar pode fazer / pode intermediar / nao pode','Essa etapa evita vender algo de forma errada e ajuda a desenhar a rede de parceiros.','Para cada grupo de servico, anote se a CNJP executa, coordena com parceiro ou nao deve oferecer.'],
        ['target_problems',2,30,'Mapear problemas que o cliente quer resolver','Cliente compra solucao para uma dor, nao nome tecnico de procedimento. Precisamos traduzir servicos para problemas reais.','Ex.: empresa com inadimplencia; locador com inquilino; negocio perdendo leads no WhatsApp. Acrescente os problemas mais claros.'],

        ['product_shortlist',3,10,'Escolher 8 a 12 produtos principais','Depois do universo amplo, reduzimos para poucos produtos comercialmente claros.','Quais produtos parecem ter melhor combinacao de demanda, margem, facilidade de entrega e capacidade atual?'],
        ['product_models',3,20,'Definir formato de cada produto','Cada produto precisa dizer o que entra, o que nao entra e como termina.','Para cada produto escolhido, descreva entrada, entrega, prazo aproximado, dependencias e resultado esperado.'],
        ['recurring_offers',3,30,'Identificar servicos recorrentes','Receita recorrente reduz dependencia de venda nova todo mes. Tecnologia e B2B podem ser especialmente fortes aqui.','Quais servicos podem virar mensalidade, pacote de casos, suporte ou manutencao?'],

        ['workflow',4,10,'Desenhar fluxo do atendimento','Precisamos conseguir apontar onde cada lead esta e qual e o proximo passo.','Descreva o caminho do lead: entrada, triagem, proposta, pagamento, execucao, parceiros, entrega e pos-venda.'],
        ['responsibilities',4,20,'Definir quem faz o que','Sem dono claro, tarefa vira patrimonio cultural da humanidade e ninguem faz.','Liste responsabilidades da Fran, Daniel, secretaria, parceiros e automacoes.'],
        ['documents_templates',4,30,'Mapear documentos e modelos necessarios','Produtos repetiveis precisam de checklists, modelos, termos e mensagens padrao.','Liste os documentos, formularios, contratos, scripts e modelos que cada produto exige.'],

        ['systems',5,10,'Definir sistemas e automacoes','Somente agora escolhemos o que vale automatizar. Automacao de processo ruim apenas faz a bagunca acontecer mais rapido.','Liste o que vai para CRM, WhatsApp, agenda, financeiro, automacao e IA.'],
        ['metrics',5,20,'Definir numeros que importam','Sem medicao nao sabemos se a CNJP esta melhorando ou apenas mais ocupada.','Escolha indicadores: leads, conversao, ticket, recorrencia, tempo de atendimento, acordos, inadimplencia etc.'],

        ['positioning',6,10,'Definir arquitetura e posicionamento das marcas','Precisamos decidir como CNJP institucional, CNJP Tech e eventuais verticais aparecem para o publico sem virar uma feira livre de servicos.','Registre a estrutura de marcas e a frase simples que explica o que cada uma resolve.'],
        ['site_structure',6,20,'Planejar site e paginas de venda','O site vem depois dos produtos. Cada pagina deve responder a uma dor e levar a uma acao.','Liste as paginas e landing pages realmente necessarias.'],

        ['acquisition',7,10,'Escolher canais de aquisicao','Agora sim entram Google Ads, Meta, prospeccao B2B, parceiros, indicacoes e conteudo.','Para cada produto prioritario, registre onde esse cliente pode ser encontrado.'],
        ['launch',7,20,'Executar lancamento controlado','Comecar pequeno permite corrigir oferta e operacao antes de jogar dinheiro em trafego.','Defina quais 1 a 3 produtos serao lancados primeiro e o criterio para considerar o teste bem-sucedido.'],
    ];
}

function seed_cnjp_tasks(): void
{
    $stmt = db()->prepare('INSERT INTO cnjp_tasks (task_key, phase, sort_order, title, explanation, question)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE phase=VALUES(phase), sort_order=VALUES(sort_order), title=VALUES(title), explanation=VALUES(explanation), question=VALUES(question)');
    foreach (cnjp_task_definitions() as $task) {
        $stmt->execute($task);
    }
}

function cnjp_bootstrap(array $user): never
{
    $tasks = db()->query('SELECT t.*, u.name updated_by_name FROM cnjp_tasks t LEFT JOIN users u ON u.id=t.updated_by ORDER BY phase, sort_order, id')->fetchAll();
    $notes = db()->query('SELECT * FROM cnjp_project_notes')->fetchAll();
    $notesMap = [];
    foreach ($notes as $note) {
        $notesMap[$note['note_key']] = $note['content'];
    }
    $decisions = db()->query('SELECT d.*, u.name created_by_name FROM cnjp_decisions d LEFT JOIN users u ON u.id=d.created_by ORDER BY d.id DESC')->fetchAll();
    json_response(['ok'=>true,'user'=>$user,'csrf'=>csrf_token(),'tasks'=>$tasks,'notes'=>$notesMap,'decisions'=>$decisions]);
}

function save_task(): never
{
    $input = json_input();
    $key = trim((string)($input['task_key'] ?? ''));
    $status = (string)($input['status'] ?? 'todo');
    if (!in_array($status, ['todo','doing','done','blocked'], true)) $status = 'todo';
    $stmt = db()->prepare('UPDATE cnjp_tasks SET status=?, answer=?, notes=?, updated_by=?, updated_at=NOW() WHERE task_key=?');
    $stmt->execute([$status, trim((string)($input['answer'] ?? '')), trim((string)($input['notes'] ?? '')), $_SESSION['user_id'], $key]);
    if ($stmt->rowCount() < 1) {
        $exists = db()->prepare('SELECT id FROM cnjp_tasks WHERE task_key=?');
        $exists->execute([$key]);
        if (!$exists->fetchColumn()) json_response(['ok'=>false,'message'=>'Tarefa nao encontrada.'],404);
    }
    audit('update', 'cnjp_task', null, ['task_key'=>$key,'status'=>$status]);
    json_response(['ok'=>true,'saved_at'=>date('c')]);
}

function save_project_note(): never
{
    $input = json_input();
    $key = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['note_key'] ?? 'general')) ?: 'general';
    $content = trim((string)($input['content'] ?? ''));
    $stmt = db()->prepare('INSERT INTO cnjp_project_notes (note_key, content, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content=VALUES(content), updated_by=VALUES(updated_by), updated_at=NOW()');
    $stmt->execute([$key,$content,$_SESSION['user_id']]);
    audit('update', 'cnjp_project_note', null, ['note_key'=>$key]);
    json_response(['ok'=>true]);
}

function save_decision(): never
{
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $title = trim((string)($input['title'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $status = (string)($input['decision_status'] ?? 'open');
    if ($title === '') json_response(['ok'=>false,'message'=>'Informe o titulo da decisao.'],422);
    if (!in_array($status,['open','decided','discarded'],true)) $status='open';
    if ($id > 0) {
        $stmt=db()->prepare('UPDATE cnjp_decisions SET title=?, description=?, decision_status=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([$title,$description,$status,$id]);
    } else {
        $stmt=db()->prepare('INSERT INTO cnjp_decisions (title,description,decision_status,created_by) VALUES (?,?,?,?)');
        $stmt->execute([$title,$description,$status,$_SESSION['user_id']]);
        $id=(int)db()->lastInsertId();
    }
    audit('update','cnjp_decision',$id,['status'=>$status]);
    json_response(['ok'=>true,'id'=>$id]);
}

function delete_decision(): never
{
    $id=(int)(json_input()['id'] ?? 0);
    db()->prepare('DELETE FROM cnjp_decisions WHERE id=?')->execute([$id]);
    audit('delete','cnjp_decision',$id);
    json_response(['ok'=>true]);
}
