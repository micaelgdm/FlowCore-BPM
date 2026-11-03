# Segurança e proteção de dados no FlowCore BPM

Este documento registra a linha de base técnica do FlowCore e os controles que precisam ser operados pela organização. Ele não substitui a política corporativa de privacidade, a avaliação jurídica nem a definição das responsabilidades de controlador e operador.

## Dados tratados pelo sistema

| Categoria | Onde aparece | Observação |
| --- | --- | --- |
| Identificação e conta | `users` | Nome, e-mail corporativo, perfil, estado da conta e hash de senha. |
| Dados de solicitações | `process_instances.form_data` | Respostas são definidas por cada processo; podem incluir dados pessoais se o formulário permitir. |
| Dados das tarefas | `process_task_decisions.form_data` e justificativas | Respostas, decisões e histórico de correções. |
| Anexos | armazenamento privado da aplicação | Arquivos de formulários e tarefas, servidos somente pela rota autenticada. |
| Roteamento e notificações | tabelas de tarefas, delegações e notificações | Identificadores de usuários, destinatários e estado da entrega. |
| Auditoria | `audit_events` | Ator, ação e alterações administrativas. Senhas não devem ser registradas. |
| Motor BPM | MariaDB do Flowable | Variáveis de processo usadas na execução; avaliar esse banco e seus backups como parte do inventário de dados. |

Formulários são configuráveis e podem coletar dados além de nome e e-mail. O administrador do processo deve limitar cada formulário aos dados necessários para a finalidade aprovada. Dados pessoais sensíveis, dados de crianças e documentos de identificação não devem ser solicitados sem análise e autorização específicas.

## Controles técnicos ativos

- Acesso exige autenticação; a área administrativa é restrita a contas Master ativas e o portal bloqueia contas inativas.
- O acesso a tarefas considera atribuição individual, grupo e responsável atual; um membro do grupo não pode acessar tarefa já atribuída a outra pessoa.
- Solicitações são visíveis ao solicitante no portal. A permissão de visualizar o catálogo do processo, por si só, não autoriza baixar anexos de solicitações alheias.
- Anexos ficam no disco privado, são baixados pela aplicação após autorização e usam resposta `no-store`, `nosniff` e tipo de conteúdo genérico.
- Login, solicitação de redefinição e redefinição de senha têm limitação de tentativas. A resposta de recuperação não revela se o e-mail existe.
- Senhas exigem no mínimo 12 caracteres e são armazenadas com hash. Cookies de sessão são criptografados por padrão e, em produção, seguros quando a conexão HTTPS é reconhecida.
- Respostas HTTP incluem cabeçalhos contra enquadramento, detecção indevida de tipo, envio de referenciador e acesso desnecessário a recursos do dispositivo. HSTS só é enviado em produção quando a requisição chega como HTTPS.
- Alterações de conta registram ator, conta afetada, perfil/estado anterior e novo e se houve redefinição de senha. O conteúdo de senhas e e-mails não é copiado para o evento de auditoria.

## Requisitos técnicos para publicação

1. Publicar somente com `APP_ENV=production`, `APP_DEBUG=false`, chave `APP_KEY` exclusiva e `APP_URL` HTTPS.
2. Configurar `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, banco com usuário de privilégio mínimo e senhas fora do repositório.
3. Disponibilizar o Flowable apenas na rede interna necessária, com autenticação e credenciais próprias; não expor o endpoint REST à internet.
4. Restringir permissões de escrita do sistema operacional a `storage` e `bootstrap/cache`; manter o `.env`, bancos, logs e anexos fora da raiz pública.
5. Aplicar TLS também entre componentes quando o tráfego sair do mesmo host/rede confiável. Proteger bancos e backups com criptografia e acesso restrito.
6. Definir rotina de atualização, revisão periódica de usuários/permissões, backup e teste de restauração.
7. Validar envio de e-mail com mensagens genéricas, sem incluir respostas ou anexos de solicitações.

## Decisões da organização necessárias antes do uso real

O código não pode determinar por conta própria a finalidade empresarial ou a base legal de cada processo. Antes de liberar os formulários, a área responsável por privacidade/jurídico deve registrar:

- controlador, operadores, encarregado ou canal de privacidade e contatos para titulares;
- finalidade e base legal por processo/categoria de dados, compartilhamentos e necessidade de dados sensíveis;
- prazo de retenção para solicitações, decisões, anexos, notificações, auditoria, logs e cópias de segurança, incluindo bloqueios legais;
- procedimento para confirmação, acesso, correção, oposição, informação e eliminação quando aplicável, com verificação de identidade e registro do atendimento;
- responsável por receber e tratar incidentes, preservar evidências, avaliar risco e coordenar comunicações;
- texto de aviso de privacidade que explique os tratamentos reais e um canal acessível aos titulares.

Não há exclusão automática de dados pessoais porque o prazo de retenção e as exceções legais ainda precisam ser definidos pela organização. O administrador pode excluir processos de teste, mas isso não é uma política de descarte para solicitações reais. Depois da aprovação dos prazos, deve-se implementar e validar o ciclo de retenção incluindo os dados do Flowable e backups.

## Resposta a incidentes

Incidentes com dados pessoais devem ser reportados imediatamente ao responsável interno designado. O controlador deve avaliar se o incidente pode causar risco ou dano relevante e coordenar a comunicação aos titulares e à ANPD dentro do prazo regulamentar. O sistema, por si só, não decide se a comunicação é obrigatória nem envia a notificação regulatória.

## Base de referência

- [Lei nº 13.709/2018 (LGPD)](https://www.gov.br/pt-br/lgpd/lei-no-13-709-18-lei-geral-de-protecao-de-dados-lgpd), em especial princípios, transparência, registro de operações, direitos dos titulares e segurança.
- [Resolução CD/ANPD nº 15/2024 e comunicação de incidente](https://www.gov.br/anpd/pt-br/canais_atendimento/agente-de-tratamento/comunicado-de-incidente-de-seguranca).
- [Guia de Segurança da Informação da ANPD](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/processo-guia-orientativo-sobre-seguranca-da-informacao-para-agentes-de-tratamento-de-pequeno-porte.pdf) como referência de medidas administrativas e técnicas.
- [Direitos dos titulares](https://www.gov.br/anpd/pt-br/assuntos/titular-de-dados-1/direito-dos-titulares) e [orientações sobre o encarregado/canal de contato](https://www.gov.br/anpd/pt-br/acesso-a-informacao/perguntas-frequentes).
