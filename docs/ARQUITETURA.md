# Arquitetura — decisões em aberto

**Status:** Laravel/MariaDB, contas Master e Usuário, grupos de trabalho, permissões por processo, editor bpmn-js e formulários iniciais e por tarefa estão aplicados. Tarefas humanas são encaminhadas a grupos; o tipo configurado em cada etapa determina se ela é de preenchimento ou aprovação. A conclusão registra o responsável, as respostas e a justificativa.

## Base validada

- Windows, XAMPP em `C:\xamp85`; repositório em `C:\xampp\htdocs\BPMN`.
- PHP 8.5.12, Apache 2.4.65, MariaDB 10.11.16 e Laravel Framework 13.38.0.
- Apache responde HTTP 200; o VirtualHost serve somente `public/` e exige `Require local`.
- Banco `flowcore_bpm`; usuário `flowcore_app` com acesso apenas a essa base. Senha aleatória e segredo de sessão ficam em `.env`, fora do Git.
- Autenticação Laravel por sessão/CSRF, usuário Master bootstrapado a partir de variáveis locais e sem cadastro público.
- bpmn-js 18.30.1, distribuído sob a licença bpmn.io. O watermark do bpmn.io continua visível conforme a licença.
- 34 testes passaram (146 assertions); Vite compila os assets do editor, do construtor de formulários e do portal de aprovações.
- Temurin JDK 17.0.20.1 e Maven 3.10.0 portáteis em `.tools/`; o Java global não foi alterado.
- Flowable 8.0.0 em serviço Spring Boot 4.0.0 (`engine-poc`), limitado a `127.0.0.1:8091`.
- Base MariaDB `flowcore_engine` e usuário `flowcore_engine`, com privilégios restritos à própria base e separados do Laravel.
- Prova REST: anônimo recebe 401; instância e tarefa humana concluídas; histórico recuperado após reinício do motor.
- Serviço Laravel `FlowableClient` consulta definições por Basic Auth com timeout; rota `GET /master/engine/process-definitions` exige Master e retorna apenas campos selecionados.
- A publicação exige exatamente um processo BPMN, atribui `flowcore_process_{id}` como chave exclusiva e marca o processo como executável. O XML publicado, hash, deployment ID e process-definition ID ficam registrados no banco local.
- O nome determinístico da implantação combina ID do processo e versão. Em uma repetição após resposta incerta, o Laravel lê o recurso do Flowable e compara o SHA-256 antes de reutilizar a definição existente.
- Processos publicados não podem ser alterados pelo endpoint de rascunho; o editor passa a usar a visualização BPMN sem controles de edição.
- `form_schema` guarda, no registro da versão em rascunho, campos permitidos e suas opções. A publicação bloqueia alterações ao diagrama e ao formulário.
- `process_instances` guarda UUID usado nas rotas, código humano `SOL-{id}` para identificação na interface, chave de idempotência, iniciador, formulário submetido, estado local e ID da instância Flowable. Usuários consultam somente solicitações que iniciaram.
- O Flowable recebe variáveis tipadas e UUID como `businessKey`; antes de criar, a integração procura a chave entre instâncias ativas e no histórico, inclusive para recuperar resposta perdida após conclusão rápida.
- `process_user_access` separa visualização e início de solicitações. O campo legado `can_approve` é mantido somente para instâncias e definições antigas; novos processos usam os grupos configurados nas etapas.
- `process_task_decisions` preserva tarefa, ator, decisão, comentário e confirmação do Flowable; o solicitante vê esse histórico em sua própria solicitação.
- Datas são gravadas em UTC e convertidas para o fuso de apresentação `APP_DISPLAY_TIMEZONE` (padrão `America/Sao_Paulo`) nas telas.
- `process_groups` e `process_group_user` guardam equipes e membros, cada grupo com um identificador estável para o Flowable.
- `process_definitions.task_group_assignments` associa cada ID de tarefa BPMN a um grupo. Na publicação, o Laravel grava esse grupo como `flowable:candidateGroups`; a fila consulta candidatos e permite que um integrante assuma a tarefa.
- `process_definitions.task_form_schemas` guarda o tipo da etapa (preenchimento ou aprovação) e seus campos. As respostas ficam em `process_task_decisions.form_data` e também são enviadas ao Flowable com nomes de variável únicos por tarefa.
- O campo `show_process_data` no esquema de uma tarefa autoriza a fila a mostrar, em leitura, os campos das etapas já concluídas. As tarefas de aprovação e cadastro final podem reutilizar o histórico local, sem depender da consulta de variáveis do motor.
- `process_definitions.gateway_routes` pode associar saídas exclusivas ou inclusivas a `approved`, `rejected`, `default` ou `return:{taskDefinitionKey}`. Gateways exclusivos devolvem para uma área; gateways inclusivos permitem selecionar várias áreas e usam uma junção inclusiva para esperar as correções antes de retornar à aprovação. O formulário exige destino(s) e justificativa; o Laravel publica condições com `fc_decision` e `fc_return_to`.
- `process_definitions.family_uuid` identifica versões da mesma definição e `is_active` indica qual publicação recebe novas solicitações. Publicar uma versão nova a ativa; o Master também pode reativar uma versão publicada anterior. Instâncias em andamento continuam vinculadas à versão original.
- Solicitações em andamento têm seu status reconciliado ao abrir a lista/detalhes do solicitante e por um comando agendado que verifica 25 por minuto. A reconciliação consulta runtime e, quando necessário, histórico do Flowable; se o motor estiver indisponível ou não confirmar um registro histórico, o estado final local não é inferido. Uma atualização condicional protege contra corrida com a conclusão de uma tarefa.
- Tarefas humanas novas e decisões de etapa geram registros no outbox `task_notification_outbox`, únicos por tarefa, usuário e tipo de evento. O solicitante recebe avisos para aprovação de etapa, devolução com motivo/destino, rejeição e conclusão. O envio ocorre em jobs assíncronos, com três tentativas; falhas ficam registradas sem interromper a instância. Um comando agendado verifica 25 instâncias por ciclo e envia um aviso ao entrar nas últimas 24 horas e outro se a tarefa vencer. O Master consulta e filtra o outbox e pode reenfileirar falhas. A entrega externa depende de mailer SMTP configurado e de um worker Laravel ativo.
- `audit_events` registra, com ator e data, publicações, ativações, exclusões e alterações de formulários, roteamento e permissões. A tela é exclusiva do Master; eventos guardam uma cópia do nome do ator e do processo para preservar o histórico após a remoção das definições. Respostas de solicitações não são copiadas para a auditoria administrativa.

## Dados e segurança implementados

- `users.role` distingue Administrador Master de Usuário; `users.is_active` e as permissões `can_view`/`can_start` controlam o catálogo. A associação aos grupos controla o acesso às tarefas, inclusive às etapas de aprovação. Perfis antigos são convertidos para Usuário.
- `process_definitions` armazena nome, descrição, estado `draft`, versão inicial, XML BPMN e autores de criação/alteração.
- `process_definitions.form_schema` armazena no máximo 30 campos de tipos permitidos; opções de listas são valores simples, sem código executável.
- `process_instances` preserva UUID, chave de idempotência, autor, dados enviados e referência Flowable para a consulta do próprio solicitante.
- Rotas de modelagem, usuários, formulários e publicação exigem sessão autenticada e papel Master. Rotas de catálogo exigem sessão ativa e processo concedido à conta.
- O endpoint limita XML a 2 MB, exige `bpmn:definitions` e ao menos um `bpmn:process`, rejeita DTD/entidades externas e usa `LIBXML_NONET`.
- O editor permite importar/exportar arquivo BPMN e persiste rascunhos no servidor. As respostas do formulário são persistidas como JSON, e somente os campos definidos no esquema são enviados ao motor.
- O endpoint de início valida os valores novamente no servidor, descarta campos extras, e só aceita processos publicados concedidos à conta ativa.

## Decisões pendentes

1. Exibir no painel Master a atualização periódica dos status e ampliar métricas operacionais.
2. Validar criação e publicação de novas versões junto a permissões herdadas e instâncias ainda em execução.
3. Expandir permissões para grupos/departamentos e auditar alterações administrativas.
4. Expandir validação do modelo e editor de propriedades sem permitir execução de código arbitrário.

## Referências oficiais consultadas em 26/10/2026

- [Política de versões do Laravel](https://laravel.com/framework/docs/releases)
- [Bancos suportados pelo Laravel](https://laravel.com/framework/docs/database/config)
- [Manutenção das versões MariaDB](https://mariadb.org/about/)
- [Documentação Flowable Open Source](https://www.flowable.com/open-source/docs/oss-introduction/)
- [API REST do Flowable](https://www.flowable.com/open-source/docs/dmn/ch07-REST)
- [Versões do bpmn-js](https://github.com/bpmn-io/bpmn-js/releases)
- [Licença bpmn.io](https://github.com/bpmn-io/bpmn-js/blob/develop/LICENSE)
