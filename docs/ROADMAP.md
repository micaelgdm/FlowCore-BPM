# Roadmap

## Fase 0 — Preparação

- [x] Verificar Windows, XAMPP, versões do PHP, Apache e banco.
- [x] Atualizar XAMPP: PHP 8.5.12, Apache 2.4.65 e MariaDB 10.11.16.
- [x] Confirmar Apache em `localhost` e conexão autenticada ao MariaDB.
- [x] Verificar Composer, extensões PHP e Git.
- [x] Inicializar repositório e documentação básica.
- [x] Validar compatibilidade Laravel 13/PHP 8.5 e MariaDB 10.11.

## Fase 1 — Fundação

- [x] Laravel Framework 13.38.0 e dependências travadas.
- [x] Banco `flowcore_bpm` e conta dedicada com privilégio restrito à base.
- [x] Migrations padrão aplicadas; `.env` local ignorado pelo Git.
- [x] VirtualHost seguro apontando para `public/`; Laravel confirmado pelo navegador.
- [x] Testes da fundação: 7 passaram, 20 assertions.

## Fase 2 — Editor BPMN (fatia inicial)

- [x] Node.js/npm portátil disponíveis sem alterar a instalação global.
- [x] bpmn-js 18.30.1 instalado e licença/watermark preservados.
- [x] Login Master; cadastro público desativado; rotas administrativas protegidas.
- [x] Criar/listar rascunhos de processo.
- [x] Editar BPMN 2.0 com início, tarefa humana, fim, paleta e zoom.
- [x] Abrir e exportar XML; salvar e reabrir rascunho no MariaDB.
- [x] Validar XML do lado do servidor (BPMN 2.0, sem DTD/entidades externas).
- [x] Build Vite concluído; editor confirmado visualmente no navegador.
- [x] Configurar um formulário simples por rascunho e publicar o esquema imutável junto com BPMN.
- [x] Validar respostas no servidor e iniciar instâncias Flowable pelo portal autorizado.
- [x] Registrar respostas e estado local; impedir reenvio duplicado com chave idempotente.
- [x] Mostrar catálogo, formulário e solicitações próprias para usuários finais.
- [x] Separar Minhas solicitações do catálogo de processos e paginar a lista com 15 itens por página.
- [x] Separar permissões para consultar, solicitar e aprovar processos.
- [x] Atribuir a tarefa humana ao aprovador e concluir a aprovação com justificativa e histórico.
- [x] Cadastrar grupos de trabalho e associar usuários ativos às equipes.
- [x] Configurar grupo responsável em cada tarefa BPMN e publicar como grupo candidato no Flowable.
- [x] Criar fila de tarefas por grupo, com ação para um membro assumir e concluir a tarefa.
- [x] Criar formulários próprios por tarefa, persistir as respostas e mostrá-las no histórico da solicitação.
- [x] Permitir que aprovação e cadastro final visualizem os dados preenchidos nas etapas anteriores.
- [x] Acrescentar ao exemplo a etapa de cadastro externo após a aprovação, com protocolo e observação do registro.
- [x] Validar em execução real o fork/join paralelo, devolução ao setor escolhido, retorno à Diretoria e aprovação final no Flowable.
- [x] Adicionar rejeição e saídas condicionais aprovado/rejeitado em gateways exclusivos.
- [x] Devolver uma aprovação para a tarefa de preenchimento escolhida e retornar à aprovação após a correção.
- [ ] Validar um ciclo de correção completo no Flowable, incluindo escolha de departamento e retorno à Diretoria.
- [x] Editar nomes e instruções de tarefas humanas em painel de propriedades, mantendo os IDs usados pelo roteamento e exibindo as instruções ao executor.
- [ ] Ampliar propriedades editáveis e cenários de diagrama.
- [ ] Validar round-trip entre bpmn-js e o motor escolhido antes de publicar.

## Fase 3 — Prova de conceito do motor

- [x] Confirmar JDK 17+ e instalar Flowable Open Source em ambiente local.
- [x] Executar Flowable 8.0.0 via REST com Spring Boot 4.0.0 e autenticação Basic local.
- [x] Implantar um BPMN de teste, iniciar uma instância e concluir uma tarefa humana via REST.
- [x] Criar a base `flowcore_engine` e uma conta MariaDB dedicada com acesso apenas a essa base.
- [x] Executar o Flowable com MariaDB, concluir a tarefa humana e recuperar o histórico após reiniciar o motor.
- [x] Integrar no Laravel a consulta autenticada das definições do motor, restrita a Master.
- [x] Publicar um rascunho BPMN pelo editor, persistir os IDs Flowable e recuperar a publicação por um nome determinístico sem duplicá-la.
- [x] Iniciar instâncias pelo portal com chave de negócio idempotente e variáveis do formulário.
- [ ] Integrar teste real de iniciar uma solicitação, atribuir/aprovar uma tarefa humana e recuperar seu estado depois de reiniciar o motor.
- [x] Reconciliar sob demanda o status das solicitações em andamento com runtime/histórico do Flowable ao abrir detalhes e a lista do solicitante; exibir encerramento registrado no motor.
- [x] Agendar reconciliação incremental do status das solicitações com o Flowable, em lotes limitados e protegidos contra atualização concorrente.
- [x] Criar nova versão editável e alternar a publicação ativa sem alterar instâncias existentes; o portal inicia somente na versão ativa.

## Fases seguintes

4. Formulários dinâmicos — formulários iniciais e por etapa aceitam anexos privados e campos condicionais, com validação no navegador/servidor e autorização de download.
5. Aprovações e regras — aprovação, rejeição com retorno configurável à etapa de preenchimento e retorno à aprovação implementados e validados no Flowable para o exemplo; ampliar cenários de execução.
6. Notificações — contador, e-mail assíncrono de nova tarefa, alertas de prazo próximo/vencido, avisos de aprovação/devolução/rejeição/conclusão ao solicitante e monitoramento Master do outbox com reenvio manual implementados.
7. Auditoria, prazos, indicadores e recursos corporativos — prazos por tarefa, trilha de auditoria, acompanhamento da etapa atual e métricas de duração média por etapa, atrasos e pendências por grupo implementados.
8. Implantação interna.

Recursos recentes: gateways inclusivos podem devolver para múltiplas áreas em paralelo; formulários aceitam anexos privados e campos condicionais; o painel Master apresenta tempos por etapa, atrasos e pendências por grupo; páginas de Formulário/Equipes de versões publicadas são somente para leitura. O ciclo de devolução multiárea ainda precisa de validação ponta a ponta no Flowable.

## Ambiente e comandos

| Componente | Versão/local |
| --- | --- |
| PHP | 8.5.12 em `C:\xamp85` |
| Laravel | 13.38.0 |
| Apache | 2.4.65, VirtualHost local para `C:\xampp\htdocs\BPMN\public` |
| MariaDB | 10.11.16, database `flowcore_bpm` |
| Composer | 2.9.5; chamar via PHP do XAMPP novo |
| Node.js/npm | 24.22.1 / 11.19.1 em `.tools/`, ignorado pelo Git |
| bpmn-js | 18.30.1 |
| JDK | Temurin 17.0.20.1 em `.tools/`, sem alterar o Java global |
| Maven | 3.10.0 em `.tools/`, sem alterar o PATH global |
| Flowable | 8.0.0 em `127.0.0.1:8091`; MariaDB `flowcore_engine`, conta dedicada |

O terminal global ainda resolve `php`/`composer` para `C:\xampp` antigo e `java` para o JRE 8 da Oracle. A prova usa executáveis portáteis em `.tools/`. O Flowable e a aplicação Laravel usam bases MariaDB distintas e contas restritas.

Para iniciar o motor, execute `powershell -ExecutionPolicy Bypass -File .\engine-poc\start.ps1` na raiz. As credenciais REST e MariaDB estão no arquivo local ignorado `.env.flowable`; o serviço escuta somente em `127.0.0.1:8091`.
