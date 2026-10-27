# Padrões iniciais

Estas regras são provisórias e serão ampliadas quando a arquitetura for aprovada.

- Validar entrada e autorização no servidor; a interface não é controle de segurança.
- Manter segredos e dados de negócio fora do Git.
- Persistir arquivos privados fora do document root público.
- Usar consultas parametrizadas/ORM; escapar saída HTML e proteger operações contra CSRF.
- Registrar alterações administrativas e decisões de negócio com autor, data e contexto.
- Não executar código arbitrário fornecido por administradores como regra de negócio.
- Publicações de processo deverão ser versionadas e imutáveis; instâncias registram a versão usada.
- Atualizar este documento conforme decisões técnicas forem aprovadas.

## Autenticação e diagramas

- Rotas administrativas exigem sessão Laravel autenticada e papel `master`; o cadastro público permanece desativado.
- Use `FLOWCORE_MASTER_EMAIL` e `FLOWCORE_MASTER_PASSWORD` somente no `.env` local; nunca versione o arquivo.
- Cada alteração de diagrama exige CSRF e autorização Master no servidor.
- Aceite XML BPMN limitado a 2 MB, valide namespace/elementos esperados e rejeite DTD/entidades externas. Não resolva conteúdo externo.
- Preserve o watermark e o aviso de copyright exigidos pela licença bpmn.io.
- Definições de formulário aceitam somente tipos e opções controlados pelo servidor; nunca avalie código informado em uma definição.
- Valide cada resposta contra o esquema imutável publicado e descarte chaves enviadas que não pertençam ao formulário.
- Use UUID como chave de idempotência ao iniciar uma instância no motor; não repita uma criação sem consultar primeiro a chave de negócio.
- Restrinja cada instância ao solicitante que a criou até que uma permissão explícita de consulta seja implementada.
- Antes de concluir uma tarefa, confira no servidor o assignee Flowable e se o usuário pertence ao grupo responsável pela etapa; o tipo configurado da etapa determina se exige aprovação.
- Grave a decisão e a justificativa localmente, mantendo o ID da tarefa Flowable único para evitar decisões duplicadas.
