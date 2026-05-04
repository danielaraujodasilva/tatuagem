<?php

if (!function_exists('app_menu_render')) {
    function app_menu_render(string $active = ''): void
    {
        $user = function_exists('current_user') ? current_user() : null;
        $isAdmin = function_exists('auth_has_role') && auth_has_role(['adm']);
        $items = [
            ['key' => 'crm', 'label' => 'CRM Pipeline', 'url' => auth_url('/crm/index.php'), 'roles' => ['funcionario', 'adm']],
            ['key' => 'relatorios', 'label' => 'Relatorios', 'url' => auth_url('/crm/relatorios.php'), 'roles' => ['funcionario', 'adm']],
            ['key' => 'ficha', 'label' => 'Nova ficha', 'url' => auth_url('/ficha/index.php'), 'roles' => ['funcionario', 'adm']],
            ['key' => 'clientes', 'label' => 'Clientes', 'url' => auth_url('/ficha/public/clientes.php'), 'roles' => ['funcionario', 'adm']],
            ['key' => 'agenda', 'label' => 'Agenda', 'url' => auth_url('/ficha/agenda/'), 'roles' => ['funcionario', 'adm']],
            ['key' => 'mapa', 'label' => 'Mapa', 'url' => auth_url('/ficha/mapa_clientes.php'), 'roles' => ['funcionario', 'adm']],
            ['key' => 'orcamento', 'label' => 'Orcamentos', 'url' => auth_url('/orcamento/admin.php'), 'roles' => ['adm']],
            ['key' => 'usuarios', 'label' => 'Usuarios', 'url' => auth_url('/auth/usuarios.php'), 'roles' => ['adm']],
            ['key' => 'config', 'label' => 'Configuracoes', 'url' => auth_url('/crm/configuracoes.php'), 'roles' => ['adm']],
        ];

        $role = (string)($user['role'] ?? '');
        $visible = array_values(array_filter($items, static function (array $item) use ($role, $isAdmin): bool {
            if ($isAdmin) {
                return true;
            }

            return in_array($role, $item['roles'], true);
        }));

        $activeLabel = 'Menu';
        foreach ($visible as $item) {
            if ($item['key'] === $active) {
                $activeLabel = $item['label'];
                break;
            }
        }
        ?>
        <style>
            .app-menu-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                width: 100%;
                margin-top: 18px;
                padding: 12px;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 18px;
                background: rgba(15, 23, 42, 0.86);
            }
            .app-menu-brand {
                min-width: 0;
                color: #f8fafc;
                font-weight: 800;
                line-height: 1.15;
            }
            .app-menu-brand small {
                display: block;
                margin-top: 4px;
                color: #94a3b8;
                font-size: 0.78rem;
                font-weight: 600;
            }
            .app-menu-dropdown {
                position: relative;
                flex: 0 0 auto;
            }
            .app-menu-dropdown summary {
                display: inline-flex;
                align-items: center;
                gap: 9px;
                min-height: 42px;
                padding: 0 16px;
                border-radius: 13px;
                border: 1px solid rgba(56, 189, 248, 0.32);
                color: #e0f2fe;
                background: rgba(56, 189, 248, 0.11);
                font-weight: 800;
                cursor: pointer;
                list-style: none;
                user-select: none;
            }
            .app-menu-dropdown summary::-webkit-details-marker { display: none; }
            .app-menu-dropdown summary::before {
                content: "";
                width: 18px;
                height: 12px;
                background:
                    linear-gradient(#e0f2fe, #e0f2fe) 0 0 / 18px 2px no-repeat,
                    linear-gradient(#e0f2fe, #e0f2fe) 0 5px / 18px 2px no-repeat,
                    linear-gradient(#e0f2fe, #e0f2fe) 0 10px / 18px 2px no-repeat;
            }
            .app-menu-panel {
                position: absolute;
                right: 0;
                top: calc(100% + 10px);
                z-index: 1000;
                display: grid;
                gap: 6px;
                width: min(86vw, 300px);
                padding: 10px;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 16px;
                background: #0f172a;
                box-shadow: 0 26px 70px rgba(0, 0, 0, 0.38);
            }
            .app-menu-link {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                min-height: 42px;
                padding: 0 12px;
                border-radius: 12px;
                color: #dbeafe;
                text-decoration: none;
                font-weight: 700;
            }
            .app-menu-link:hover,
            .app-menu-link.is-active {
                color: #04111d;
                background: #38bdf8;
            }
            .app-menu-divider {
                height: 1px;
                margin: 4px 0;
                background: rgba(148, 163, 184, 0.18);
            }
            @media (max-width: 640px) {
                .app-menu-bar { align-items: stretch; flex-direction: column; }
                .app-menu-dropdown summary { justify-content: center; width: 100%; }
                .app-menu-panel { left: 0; right: auto; width: 100%; }
            }
        </style>
        <nav class="app-menu-bar" aria-label="Menu principal">
            <div class="app-menu-brand">
                <?php echo htmlspecialchars($activeLabel, ENT_QUOTES, 'UTF-8'); ?>
                <small><?php echo htmlspecialchars((string)($user['nome'] ?? $user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
            <details class="app-menu-dropdown">
                <summary>Menu</summary>
                <div class="app-menu-panel">
                    <?php foreach ($visible as $item): ?>
                        <a class="app-menu-link <?php echo $item['key'] === $active ? 'is-active' : ''; ?>"
                           href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span aria-hidden="true">›</span>
                        </a>
                    <?php endforeach; ?>
                    <div class="app-menu-divider"></div>
                    <a class="app-menu-link" href="<?php echo htmlspecialchars(auth_url('/auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
                </div>
            </details>
        </nav>
        <?php
    }
}
