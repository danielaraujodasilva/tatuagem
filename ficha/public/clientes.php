<?php
require_once __DIR__ . '/../../auth/auth.php';
require_staff();
require __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../../includes/app_menu.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function get_param(string $key, string $default = ''): string
{
    return trim((string)($_GET[$key] ?? $default));
}

function sort_link(string $field, string $label, array $current): string
{
    $dir = ($current['sort'] === $field && $current['dir'] === 'asc') ? 'desc' : 'asc';
    $params = $_GET;
    $params['sort'] = $field;
    $params['dir'] = $dir;
    $params['page'] = 1;
    $mark = $current['sort'] === $field ? ($current['dir'] === 'asc' ? ' ↑' : ' ↓') : '';
    return '<a class="ficha-sort-link" href="?' . h(http_build_query($params)) . '">' . h($label . $mark) . '</a>';
}

function bind_stmt_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') {
        return;
    }

    $refs = [$types];
    foreach ($params as $key => &$value) {
        $refs[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

$q = get_param('q');
$comTatuagens = get_param('com_tatuagens');
$usoImagem = get_param('uso_imagem');
$sort = get_param('sort', 'nome');
$dir = strtolower(get_param('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
$page = max(1, (int)get_param('page', '1'));
$perPage = (int)get_param('per_page', '15');
$allowedPerPage = [10, 15, 25, 50];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 15;
}

$sortMap = [
    'nome' => 'c.nome',
    'created_at' => 'c.created_at',
    'total_tatuagens' => 'total_tatuagens',
    'ultima_tatuagem' => 'ultima_tatuagem',
    'valor_total' => 'valor_total',
];
if (!isset($sortMap[$sort])) {
    $sort = 'nome';
}

$where = [];
$params = [];
$types = '';
if ($q !== '') {
    $where[] = '(c.nome LIKE ? OR c.telefone LIKE ? OR c.email LIKE ? OR c.instagram_cliente LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}
if ($usoImagem !== '') {
    $where[] = 'c.autorizou_uso_imagem = ?';
    $params[] = $usoImagem === 'sim' ? 1 : 0;
    $types .= 'i';
}

$having = [];
if ($comTatuagens === 'sim') {
    $having[] = 'total_tatuagens > 0';
} elseif ($comTatuagens === 'nao') {
    $having[] = 'total_tatuagens = 0';
}

$baseSql = '
    FROM clientes c
    LEFT JOIN tatuagens t ON t.cliente_id = c.id
    ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
    GROUP BY c.id
    ' . ($having ? 'HAVING ' . implode(' AND ', $having) : '');

$countSql = 'SELECT COUNT(*) AS total FROM (
    SELECT c.id, COUNT(t.id) AS total_tatuagens
    ' . $baseSql . '
) base';
$countStmt = $conn->prepare($countSql);
bind_stmt_params($countStmt, $types, $params);
$countStmt->execute();
$totalClientes = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, (int)ceil($totalClientes / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = '
    SELECT
        c.*,
        COUNT(t.id) AS total_tatuagens,
        COALESCE(SUM(t.valor), 0) AS valor_total,
        MAX(t.data_tatuagem) AS ultima_tatuagem
    ' . $baseSql . '
    ORDER BY ' . $sortMap[$sort] . ' ' . strtoupper($dir) . ', c.nome ASC
    LIMIT ? OFFSET ?';

$stmt = $conn->prepare($sql);
$queryParams = $params;
$queryTypes = $types . 'ii';
$queryParams[] = $perPage;
$queryParams[] = $offset;
bind_stmt_params($stmt, $queryTypes, $queryParams);
$stmt->execute();
$clientes = $stmt->get_result();

$stats = $conn->query('
    SELECT
        COUNT(*) AS total,
        SUM(autorizou_uso_imagem = 1) AS autorizados,
        SUM(total_tatuagens > 0) AS recorrentes
    FROM (
        SELECT c.id, c.autorizou_uso_imagem, COUNT(t.id) AS total_tatuagens
        FROM clientes c
        LEFT JOIN tatuagens t ON t.cliente_id = c.id
        GROUP BY c.id
    ) base
')->fetch_assoc();

$sortState = ['sort' => $sort, 'dir' => $dir];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clientes e Tatuagens</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/style.css" rel="stylesheet">
  <style>
    .clientes-filter-grid { display: grid; grid-template-columns: minmax(220px, 1.6fr) repeat(4, minmax(150px, 1fr)); gap: 12px; }
    .ficha-sort-link { color: #d8f3ff; text-decoration: none; }
    .ficha-sort-link:hover { color: #38bdf8; }
    .clientes-pagination { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 18px; }
    .clientes-pages { display: flex; flex-wrap: wrap; gap: 6px; }
    .clientes-pages a, .clientes-pages span {
      min-width: 38px;
      min-height: 38px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 11px;
      border: 1px solid rgba(148, 163, 184, 0.2);
      color: #dbeafe;
      text-decoration: none;
      font-weight: 700;
    }
    .clientes-pages span { background: #38bdf8; color: #05111d; border-color: #38bdf8; }
    @media (max-width: 1100px) { .clientes-filter-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 640px) { .clientes-filter-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame">
      <header class="ficha-hero">
        <span class="ficha-kicker">Ficha de cliente</span>
        <h1>Clientes e historico de tatuagens</h1>
        <p>Busque, filtre, ordene e abra rapidamente os detalhes ou o proximo agendamento de cada cliente.</p>
        <?php app_menu_render('clientes'); ?>
      </header>

      <div class="ficha-content">
        <div class="ficha-stats mb-4">
          <div class="ficha-stat"><span>Total na base</span><strong><?php echo (int)$stats['total']; ?></strong></div>
          <div class="ficha-stat"><span>Com tatuagem</span><strong><?php echo (int)$stats['recorrentes']; ?></strong></div>
          <div class="ficha-stat"><span>Uso de imagem</span><strong><?php echo (int)$stats['autorizados']; ?></strong></div>
        </div>

        <form method="get" class="ficha-panel mb-4">
          <div class="clientes-filter-grid">
            <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="Buscar nome, telefone, email ou Instagram">
            <select class="form-select" name="com_tatuagens">
              <option value="">Todos os clientes</option>
              <option value="sim" <?php echo $comTatuagens === 'sim' ? 'selected' : ''; ?>>Com tatuagens</option>
              <option value="nao" <?php echo $comTatuagens === 'nao' ? 'selected' : ''; ?>>Sem tatuagens</option>
            </select>
            <select class="form-select" name="uso_imagem">
              <option value="">Uso de imagem</option>
              <option value="sim" <?php echo $usoImagem === 'sim' ? 'selected' : ''; ?>>Autorizado</option>
              <option value="nao" <?php echo $usoImagem === 'nao' ? 'selected' : ''; ?>>Nao autorizado</option>
            </select>
            <select class="form-select" name="per_page">
              <?php foreach ($allowedPerPage as $option): ?>
                <option value="<?php echo $option; ?>" <?php echo $perPage === $option ? 'selected' : ''; ?>><?php echo $option; ?> por pagina</option>
              <?php endforeach; ?>
            </select>
            <div class="d-flex gap-2">
              <button class="btn ficha-btn ficha-btn-primary flex-fill">Filtrar</button>
              <a class="btn ficha-btn ficha-btn-secondary" href="clientes.php">Limpar</a>
            </div>
          </div>
        </form>

        <div class="ficha-table-panel">
          <div class="ficha-table-head">
            <div>
              <h2 class="ficha-panel-title mb-1">Base de clientes</h2>
              <p class="ficha-copy mb-0">Mostrando <?php echo $clientes->num_rows; ?> de <?php echo $totalClientes; ?> clientes encontrados.</p>
            </div>
            <div class="ficha-chip">Pagina <?php echo $page; ?> de <?php echo $totalPages; ?></div>
          </div>
          <div class="ficha-table-wrap">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th><?php echo sort_link('nome', 'Cliente', $sortState); ?></th>
                  <th>Contato</th>
                  <th><?php echo sort_link('total_tatuagens', 'Tatuagens', $sortState); ?></th>
                  <th><?php echo sort_link('ultima_tatuagem', 'Ultima', $sortState); ?></th>
                  <th><?php echo sort_link('valor_total', 'Valor total', $sortState); ?></th>
                  <th>Acoes</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($clientes->num_rows === 0): ?>
                  <tr><td colspan="6" class="ficha-empty">Nenhum cliente encontrado com esses filtros.</td></tr>
                <?php endif; ?>
                <?php while ($cliente = $clientes->fetch_assoc()): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?php echo h($cliente['nome']); ?></div>
                      <div class="ficha-muted"><?php echo h((string)$cliente['instagram_cliente']); ?></div>
                    </td>
                    <td>
                      <a class="ficha-card-link" href="https://wa.me/<?php echo preg_replace('/\D/', '', $cliente['telefone']); ?>" target="_blank">
                        <?php echo h($cliente['telefone']); ?>
                      </a>
                      <div class="ficha-muted"><?php echo h($cliente['email']); ?></div>
                    </td>
                    <td><span class="ficha-chip"><?php echo (int)$cliente['total_tatuagens']; ?> registradas</span></td>
                    <td><?php echo $cliente['ultima_tatuagem'] ? date('d/m/Y', strtotime($cliente['ultima_tatuagem'])) : '-'; ?></td>
                    <td><?php echo 'R$ ' . number_format((float)$cliente['valor_total'], 2, ',', '.'); ?></td>
                    <td>
                      <div class="d-flex gap-2 flex-wrap">
                        <button class="btn ficha-btn ficha-btn-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#tatuagens-<?php echo (int)$cliente['id']; ?>">Ver tatuagens</button>
                        <a class="btn ficha-btn ficha-btn-secondary btn-sm" href="../detalhes_cliente.php?id=<?php echo (int)$cliente['id']; ?>">Detalhes</a>
                        <a class="btn ficha-btn ficha-btn-primary btn-sm" href="cadastrar_tatuagem.php?cliente_id=<?php echo (int)$cliente['id']; ?>">Novo agendamento</a>
                      </div>
                    </td>
                  </tr>
                  <tr class="collapse" id="tatuagens-<?php echo (int)$cliente['id']; ?>">
                    <td colspan="6">
                      <div class="ficha-panel mt-2">
                        <h3 class="ficha-panel-title">Tatuagens de <?php echo h($cliente['nome']); ?></h3>
                        <div class="ficha-table-wrap">
                          <table class="table align-middle mb-0">
                            <thead>
                              <tr>
                                <th>Descricao</th>
                                <th>Valor</th>
                                <th>Data</th>
                                <th>Horario</th>
                                <th>Status</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                              $stmtTat = $conn->prepare('SELECT descricao, valor, data_tatuagem, hora_inicio, hora_fim, status FROM tatuagens WHERE cliente_id = ? ORDER BY data_tatuagem DESC, hora_inicio DESC');
                              $clienteId = (int)$cliente['id'];
                              $stmtTat->bind_param('i', $clienteId);
                              $stmtTat->execute();
                              $tatuagens = $stmtTat->get_result();
                              ?>
                              <?php if ($tatuagens->num_rows > 0): ?>
                                <?php while ($tatuagem = $tatuagens->fetch_assoc()): ?>
                                  <tr>
                                    <td><?php echo h($tatuagem['descricao']); ?></td>
                                    <td>R$ <?php echo number_format((float)$tatuagem['valor'], 2, ',', '.'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($tatuagem['data_tatuagem'])); ?></td>
                                    <td><?php echo h(substr((string)$tatuagem['hora_inicio'], 0, 5) . ' - ' . substr((string)$tatuagem['hora_fim'], 0, 5)); ?></td>
                                    <td><span class="ficha-chip"><?php echo h($tatuagem['status']); ?></span></td>
                                  </tr>
                                <?php endwhile; ?>
                              <?php else: ?>
                                <tr><td colspan="5" class="ficha-empty">Nenhuma tatuagem encontrada para este cliente.</td></tr>
                              <?php endif; ?>
                              <?php $stmtTat->close(); ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>

          <?php if ($totalPages > 1): ?>
            <div class="clientes-pagination">
              <div class="ficha-muted">Pagina <?php echo $page; ?> de <?php echo $totalPages; ?></div>
              <div class="clientes-pages">
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                    $paramsPage = $_GET;
                    $paramsPage['page'] = $p;
                    if ($p === $page):
                ?>
                      <span><?php echo $p; ?></span>
                    <?php else: ?>
                      <a href="?<?php echo h(http_build_query($paramsPage)); ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$stmt->close();
?>
