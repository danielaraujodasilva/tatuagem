export const TECH_SERVICES = {
  id: 'tecnologia',
  title: 'Tecnologia & Automação',
  icon: 'terminal',
  description: 'Soluções digitais que podem ser implantadas, personalizadas e mantidas para pequenos negócios e operações jurídicas.',
  tags: ['green'],
  metrics: { score: 96, ease: 95, revenue: 85, recurring: 90, complexity: 42 },
  children: [
    {
      title: 'Sites & Presença Digital',
      description: 'Sites rápidos, profissionais e focados em gerar contato, orçamento ou agendamento.',
      metrics: { score: 97, ease: 98, revenue: 78, recurring: 68, complexity: 22 },
      children: [
        { title: 'Site institucional', description: 'Site completo para apresentar empresa, serviços, contatos e diferenciais.', tags: ['green'], metrics: { score: 98, ease: 100, revenue: 80, recurring: 55, complexity: 15 } },
        { title: 'Landing page de captação', description: 'Página enxuta para transformar tráfego de anúncios em leads.', tags: ['green'], metrics: { score: 99, ease: 100, revenue: 76, recurring: 50, complexity: 12 } },
        { title: 'Página de serviço', description: 'Página dedicada a um serviço específico, preparada para campanha e busca.', tags: ['green'], metrics: { score: 96, ease: 100, revenue: 68, recurring: 45, complexity: 10 } },
        { title: 'Catálogo digital', description: 'Catálogo online de serviços, produtos, portfólio ou opções de atendimento.', tags: ['green'], metrics: { score: 94, ease: 95, revenue: 72, recurring: 45, complexity: 20 } },
        { title: 'Manutenção de site', description: 'Atualizações, correções, conteúdo, segurança básica e pequenas melhorias.', tags: ['green'], metrics: { score: 96, ease: 94, revenue: 66, recurring: 98, complexity: 24 } },
        { title: 'Hospedagem e gestão técnica', description: 'Hospedagem, domínio, SSL, backups e suporte operacional.', tags: ['green'], metrics: { score: 93, ease: 90, revenue: 62, recurring: 100, complexity: 30 } }
      ]
    },
    {
      title: 'CRM & Sistemas Internos',
      description: 'Ferramentas sob medida para organizar clientes, tarefas, documentos, agenda e operação.',
      metrics: { score: 95, ease: 88, revenue: 94, recurring: 98, complexity: 58 },
      children: [
        { title: 'CRM para pequenos negócios', description: 'Funil de leads, cadastro de clientes, histórico e tarefas em um painel simples.', tags: ['green'], metrics: { score: 97, ease: 90, revenue: 94, recurring: 100, complexity: 55 } },
        { title: 'CRM para escritório jurídico', description: 'Clientes, atendimentos, documentos, prazos internos e etapas comerciais.', tags: ['green', 'yellow'], metrics: { score: 95, ease: 86, revenue: 96, recurring: 100, complexity: 62 } },
        { title: 'Kanban operacional', description: 'Painel visual para acompanhar demandas por etapa, responsável e prioridade.', tags: ['green'], metrics: { score: 96, ease: 96, revenue: 82, recurring: 85, complexity: 28 } },
        { title: 'Agenda e agendamento online', description: 'Agenda compartilhada com formulário, confirmação e organização de horários.', tags: ['green'], metrics: { score: 94, ease: 94, revenue: 76, recurring: 90, complexity: 34 } },
        { title: 'Portal do cliente', description: 'Área privada para documentos, andamento, mensagens e informações do atendimento.', tags: ['green'], metrics: { score: 88, ease: 70, revenue: 94, recurring: 95, complexity: 72 } },
        { title: 'Sistema interno sob medida', description: 'Aplicação simples para substituir planilhas, papel e processos manuais repetitivos.', tags: ['green'], metrics: { score: 91, ease: 78, revenue: 100, recurring: 88, complexity: 70 } }
      ]
    },
    {
      title: 'WhatsApp & Atendimento',
      description: 'Automação do canal que já concentra boa parte do atendimento de pequenas empresas.',
      metrics: { score: 98, ease: 91, revenue: 95, recurring: 100, complexity: 52 },
      children: [
        { title: 'Central de atendimento WhatsApp', description: 'Organiza contatos, etapas, responsáveis e histórico do atendimento.', tags: ['green'], metrics: { score: 99, ease: 92, revenue: 95, recurring: 100, complexity: 48 } },
        { title: 'Bot de triagem', description: 'Faz perguntas iniciais, coleta dados e encaminha cada cliente para o fluxo correto.', tags: ['green'], metrics: { score: 98, ease: 95, revenue: 90, recurring: 100, complexity: 42 } },
        { title: 'Automação de follow-up', description: 'Lembra a equipe ou envia mensagens programadas para leads sem resposta.', tags: ['green'], metrics: { score: 99, ease: 94, revenue: 92, recurring: 100, complexity: 40 } },
        { title: 'Integração WhatsApp + CRM', description: 'Conversa vira lead, histórico e tarefa automaticamente dentro do sistema.', tags: ['green'], metrics: { score: 98, ease: 90, revenue: 98, recurring: 100, complexity: 55 } },
        { title: 'Respostas assistidas por IA', description: 'Sugere respostas e resume conversas mantendo uma pessoa no controle final.', tags: ['green'], metrics: { score: 94, ease: 82, revenue: 98, recurring: 100, complexity: 65 } },
        { title: 'Disparo transacional', description: 'Confirmações, lembretes e atualizações ligadas a uma solicitação real do cliente.', tags: ['green'], metrics: { score: 93, ease: 86, revenue: 84, recurring: 100, complexity: 50 } }
      ]
    },
    {
      title: 'Automação de Processos',
      description: 'Conecta sistemas e elimina tarefas repetitivas que hoje alguém faz na mão.',
      metrics: { score: 97, ease: 88, revenue: 98, recurring: 96, complexity: 55 },
      children: [
        { title: 'Automação de formulários', description: 'Dados enviados pelo cliente alimentam planilhas, CRM, e-mail ou outros sistemas.', tags: ['green'], metrics: { score: 98, ease: 96, revenue: 85, recurring: 90, complexity: 30 } },
        { title: 'Geração automática de documentos', description: 'Dados cadastrados preenchem modelos e geram arquivos padronizados.', tags: ['green', 'yellow'], metrics: { score: 97, ease: 90, revenue: 95, recurring: 96, complexity: 48 } },
        { title: 'Integração entre sistemas', description: 'Faz ferramentas diferentes trocarem dados sem copiar e colar informações.', tags: ['green'], metrics: { score: 96, ease: 82, revenue: 100, recurring: 95, complexity: 68 } },
        { title: 'Automação de e-mails e notificações', description: 'Envia avisos a partir de etapas, datas, pagamentos ou ações do cliente.', tags: ['green'], metrics: { score: 96, ease: 96, revenue: 82, recurring: 98, complexity: 30 } },
        { title: 'Fluxos n8n', description: 'Criação e manutenção de automações conectando APIs, bancos, WhatsApp e serviços web.', tags: ['green'], metrics: { score: 97, ease: 90, revenue: 98, recurring: 98, complexity: 58 } },
        { title: 'Robôs de tarefas administrativas', description: 'Automatiza consultas, organização de dados e rotinas digitais repetitivas quando permitido.', tags: ['green'], metrics: { score: 90, ease: 76, revenue: 98, recurring: 94, complexity: 72 } }
      ]
    },
    {
      title: 'IA Aplicada a Negócios',
      description: 'IA usada para economizar tempo e organizar informação, sem vender um oráculo eletrônico que inventa coisa.',
      metrics: { score: 90, ease: 74, revenue: 100, recurring: 98, complexity: 76 },
      children: [
        { title: 'Assistente interno com IA', description: 'Pesquisa procedimentos, informações internas e respostas a partir da base da empresa.', tags: ['green'], metrics: { score: 92, ease: 78, revenue: 100, recurring: 100, complexity: 70 } },
        { title: 'Resumo automático de atendimentos', description: 'Transforma conversas longas em resumo, pendências e próximos passos.', tags: ['green'], metrics: { score: 96, ease: 92, revenue: 90, recurring: 100, complexity: 42 } },
        { title: 'Classificação de leads', description: 'Organiza contatos por interesse, urgência, serviço ou estágio comercial.', tags: ['green'], metrics: { score: 95, ease: 88, revenue: 92, recurring: 100, complexity: 50 } },
        { title: 'Extração de dados de documentos', description: 'Lê documentos e transforma campos relevantes em dados estruturados para conferência humana.', tags: ['green', 'yellow'], metrics: { score: 89, ease: 72, revenue: 100, recurring: 96, complexity: 78 } },
        { title: 'Base de conhecimento pesquisável', description: 'Centraliza manuais, procedimentos e documentos para busca inteligente da equipe.', tags: ['green'], metrics: { score: 94, ease: 86, revenue: 92, recurring: 98, complexity: 52 } },
        { title: 'Chatbot com base própria', description: 'Atendimento automatizado limitado às informações e regras fornecidas pela empresa.', tags: ['green'], metrics: { score: 84, ease: 65, revenue: 100, recurring: 100, complexity: 82 } }
      ]
    },
    {
      title: 'Dados, Relatórios & Financeiro',
      description: 'Painéis e relatórios simples para deixar de administrar empresa no instinto e na memória.',
      metrics: { score: 92, ease: 88, revenue: 86, recurring: 92, complexity: 46 },
      children: [
        { title: 'Dashboard gerencial', description: 'Painel com vendas, leads, despesas, metas e indicadores importantes.', tags: ['green'], metrics: { score: 94, ease: 88, revenue: 90, recurring: 92, complexity: 46 } },
        { title: 'Relatório automático', description: 'Gera resumos periódicos usando dados já registrados no sistema.', tags: ['green'], metrics: { score: 92, ease: 90, revenue: 82, recurring: 98, complexity: 38 } },
        { title: 'Controle financeiro simples', description: 'Receitas, despesas, categorias, fluxo de caixa e visão mensal.', tags: ['green'], metrics: { score: 91, ease: 90, revenue: 80, recurring: 90, complexity: 40 } },
        { title: 'Funil comercial', description: 'Mede de onde vieram os leads e onde eles estão sendo perdidos.', tags: ['green'], metrics: { score: 96, ease: 94, revenue: 88, recurring: 96, complexity: 32 } }
      ]
    },
    {
      title: 'Infraestrutura Digital',
      description: 'Implantação e manutenção da parte técnica que pequenas empresas normalmente ignoram até parar de funcionar.',
      metrics: { score: 80, ease: 78, revenue: 80, recurring: 98, complexity: 66 },
      children: [
        { title: 'Domínio, DNS e SSL', description: 'Configuração de domínio, apontamentos, HTTPS e serviços básicos.', tags: ['green'], metrics: { score: 88, ease: 92, revenue: 60, recurring: 72, complexity: 28 } },
        { title: 'E-mail profissional', description: 'Criação e configuração de contas com domínio próprio.', tags: ['green'], metrics: { score: 90, ease: 94, revenue: 62, recurring: 84, complexity: 26 } },
        { title: 'Servidor e hospedagem', description: 'Implantação e manutenção básica de aplicações e serviços web.', tags: ['green'], metrics: { score: 82, ease: 76, revenue: 82, recurring: 100, complexity: 68 } },
        { title: 'Backup automatizado', description: 'Rotina de cópias de segurança para arquivos, bancos e aplicações.', tags: ['green'], metrics: { score: 88, ease: 88, revenue: 68, recurring: 100, complexity: 42 } },
        { title: 'Acesso remoto seguro', description: 'Configuração de acesso remoto para manutenção e trabalho autorizado.', tags: ['green'], metrics: { score: 82, ease: 88, revenue: 64, recurring: 80, complexity: 44 } },
        { title: 'Monitoramento básico', description: 'Verifica disponibilidade de serviços e avisa quando algo importante cai.', tags: ['green'], metrics: { score: 84, ease: 82, revenue: 76, recurring: 100, complexity: 54 } }
      ]
    },
    {
      title: 'Pacotes de Transformação Digital',
      description: 'Combina várias soluções em um projeto de implantação, o que vende melhor do que quinze serviços técnicos soltos.',
      metrics: { score: 96, ease: 86, revenue: 100, recurring: 100, complexity: 58 },
      children: [
        { title: 'Kit Presença Digital', description: 'Site + domínio + e-mail + formulário + analytics básico.', tags: ['green'], metrics: { score: 98, ease: 96, revenue: 88, recurring: 82, complexity: 28 } },
        { title: 'Kit Atendimento', description: 'WhatsApp + CRM + funil + automações de retorno e agendamento.', tags: ['green'], metrics: { score: 100, ease: 90, revenue: 100, recurring: 100, complexity: 52 } },
        { title: 'Kit Escritório Digital', description: 'CRM + documentos + agenda + portal + automações para operação profissional.', tags: ['green', 'yellow'], metrics: { score: 96, ease: 82, revenue: 100, recurring: 100, complexity: 66 } },
        { title: 'Kit Automação Administrativa', description: 'Mapeia tarefas repetitivas e automatiza formulários, mensagens, documentos e relatórios.', tags: ['green'], metrics: { score: 99, ease: 88, revenue: 100, recurring: 100, complexity: 56 } },
        { title: 'Diagnóstico de processos digitais', description: 'Mapeia o que a empresa faz manualmente e prioriza o que vale automatizar.', tags: ['green'], metrics: { score: 97, ease: 100, revenue: 90, recurring: 65, complexity: 20 } }
      ]
    }
  ]
};
