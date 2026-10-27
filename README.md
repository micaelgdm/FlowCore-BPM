# FlowCore BPM

Plataforma corporativa de gestão de processos. A fundação Laravel, o editor BPMN, publicação de versões iniciais no Flowable, cadastro Master de usuários e grupos, formulários de solicitação e por etapa, encaminhamento por grupo, aprovação e retorno para correção estão disponíveis localmente. Novas versões e validação end-to-end do paralelismo e dos ciclos de correção continuam em desenvolvimento.

## Ambiente validado

- XAMPP em `C:\xamp85`: PHP 8.5.12, Apache 2.4.65 e MariaDB 10.11.16.
- Laravel Framework 13.38.0; Composer 2.9.5.
- Node.js 24.22.1 e npm 11.19.1 em `.tools/` (distribuição portátil, ignorada pelo Git).
- bpmn-js 18.30.1; licença bpmn.io, incluindo o watermark obrigatório preservado.
- Flowable 8.0.0 em prova local, com JDK Temurin 17.0.20.1 e Maven 3.10.0 portáteis em `.tools/`.
- MariaDB `flowcore_bpm`, conta dedicada `flowcore_app` e migrations aplicadas.
- Apache responde `http://localhost/` e serve a aplicação por VirtualHost limitado a `public/` e conexões locais.

## Entrar e editar

Abra `http://localhost/`. O sistema encaminha para o login Master. O e-mail inicial é `admin@flowcore.local`; a senha gerada está na variável `FLOWCORE_MASTER_PASSWORD` do arquivo local `.env` (esse arquivo não é versionado).

Após entrar, crie um rascunho em **Processos**. No editor em `/master/processes/{id}/editor`, edite o diagrama, abra ou exporte BPMN e salve o rascunho. Antes de publicar, use **Formulário** para definir os campos que o solicitante deverá preencher. A publicação envia o BPMN ao Flowable e torna imutáveis o diagrama e os formulários daquela versão. No editor publicado, **Criar nova versão** abre um rascunho separado com as configurações e permissões copiadas; solicitações existentes continuam vinculadas à versão original.

O Master cria contas com perfil **Usuário**, organiza membros em **Grupos** e concede acesso de consulta ou início por processo. Em rascunhos, **Equipes** associa cada tarefa humana a um grupo, define se a etapa é de preenchimento ou aprovação e configura seus campos. Nas saídas do Gateway Exclusivo, configure uma rota de aprovação e rotas para as tarefas de correção. Ao devolver uma solicitação, o responsável pela aprovação escolhe a etapa de correção e registra o motivo; concluída a correção, a instância retorna à aprovação. Respostas e decisões aparecem no histórico. Instâncias legadas preservam o responsável que já tinham.

## Comandos locais

Use explicitamente o PHP do XAMPP atualizado enquanto o PATH do Windows apontar para a instalação antiga:

```powershell
& 'C:\xamp85\php\php.exe' artisan --version
& 'C:\xamp85\php\php.exe' artisan migrate:status
& 'C:\xamp85\php\php.exe' artisan test
& '.tools\node-v24.22.1-win-x64\npm.cmd' run build
```

Para Composer, `C:\composer\composer.phar` está disponível; o ZIP foi habilitado somente na instalação inicial via `-d extension=zip`.

## Prova local do Flowable

Na raiz do projeto, inicie o serviço com:

```powershell
powershell -ExecutionPolicy Bypass -File .\engine-poc\start.ps1
```

A API fica em `http://127.0.0.1:8091/process-api` e exige Basic Auth. O usuário e a senha aleatória estão no arquivo local ignorado `.env.flowable`. O serviço usa a base `flowcore_engine` no MariaDB, com conta dedicada e separada de `flowcore_bpm`. Pressione `Ctrl+C` no terminal para parar o serviço.

A prova implantou o BPMN de exemplo, iniciou o processo, concluiu uma tarefa humana e confirmou o histórico após reiniciar o serviço. A API exige autenticação e responde 401 a chamadas anônimas. O Laravel consulta definições por `GET /master/engine/process-definitions` e publica rascunhos pelo botão **Publicar versão** no editor. O endereço e as credenciais ficam em `.env` (`FLOWABLE_URL`, `FLOWABLE_USER`, `FLOWABLE_PASSWORD`). Cada processo publicado fica imutável até existir fluxo para criar outra versão.

## Documentação

- [Roadmap](docs/ROADMAP.md)
- [Arquitetura e decisões](docs/ARQUITETURA.md)
- [Padrões](docs/PADROES.md)
- [Changelog](CHANGELOG.md)

## Usuários e solicitações

- O cadastro público continua desativado; contas e perfis são administrados pelo Master.
- O formulário inicial suporta texto curto/longo, número, data, lista de opções e sim/não, com campos obrigatórios.
- Formulários são definidos em rascunhos e publicados junto com o BPMN; uma versão publicada é imutável.
- As respostas são validadas no servidor, guardadas como JSON e enviadas ao Flowable como variáveis do processo.
- Tarefas de preenchimento e aprovação/rejeição registram ator, data, comentário e campos no histórico local.
- Aprovação e devolução para uma tarefa de preenchimento podem seguir saídas configuradas em gateways exclusivos; execução completa dos ciclos ainda precisa de validação real no Flowable.

## Segurança

Não versione `.env`, credenciais, dados pessoais ou arquivos de negócio. Rotas do Master exigem autenticação e função Master; o VirtualHost local também exige conexão da própria máquina. A validação de XML rejeita DTD/entidades externas e não busca conteúdo pela rede.
