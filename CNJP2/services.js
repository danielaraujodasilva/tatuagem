export const LEGEND = {
  green: { label: 'Execução administrativa', short: 'A CNJP pode executar diretamente a parte administrativa.' },
  blue: { label: 'Intermediação', short: 'A CNJP organiza, encaminha e acompanha; o ato oficial é de terceiro.' },
  yellow: { label: 'Profissional habilitado', short: 'Exige advogado ou outro profissional habilitado em parte do serviço.' },
  red: { label: 'Ato exclusivo', short: 'A CNJP não pratica o ato oficial; apenas assessora e acompanha.' },
  purple: { label: 'Fluxo digital oficial', short: 'Pode usar plataforma ou procedimento eletrônico oficial.' }
};

export const SERVICES = [
  {
    id: 'documentos-cartorios',
    title: 'Documentos & Cartórios',
    icon: 'folder_open',
    description: 'Resolver burocracia documental sem o cliente precisar descobrir sozinho onde, como e com quem pedir.',
    tags: ['green', 'blue'],
    children: [
      {
        title: 'Certidões e segundas vias',
        description: 'Localização, solicitação e acompanhamento de documentos oficiais.',
        children: [
          { title: 'Certidão de nascimento', description: 'Segunda via e orientação documental para localizar e solicitar o registro.', tags: ['green', 'blue'] },
          { title: 'Certidão de casamento', description: 'Segunda via, inteiro teor quando aplicável e acompanhamento da solicitação.', tags: ['green', 'blue'] },
          { title: 'Certidão de óbito', description: 'Solicitação de segunda via e orientação sobre o cartório competente.', tags: ['green', 'blue'] },
          { title: 'Certidões imobiliárias', description: 'Matrícula, ônus, transcrição e outras certidões ligadas a imóveis.', tags: ['blue'] },
          { title: 'Certidão de protesto', description: 'Consulta e obtenção da certidão perante a central ou cartório competente.', tags: ['blue'] },
          { title: 'Certidão de ato notarial', description: 'Solicitação de certidão ou traslado de ato praticado em tabelionato.', tags: ['blue', 'purple'] }
        ]
      },
      {
        title: 'Reconhecimento, autenticação e notas',
        description: 'A CNJP facilita o caminho; o tabelionato continua praticando o ato.',
        children: [
          { title: 'Assessoria para reconhecimento de firma', description: 'Triagem do documento, orientação e encaminhamento ao tabelionato.', tags: ['blue', 'red'] },
          { title: 'e-Not Assina', description: 'Orientação e acompanhamento do reconhecimento de assinatura eletrônica em documento digital.', tags: ['blue', 'purple', 'red'] },
          { title: 'Assessoria para autenticação', description: 'Organização e encaminhamento de documentos para autenticação.', tags: ['blue', 'red'] },
          { title: 'CENAD', description: 'Orientação para autenticação digital por meio do fluxo notarial oficial.', tags: ['blue', 'purple', 'red'] },
          { title: 'Procuração pública', description: 'Triagem, organização de informações, encaminhamento e acompanhamento.', tags: ['blue', 'purple', 'red'] },
          { title: 'Escritura pública', description: 'Assessoria documental e acompanhamento até o ato ser lavrado pelo tabelião.', tags: ['blue', 'yellow', 'purple', 'red'] },
          { title: 'Ata notarial', description: 'Coleta inicial, organização e encaminhamento da demanda ao tabelionato.', tags: ['blue', 'yellow', 'purple', 'red'] },
          { title: 'Apostila de Haia', description: 'Verificação documental, serventia habilitada, encaminhamento e acompanhamento.', tags: ['blue', 'purple', 'red'] },
          { title: 'AEV', description: 'Orientação e acompanhamento da Autorização Eletrônica de Viagem.', tags: ['blue', 'purple', 'red'] }
        ]
      },
      {
        title: 'Registro de Títulos e Documentos (RTD)',
        description: 'Registro, conservação, notificação e pesquisa de documentos particulares.',
        children: [
          { title: 'Registro de contrato', description: 'Organização e encaminhamento para registro do contrato no RTD.', tags: ['blue', 'red'] },
          { title: 'Registro de documento', description: 'Triagem e acompanhamento do registro ou conservação documental.', tags: ['blue', 'red'] },
          { title: 'Notificação via RTD', description: 'Encaminhamento formal e acompanhamento da notificação perante o registro competente.', tags: ['blue', 'red'] },
          { title: 'Certidões e pesquisas em RTD', description: 'Solicitação e acompanhamento de certidões e pesquisas disponíveis.', tags: ['blue'] }
        ]
      }
    ]
  },
  {
    id: 'conflitos-extrajudicial',
    title: 'Conflitos & Soluções Extrajudiciais',
    icon: 'handshake',
    description: 'Resolver conflitos por negociação estruturada antes de transformar tudo em processo, custo e sofrimento burocrático.',
    tags: ['green', 'yellow'],
    children: [
      { title: 'Mediação extrajudicial', description: 'Terceiro imparcial ajuda as partes a construírem uma solução consensual.', tags: ['green'] },
      { title: 'Conciliação extrajudicial', description: 'Condução mais direcionada para buscar um acordo viável entre as partes.', tags: ['green'] },
      { title: 'Arbitragem', description: 'Solução privada de conflitos patrimoniais disponíveis, conforme convenção e legislação aplicável.', tags: ['yellow'] },
      { title: 'Negociação estruturada', description: 'Organização de proposta e comunicação entre partes, credores, devedores ou empresas.', tags: ['green'] },
      { title: 'Audiência online', description: 'Sessão de conciliação ou mediação por videoconferência com registro do procedimento.', tags: ['green'] },
      { title: 'Termo de acordo', description: 'Formalização do acordo alcançado, respeitando os limites da atividade jurídica privativa.', tags: ['green', 'yellow'] },
      {
        title: 'Notificações extrajudiciais',
        description: 'Comunicação formal para cobrar, negociar, rescindir ou registrar uma posição.',
        children: [
          { title: 'Notificação de cobrança', description: 'Comunicação formal de dívida e abertura para pagamento ou negociação.', tags: ['green', 'blue'] },
          { title: 'Notificação para acordo', description: 'Convocação formal para tentativa de composição.', tags: ['green', 'blue'] },
          { title: 'Notificação contratual', description: 'Comunicação sobre descumprimento, obrigação ou intenção de encerramento.', tags: ['blue', 'yellow'] },
          { title: 'Notificação de desocupação', description: 'Organização e encaminhamento da comunicação conforme contrato e situação.', tags: ['blue', 'yellow'] },
          { title: 'Notificação empresarial', description: 'Comunicação formal entre empresas, fornecedores, clientes ou parceiros.', tags: ['blue', 'yellow'] }
        ]
      },
      {
        title: 'Cobrança e recuperação de crédito',
        description: 'Levantamento do débito, tentativa de negociação e regularização documental.',
        children: [
          { title: 'Negociação de dívida', description: 'Organização do débito e estruturação de proposta de pagamento.', tags: ['green'] },
          { title: 'Consulta de protestos', description: 'Pesquisa de protestos e orientação sobre próximos passos.', tags: ['green', 'blue'] },
          { title: 'Cancelamento de protesto', description: 'Orientação documental e acompanhamento do procedimento de cancelamento.', tags: ['blue', 'red'] },
          { title: 'Encaminhamento para protesto', description: 'Organização e envio da documentação ao serviço competente.', tags: ['blue', 'red'] }
        ]
      }
    ]
  },
  {
    id: 'imobiliario',
    title: 'Imobiliário & Regularização',
    icon: 'apartment',
    description: 'Diagnosticar documentos, organizar o caminho e acompanhar regularizações, registros e conflitos ligados a imóveis.',
    tags: ['blue', 'yellow'],
    children: [
      {
        title: 'Diagnóstico documental do imóvel',
        description: 'Descobrir o que existe, o que falta e qual caminho faz sentido antes de prometer uma solução.',
        children: [
          { title: 'Pesquisa de matrícula', description: 'Localização de matrícula e identificação do registro competente.', tags: ['green', 'blue'] },
          { title: 'Matrícula atualizada', description: 'Solicitação e acompanhamento de matrícula atualizada.', tags: ['green', 'blue'] },
          { title: 'Certidão de ônus', description: 'Obtenção da certidão para verificar ônus e registros existentes.', tags: ['blue'] },
          { title: 'Pesquisa de titularidade', description: 'Pesquisa documental dentro dos meios legalmente disponíveis.', tags: ['blue'] },
          { title: 'Levantamento documental', description: 'Reunião e organização dos documentos necessários ao procedimento.', tags: ['green'] }
        ]
      },
      {
        title: 'Registro e regularização',
        description: 'Gestão documental e acompanhamento de procedimentos perante Registro de Imóveis, prefeitura e profissionais.',
        children: [
          { title: 'Registro de compra e venda', description: 'Organização documental e acompanhamento do protocolo e registro.', tags: ['blue', 'yellow', 'red'] },
          { title: 'Averbação', description: 'Triagem, documentos e acompanhamento de averbações possíveis.', tags: ['blue', 'yellow', 'red'] },
          { title: 'Retificação de registro', description: 'Organização do pedido e encaminhamento profissional quando necessário.', tags: ['blue', 'yellow', 'red'] },
          { title: 'Regularização imobiliária', description: 'Diagnóstico e coordenação documental do caminho para regularizar o imóvel.', tags: ['blue', 'yellow'] },
          { title: 'Usucapião extrajudicial', description: 'Gestão documental, certidões e acompanhamento com advogado e registro competente.', tags: ['blue', 'yellow', 'red'] },
          { title: 'Adjudicação compulsória extrajudicial', description: 'Triagem e acompanhamento administrativo com profissionais obrigatórios.', tags: ['blue', 'yellow', 'red'] },
          { title: 'Cancelamento de ônus', description: 'Levantamento documental e acompanhamento do procedimento adequado.', tags: ['blue', 'yellow', 'red'] },
          { title: 'Regularização na prefeitura', description: 'Protocolos, cadastros e acompanhamento administrativo municipal.', tags: ['green', 'yellow'] },
          { title: 'IPTU e cadastro imobiliário', description: 'Consulta, organização documental e acompanhamento de regularizações cadastrais.', tags: ['green'] },
          { title: 'Habite-se e regularizações técnicas', description: 'Gestão documental com encaminhamento a profissionais habilitados quando exigidos.', tags: ['green', 'yellow'] }
        ]
      },
      {
        title: 'Conflitos imobiliários',
        description: 'Negociação e mediação envolvendo aluguel, compra, venda e obrigações relacionadas ao imóvel.',
        children: [
          { title: 'Conflito proprietário × inquilino', description: 'Triagem, negociação e mediação de problemas locatícios.', tags: ['green', 'yellow'] },
          { title: 'Cobrança de aluguel', description: 'Negociação extrajudicial de valores em atraso.', tags: ['green'] },
          { title: 'Distrato imobiliário', description: 'Organização documental e encaminhamento profissional conforme o caso.', tags: ['blue', 'yellow'] },
          { title: 'Conflito comprador × vendedor', description: 'Mediação e organização da documentação do negócio.', tags: ['green', 'yellow'] }
        ]
      }
    ]
  },
  {
    id: 'empresarial-administrativo',
    title: 'Empresarial & Administrativo',
    icon: 'domain',
    description: 'Ajudar empresas e pessoas a organizar cadastros, documentos, protocolos e pendências administrativas.',
    tags: ['green', 'yellow'],
    children: [
      {
        title: 'Empresas e CNPJ',
        description: 'Assessoria operacional para abertura, alteração, baixa e regularização empresarial.',
        children: [
          { title: 'Abertura de empresa', description: 'Organização da demanda e intermediação com os profissionais e órgãos necessários.', tags: ['green', 'yellow'] },
          { title: 'Alteração empresarial', description: 'Mudança de endereço, atividade, dados cadastrais ou quadro societário.', tags: ['green', 'yellow'] },
          { title: 'Baixa empresarial', description: 'Organização e acompanhamento do encerramento formal.', tags: ['green', 'yellow'] },
          { title: 'Regularização de CNPJ', description: 'Levantamento de pendências e encaminhamento para solução.', tags: ['green', 'yellow'] },
          { title: 'Regularização de MEI', description: 'Orientação administrativa e encaminhamento das pendências.', tags: ['green', 'yellow'] },
          { title: 'Certidões empresariais', description: 'Pesquisa, solicitação e organização de certidões da empresa.', tags: ['green', 'blue'] }
        ]
      },
      {
        title: 'Central administrativa',
        description: 'A empresa funciona como braço operacional para tarefas burocráticas recorrentes.',
        children: [
          { title: 'Protocolos administrativos', description: 'Preparação, protocolo e acompanhamento de solicitações.', tags: ['green'] },
          { title: 'Preenchimento de formulários', description: 'Auxílio operacional no preenchimento e organização de informações.', tags: ['green'] },
          { title: 'Agendamentos públicos', description: 'Localização do canal correto e auxílio no agendamento.', tags: ['green'] },
          { title: 'Regularização cadastral', description: 'Levantamento da situação e acompanhamento de correções administrativas.', tags: ['green'] },
          { title: 'Organização e digitalização', description: 'Digitalização, classificação e criação de pasta documental organizada.', tags: ['green'] },
          { title: 'Acompanhamento de processos administrativos', description: 'Consulta de andamento e organização das exigências do processo.', tags: ['green', 'yellow'] }
        ]
      }
    ]
  },
  {
    id: 'familia-sucessoes',
    title: 'Família & Sucessões',
    icon: 'family_restroom',
    description: 'Gestão documental e encaminhamento de casos familiares e sucessórios, com atuação jurídica quando obrigatória.',
    tags: ['yellow'],
    children: [
      { title: 'Divórcio consensual/extrajudicial', description: 'Triagem, organização documental e acompanhamento com advogado e tabelionato.', tags: ['blue', 'yellow', 'red', 'purple'] },
      { title: 'Dissolução de união estável', description: 'Triagem e encaminhamento conforme a situação e o procedimento adequado.', tags: ['blue', 'yellow'] },
      { title: 'Inventário extrajudicial', description: 'Gestão documental, certidões e acompanhamento com advogado obrigatório e tabelionato.', tags: ['blue', 'yellow', 'red', 'purple'] },
      { title: 'Partilha e sobrepartilha', description: 'Organização documental e coordenação com profissional habilitado.', tags: ['blue', 'yellow'] },
      { title: 'Planejamento sucessório', description: 'A empresa pode organizar a demanda; o conteúdo jurídico fica com profissional habilitado.', tags: ['yellow'] },
      { title: 'Testamento', description: 'Triagem, organização e encaminhamento para orientação profissional e tabelionato.', tags: ['blue', 'yellow', 'red', 'purple'] },
      { title: 'Mediação familiar patrimonial', description: 'Mediação de questões patrimoniais que admitam solução consensual.', tags: ['green', 'yellow'] }
    ]
  },
  {
    id: 'consumidor',
    title: 'Consumidor',
    icon: 'shopping_bag',
    description: 'Apoio administrativo, negociação e encaminhamento de problemas de consumo antes ou fora do Judiciário.',
    tags: ['green', 'yellow'],
    children: [
      { title: 'Reclamação administrativa', description: 'Organização da reclamação e encaminhamento ao fornecedor ou órgão competente.', tags: ['green'] },
      { title: 'Cobrança indevida', description: 'Levantamento documental e tentativa de negociação administrativa.', tags: ['green', 'yellow'] },
      { title: 'Negativação indevida', description: 'Triagem, documentação e encaminhamento profissional quando houver discussão jurídica.', tags: ['green', 'yellow'] },
      { title: 'Cancelamentos e reembolsos', description: 'Organização da demanda e tentativa de resolução diretamente com a empresa.', tags: ['green'] },
      { title: 'Mediação consumidor × empresa', description: 'Tentativa estruturada de acordo entre consumidor e fornecedor.', tags: ['green'] },
      { title: 'Orientação para Procon e órgãos', description: 'Identificação do canal e organização dos documentos necessários.', tags: ['green'] }
    ]
  },
  {
    id: 'b2b',
    title: 'Planos B2B',
    icon: 'hub',
    description: 'Pacotes recorrentes para organizações que lidam com conflitos, documentos e burocracia todos os meses.',
    tags: ['green', 'blue'],
    children: [
      {
        title: 'Imobiliárias',
        description: 'Um braço extrajudicial para locação, cobrança, documentação e regularização.',
        children: [
          { title: 'Mediação locatícia', description: 'Negociação de conflitos entre proprietário, inquilino e imobiliária.', tags: ['green'] },
          { title: 'Cobrança e inadimplência', description: 'Negociação extrajudicial recorrente de débitos locatícios.', tags: ['green'] },
          { title: 'Notificações', description: 'Triagem, preparação operacional e encaminhamento de comunicações formais.', tags: ['blue', 'yellow'] },
          { title: 'Documentação e certidões', description: 'Pesquisas, matrículas, certidões e acompanhamento cartorário.', tags: ['green', 'blue'] },
          { title: 'Regularização imobiliária', description: 'Diagnóstico e gestão do fluxo documental dos imóveis.', tags: ['blue', 'yellow'] }
        ]
      },
      {
        title: 'Condomínios',
        description: 'Apoio recorrente para inadimplência e conflitos da vida condominial.',
        children: [
          { title: 'Cobrança extrajudicial', description: 'Negociação de débitos condominiais antes da cobrança judicial.', tags: ['green'] },
          { title: 'Mediação condominial', description: 'Conflitos entre moradores, síndico, administradora e prestadores.', tags: ['green'] },
          { title: 'Notificações', description: 'Comunicações formais relacionadas a obrigações e conflitos.', tags: ['blue', 'yellow'] },
          { title: 'Treinamento de síndicos', description: 'Capacitação em prevenção e gestão de conflitos.', tags: ['green'] }
        ]
      },
      {
        title: 'Escolas',
        description: 'Mediação, prevenção de conflitos e capacitação de equipes escolares.',
        children: [
          { title: 'Mediação escolar', description: 'Conflitos entre alunos, pais, professores e instituição.', tags: ['green'] },
          { title: 'Círculos restaurativos', description: 'Dinâmicas estruturadas para diálogo e resolução de conflitos.', tags: ['green'] },
          { title: 'Capacitação de gestores e professores', description: 'Treinamento em negociação, comunicação e prevenção de conflitos.', tags: ['green'] },
          { title: 'Programa de cultura de paz', description: 'Projeto recorrente de prevenção, acolhimento e encaminhamento.', tags: ['green'] }
        ]
      }
    ]
  },
  {
    id: 'educacao',
    title: 'Cursos & Capacitação',
    icon: 'school',
    description: 'Uma vertical futura para transformar conhecimento operacional em formação e treinamento.',
    tags: ['green'],
    stage: 'fase futura',
    children: [
      { title: 'Mediação, conciliação e arbitragem', description: 'Formação e atualização em métodos adequados de solução de conflitos.', tags: ['green'] },
      { title: 'Negociação e comunicação', description: 'Técnicas de negociação, comunicação não violenta e gestão de conflitos.', tags: ['green'] },
      { title: 'Serviços extrajudiciais', description: 'Capacitação sobre cartórios, documentação, notificações e regularização.', tags: ['green'] },
      { title: 'Agente de Soluções Extrajudiciais', description: 'Formação para triagem, organização documental, encaminhamento e acompanhamento.', tags: ['green'] },
      { title: 'Treinamentos B2B', description: 'Capacitação personalizada para imobiliárias, condomínios, escolas e empresas.', tags: ['green'] }
    ]
  }
];
