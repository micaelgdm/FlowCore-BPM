import BpmnModeler from 'bpmn-js/lib/Modeler';
import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';

const portugueseTranslations = {
    'Task': 'Tarefa',
    'User task': 'Tarefa humana',
    'Service task': 'Tarefa de serviço',
    'Send task': 'Tarefa de envio',
    'Receive task': 'Tarefa de recebimento',
    'Manual task': 'Tarefa manual',
    'Business rule task': 'Tarefa de regra de negócio',
    'Script task': 'Tarefa de script',
    'Call activity': 'Atividade de chamada',
    'Transaction': 'Transação',
    'Event sub-process': 'Subprocesso de evento',
    'Sub-process (collapsed)': 'Subprocesso recolhido',
    'Sub-process (expanded)': 'Subprocesso expandido',
    'Ad-hoc sub-process (collapsed)': 'Subprocesso ad hoc recolhido',
    'Ad-hoc sub-process (expanded)': 'Subprocesso ad hoc expandido',
    'Sub-process': 'Subprocesso',
    'Ad-hoc sub-process': 'Subprocesso ad hoc',
    'Exclusive gateway': 'Gateway exclusivo',
    'Parallel gateway': 'Gateway paralelo',
    'Inclusive gateway': 'Gateway inclusivo',
    'Complex gateway': 'Gateway complexo',
    'Event-based gateway': 'Gateway baseado em eventos',
    'Data store reference': 'Referência a armazenamento de dados',
    'Data object reference': 'Referência a objeto de dados',
    'Expanded pool/participant': 'Pool/participante expandido',
    'Empty pool/participant': 'Pool/participante vazio',
    'Empty pool/participant (removes content)': 'Pool/participante vazio (remove o conteúdo)',
    'Start event': 'Evento de início',
    'Intermediate throw event': 'Evento intermediário de disparo',
    'Boundary event': 'Evento de borda',
    'End event': 'Evento de fim',
    'Message start event': 'Evento de início por mensagem',
    'Timer start event': 'Evento de início por temporizador',
    'Conditional start event': 'Evento de início condicional',
    'Signal start event': 'Evento de início por sinal',
    'Error start event': 'Evento de início por erro',
    'Escalation start event': 'Evento de início por escalonamento',
    'Compensation start event': 'Evento de início por compensação',
    'Message start event (non-interrupting)': 'Evento de início por mensagem (não interruptivo)',
    'Timer start event (non-interrupting)': 'Evento de início por temporizador (não interruptivo)',
    'Conditional start event (non-interrupting)': 'Evento de início condicional (não interruptivo)',
    'Signal start event (non-interrupting)': 'Evento de início por sinal (não interruptivo)',
    'Escalation start event (non-interrupting)': 'Evento de início por escalonamento (não interruptivo)',
    'Message intermediate catch event': 'Evento intermediário de captura de mensagem',
    'Message intermediate throw event': 'Evento intermediário de disparo de mensagem',
    'Timer intermediate catch event': 'Evento intermediário de captura de temporizador',
    'Escalation intermediate throw event': 'Evento intermediário de disparo de escalonamento',
    'Conditional intermediate catch event': 'Evento intermediário de captura condicional',
    'Link intermediate catch event': 'Evento intermediário de captura de ligação',
    'Link intermediate throw event': 'Evento intermediário de disparo de ligação',
    'Compensation intermediate throw event': 'Evento intermediário de disparo de compensação',
    'Signal intermediate catch event': 'Evento intermediário de captura de sinal',
    'Signal intermediate throw event': 'Evento intermediário de disparo de sinal',
    'Message boundary event': 'Evento de borda por mensagem',
    'Timer boundary event': 'Evento de borda por temporizador',
    'Escalation boundary event': 'Evento de borda por escalonamento',
    'Conditional boundary event': 'Evento de borda condicional',
    'Error boundary event': 'Evento de borda por erro',
    'Cancel boundary event': 'Evento de borda de cancelamento',
    'Signal boundary event': 'Evento de borda por sinal',
    'Compensation boundary event': 'Evento de borda por compensação',
    'Message boundary event (non-interrupting)': 'Evento de borda por mensagem (não interruptivo)',
    'Timer boundary event (non-interrupting)': 'Evento de borda por temporizador (não interruptivo)',
    'Escalation boundary event (non-interrupting)': 'Evento de borda por escalonamento (não interruptivo)',
    'Conditional boundary event (non-interrupting)': 'Evento de borda condicional (não interruptivo)',
    'Signal boundary event (non-interrupting)': 'Evento de borda por sinal (não interruptivo)',
    'Message end event': 'Evento de fim por mensagem',
    'Escalation end event': 'Evento de fim por escalonamento',
    'Error end event': 'Evento de fim por erro',
    'Cancel end event': 'Evento de fim por cancelamento',
    'Compensation end event': 'Evento de fim por compensação',
    'Signal end event': 'Evento de fim por sinal',
    'Terminate end event': 'Evento de fim terminal',
    'Sequence flow': 'Fluxo de sequência',
    'Default flow': 'Fluxo padrão',
    'Conditional flow': 'Fluxo condicional',
    'Activate hand tool': 'Ativar ferramenta de mão',
    'Activate lasso tool': 'Ativar seleção por área',
    'Activate create/remove space tool': 'Ativar ferramenta de espaço',
    'Activate global connect tool': 'Ativar ferramenta de conexão',
    'Create start event': 'Criar evento de início',
    'Create intermediate/boundary event': 'Criar evento intermediário/de borda',
    'Create end event': 'Criar evento de fim',
    'Create gateway': 'Criar gateway',
    'Create task': 'Criar tarefa',
    'Create data object reference': 'Criar objeto de dados',
    'Create data store reference': 'Criar repositório de dados',
    'Create expanded sub-process': 'Criar subprocesso expandido',
    'Create pool/participant': 'Criar pool/participante',
    'Create group': 'Criar grupo',
    'Append receive task': 'Adicionar tarefa de recebimento',
    'Append message intermediate catch event': 'Adicionar evento intermediário de captura de mensagem',
    'Append timer intermediate catch event': 'Adicionar evento intermediário de captura de temporizador',
    'Append conditional intermediate catch event': 'Adicionar evento intermediário de captura condicional',
    'Append signal intermediate catch event': 'Adicionar evento intermediário de captura de sinal',
    'Append compensation activity': 'Adicionar atividade compensatória',
    'Append end event': 'Adicionar evento de fim',
    'Append gateway': 'Adicionar gateway',
    'Append task': 'Adicionar tarefa',
    'Append intermediate/boundary event': 'Adicionar evento intermediário/de borda',
    'Delete': 'Excluir',
    'Add lane above': 'Adicionar raia acima',
    'Divide into two lanes': 'Dividir em duas raias',
    'Divide into three lanes': 'Dividir em três raias',
    'Add lane below': 'Adicionar raia abaixo',
    'Change element': 'Alterar tipo do elemento',
    'Add text annotation': 'Adicionar anotação de texto',
    'Connect to other element': 'Conectar a outro elemento',
    'Connect using association': 'Conectar usando associação',
    'Connect using data input association': 'Conectar usando associação de entrada de dados',
    'Align elements': 'Alinhar elementos',
    'Align elements left': 'Alinhar elementos à esquerda',
    'Align elements center': 'Centralizar elementos horizontalmente',
    'Align elements right': 'Alinhar elementos à direita',
    'Align elements top': 'Alinhar elementos ao topo',
    'Align elements middle': 'Centralizar elementos verticalmente',
    'Align elements bottom': 'Alinhar elementos à base',
    'Distribute elements horizontally': 'Distribuir elementos horizontalmente',
    'Distribute elements vertically': 'Distribuir elementos verticalmente',
    'Open {element}': 'Abrir {element}',
    'Parallel multi-instance': 'Múltiplas instâncias paralelas',
    'Sequential multi-instance': 'Múltiplas instâncias sequenciais',
    'Loop': 'Repetição',
    'Collection': 'Coleção',
    'Participant multiplicity': 'Multiplicidade do participante',
    'Toggle non-interrupting': 'Alternar evento não interruptivo',
    'Search in diagram': 'Buscar no diagrama',
};

const portugueseTranslate = (template, replacements = {}) => {
    const translated = portugueseTranslations[template] || template;
    return translated.replace(/\{([^}]+)\}/g, (match, key) => replacements[key] ?? match);
};

const portugueseTranslationModule = {
    translate: ['value', portugueseTranslate],
};

const canvas = document.querySelector('#bpmn-canvas');

if (canvas) {
    const status = document.querySelector('#editor-status');
    const fileInput = document.querySelector('#bpmn-file');
    const readOnly = canvas.dataset.published === 'true';
    const Modeler = readOnly ? BpmnNavigatedViewer : BpmnModeler;
    const modeler = new Modeler({
        container: canvas,
        keyboard: { bindTo: document },
        additionalModules: [portugueseTranslationModule],
    });

    const starterDiagram = `<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
  xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
  id="Definitions_FlowCore" targetNamespace="https://flowcore.local/bpmn">
  <bpmn:process id="Process_Request" name="Aprovação de solicitação" isExecutable="false">
    <bpmn:startEvent id="StartEvent_Request" name="Início" />
    <bpmn:userTask id="Task_Approval" name="Aprovar solicitação" />
    <bpmn:endEvent id="EndEvent_Request" name="Fim" />
    <bpmn:sequenceFlow id="Flow_Start_Approval" sourceRef="StartEvent_Request" targetRef="Task_Approval" />
    <bpmn:sequenceFlow id="Flow_Approval_End" sourceRef="Task_Approval" targetRef="EndEvent_Request" />
  </bpmn:process>
  <bpmndi:BPMNDiagram id="Diagram_Request">
    <bpmndi:BPMNPlane id="Plane_Request" bpmnElement="Process_Request">
      <bpmndi:BPMNShape id="StartEvent_Request_di" bpmnElement="StartEvent_Request">
        <dc:Bounds x="300" y="122" width="36" height="36" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="Task_Approval_di" bpmnElement="Task_Approval">
        <dc:Bounds x="410" y="100" width="120" height="80" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="EndEvent_Request_di" bpmnElement="EndEvent_Request">
        <dc:Bounds x="670" y="122" width="36" height="36" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNEdge id="Flow_Start_Approval_di" bpmnElement="Flow_Start_Approval">
        <di:waypoint x="336" y="140" />
        <di:waypoint x="410" y="140" />
      </bpmndi:BPMNEdge>
      <bpmndi:BPMNEdge id="Flow_Approval_End_di" bpmnElement="Flow_Approval_End">
        <di:waypoint x="530" y="140" />
        <di:waypoint x="670" y="140" />
      </bpmndi:BPMNEdge>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>`;

    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.classList.toggle('is-error', isError);
    };

    const propertiesEmpty = document.querySelector('#element-properties-empty');
    const propertiesForm = document.querySelector('#element-properties-form');
    const elementNameInput = document.querySelector('#selected-element-name');
    const elementTypeLabel = document.querySelector('#selected-element-type');
    const elementDescriptionLabel = document.querySelector('#selected-element-description');
    const elementIdLabel = document.querySelector('#selected-element-id');
    const taskInstructionsField = document.querySelector('#task-instructions-field');
    const taskInstructionsInput = document.querySelector('#selected-task-instructions');
    const applyElementNameButton = document.querySelector('#apply-element-name');
    const elementTypeNames = {
        'bpmn:Task': 'Tarefa',
        'bpmn:UserTask': 'Tarefa humana',
        'bpmn:ServiceTask': 'Tarefa de serviço',
        'bpmn:ManualTask': 'Tarefa manual',
        'bpmn:SendTask': 'Tarefa de envio',
        'bpmn:ReceiveTask': 'Tarefa de recebimento',
        'bpmn:BusinessRuleTask': 'Tarefa de regra de negócio',
        'bpmn:ScriptTask': 'Tarefa de script',
        'bpmn:StartEvent': 'Evento de início',
        'bpmn:IntermediateCatchEvent': 'Evento intermediário de captura',
        'bpmn:IntermediateThrowEvent': 'Evento intermediário de disparo',
        'bpmn:BoundaryEvent': 'Evento de borda',
        'bpmn:EndEvent': 'Evento de fim',
        'bpmn:ExclusiveGateway': 'Gateway exclusivo',
        'bpmn:InclusiveGateway': 'Gateway inclusivo',
        'bpmn:ParallelGateway': 'Gateway paralelo',
        'bpmn:EventBasedGateway': 'Gateway baseado em eventos',
        'bpmn:ComplexGateway': 'Gateway complexo',
        'bpmn:SequenceFlow': 'Conexão',
        'bpmn:SubProcess': 'Subprocesso',
        'bpmn:Transaction': 'Transação',
        'bpmn:CallActivity': 'Atividade de chamada',
        'bpmn:Participant': 'Pool/participante',
        'bpmn:Lane': 'Raia',
        'bpmn:DataObjectReference': 'Objeto de dados',
        'bpmn:DataStoreReference': 'Armazenamento de dados',
        'bpmn:TextAnnotation': 'Anotação de texto',
        'bpmn:Group': 'Grupo visual',
        'bpmn:Association': 'Associação',
        'bpmn:MessageFlow': 'Fluxo de mensagem',
    };
    const elementTypeDescriptions = {
        'bpmn:Task': 'Representa uma atividade de trabalho no processo. Use um tipo específico quando precisar indicar quem executa ou como a atividade acontece.',
        'bpmn:UserTask': 'Representa uma tarefa humana. Defina o usuário ou grupo responsável na seção Equipes e configure os campos que serão preenchidos nessa etapa.',
        'bpmn:ServiceTask': 'Representa uma atividade automática executada por um serviço ou integração. Ela precisa de uma implementação técnica no motor para executar uma ação.',
        'bpmn:ManualTask': 'Representa um trabalho feito manualmente fora do sistema. O motor não cria uma tarefa para um usuário preencher.',
        'bpmn:SendTask': 'Representa o envio de uma mensagem para outro participante ou sistema. Configure a integração que realizará o envio.',
        'bpmn:ReceiveTask': 'Representa uma etapa que aguarda uma mensagem de outro participante ou sistema.',
        'bpmn:BusinessRuleTask': 'Representa uma decisão ou cálculo feito por regras de negócio automatizadas.',
        'bpmn:ScriptTask': 'Representa uma rotina automática executada por um script configurado no motor de processos.',
        'bpmn:StartEvent': 'Marca onde o fluxo começa. Em uma solicitação, é o ponto de entrada do processo.',
        'bpmn:IntermediateCatchEvent': 'Faz o fluxo aguardar um evento, como uma mensagem, sinal ou temporizador, antes de continuar.',
        'bpmn:IntermediateThrowEvent': 'Emite um evento durante o fluxo, como uma mensagem ou sinal, e depois permite que o processo continue.',
        'bpmn:BoundaryEvent': 'Fica ligado à borda de uma atividade e reage a um evento enquanto essa atividade está em execução. Pode interromper a atividade, conforme sua configuração.',
        'bpmn:EndEvent': 'Marca o fim deste caminho do processo. Se houver outros caminhos paralelos, eles também precisam terminar para concluir a instância.',
        'bpmn:ExclusiveGateway': 'Escolhe apenas um caminho de saída, conforme as condições configuradas nos fluxos que partem deste gateway.',
        'bpmn:InclusiveGateway': 'Pode seguir por um ou mais caminhos de saída, conforme as condições atendidas.',
        'bpmn:ParallelGateway': 'Abre caminhos que são executados em paralelo ou sincroniza caminhos paralelos antes de continuar.',
        'bpmn:EventBasedGateway': 'Aguarda eventos concorrentes e segue pelo caminho correspondente ao primeiro evento que ocorrer.',
        'bpmn:ComplexGateway': 'Direciona o fluxo por uma regra de combinação mais complexa entre caminhos de entrada ou saída.',
        'bpmn:SequenceFlow': 'Conecta elementos e define a ordem do fluxo. Em saídas de um gateway, pode ter uma condição ou ser o caminho padrão.',
        'bpmn:SubProcess': 'Agrupa várias atividades em um fluxo interno, mantendo o diagrama principal mais organizado.',
        'bpmn:Transaction': 'Agrupa atividades que devem ser tratadas como uma transação, com resultado consistente ou compensação.',
        'bpmn:CallActivity': 'Chama outro processo reutilizável ou uma atividade definida separadamente.',
        'bpmn:Participant': 'Representa um participante ou pool do processo e delimita suas atividades.',
        'bpmn:Lane': 'Organiza atividades dentro de um participante, normalmente por setor, papel ou responsável.',
        'bpmn:DataObjectReference': 'Representa um documento ou dado utilizado ou produzido pelas atividades. É informativo e não controla a sequência do fluxo.',
        'bpmn:DataStoreReference': 'Representa dados persistentes consultados ou atualizados pelas atividades. É informativo e não controla a sequência do fluxo.',
        'bpmn:TextAnnotation': 'Adiciona uma observação explicativa ao diagrama. Não altera a execução do processo.',
        'bpmn:Group': 'Agrupa elementos visualmente para facilitar a leitura. Não altera a execução do processo.',
        'bpmn:Association': 'Liga uma anotação ou artefato a um elemento. Não determina a ordem de execução.',
        'bpmn:MessageFlow': 'Representa a troca de mensagens entre participantes diferentes; não define a sequência interna do processo.',
    };
    const elementDescription = (businessObject) => {
        if (businessObject.$type === 'bpmn:SequenceFlow') {
            if (businessObject.sourceRef?.default === businessObject) {
                return 'Este é o caminho padrão. Ele será seguido quando nenhuma outra condição de saída do gateway for atendida.';
            }
            if (businessObject.conditionExpression) {
                return 'Este caminho tem uma condição e só será seguido quando ela for atendida.';
            }
        }

        return elementTypeDescriptions[businessObject.$type]
            || 'Representa um elemento BPMN do processo. Seu comportamento depende do tipo e das configurações associadas.';
    };
    let selectedElement = null;

    const currentTaskInstructions = (element) => element?.businessObject?.documentation?.[0]?.text || '';
    const updateApplyState = () => {
        if (!selectedElement) {
            applyElementNameButton.disabled = true;
            return;
        }

        const businessObject = selectedElement.businessObject;
        const nameChanged = elementNameInput.value.trim() !== (businessObject.name || '');
        const isUserTask = businessObject.$type === 'bpmn:UserTask';
        const instructionsChanged = isUserTask
            && taskInstructionsInput.value.trim() !== currentTaskInstructions(selectedElement);
        applyElementNameButton.disabled = readOnly || (!nameChanged && !instructionsChanged);
    };

    const showElementProperties = (elements = []) => {
        selectedElement = elements.length === 1 ? (elements[0].labelTarget || elements[0]) : null;

        if (!selectedElement?.businessObject) {
            propertiesEmpty.hidden = false;
            propertiesForm.hidden = true;
            return;
        }

        const businessObject = selectedElement.businessObject;
        elementTypeLabel.textContent = elementTypeNames[businessObject.$type] || businessObject.$type.replace('bpmn:', '');
        elementDescriptionLabel.textContent = elementDescription(businessObject);
        elementIdLabel.textContent = businessObject.id || selectedElement.id;
        elementNameInput.value = businessObject.name || '';
        const isUserTask = businessObject.$type === 'bpmn:UserTask';
        taskInstructionsField.hidden = !isUserTask;
        taskInstructionsInput.value = isUserTask ? currentTaskInstructions(selectedElement) : '';
        elementNameInput.disabled = readOnly;
        taskInstructionsInput.disabled = readOnly;
        propertiesEmpty.hidden = true;
        propertiesForm.hidden = false;
        updateApplyState();
    };

    if (readOnly) {
        // The navigated viewer has no editing tools. Clicking an element still
        // reveals its details in the properties panel for review.
        modeler.on('element.click', ({ element }) => showElementProperties([element]));
        modeler.on('canvas.click', () => showElementProperties());
    } else {
        modeler.on('selection.changed', ({ newSelection }) => showElementProperties(newSelection));
    }

    elementNameInput.addEventListener('input', updateApplyState);
    taskInstructionsInput.addEventListener('input', updateApplyState);

    propertiesForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (readOnly || !selectedElement) return;

        const modeling = modeler.get('modeling');
        modeling.updateLabel(selectedElement, elementNameInput.value.trim());

        if (selectedElement.businessObject.$type === 'bpmn:UserTask') {
            const existingDocumentation = selectedElement.businessObject.documentation || [];
            const documentation = existingDocumentation.slice(1);
            const instructions = taskInstructionsInput.value.trim();
            if (instructions) {
                documentation.unshift(modeler.get('moddle').create('bpmn:Documentation', { text: instructions }));
            }
            modeling.updateProperties(selectedElement, { documentation });
        }

        setStatus('Propriedades atualizadas no diagrama. Salve o rascunho para persistir as alterações.');
        applyElementNameButton.disabled = true;
    });

    const fitDiagram = () => {
        const canvasApi = modeler.get('canvas');
        canvasApi.zoom('fit-viewport');
        let viewbox = canvasApi.viewbox();

        // On narrow screens, the BPMN palette overlays the canvas and hides
        // part of the diagram. Reserve room for it before centering the flow.
        if (window.matchMedia('(max-width: 700px)').matches) {
            const availableWidth = Math.max(1, canvas.clientWidth - 120);
            const fittedWidth = viewbox.width * viewbox.scale;
            const scale = viewbox.scale * Math.min(1, availableWidth / fittedWidth);
            canvasApi.zoom(scale);
            viewbox = canvasApi.viewbox();
        }

        const paletteClearance = (window.matchMedia('(max-width: 700px)').matches ? 50 : 110) / viewbox.scale;
        canvasApi.viewbox({
            x: viewbox.x - paletteClearance,
            y: viewbox.y,
            width: viewbox.width,
            height: viewbox.height,
        });
    };

    const importDiagram = async (xml, message = 'Diagrama aberto. Salve para persistir alterações.') => {
        try {
            const { warnings } = await modeler.importXML(xml);
            fitDiagram();
            setStatus(warnings.length ? `Diagrama aberto com ${warnings.length} aviso(s).` : message);
        } catch (error) {
            setStatus(`Não foi possível abrir o BPMN: ${error.message}`, true);
        }
    };

    const draftSaveFeedback = document.querySelector('#draft-save-feedback');
    let draftSaveFeedbackTimer;
    const showDraftSaveFeedback = (message) => {
        if (!draftSaveFeedback) return;
        window.clearTimeout(draftSaveFeedbackTimer);
        draftSaveFeedback.textContent = message;
        draftSaveFeedback.hidden = false;
        draftSaveFeedbackTimer = window.setTimeout(() => {
            draftSaveFeedback.hidden = true;
        }, 4500);
    };

    document.querySelector('#save-bpmn')?.addEventListener('click', async () => {
        try {
            const { xml } = await modeler.saveXML({ format: true });
            const response = await fetch(canvas.dataset.saveUrl, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ bpmn_xml: xml }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Falha ao salvar o rascunho.');
            setStatus('Rascunho salvo no servidor.');
            showDraftSaveFeedback('Rascunho salvo com sucesso.');
        } catch (error) {
            setStatus(`Não foi possível salvar o rascunho: ${error.message}`, true);
        }
    });

    const publishButton = document.querySelector('#publish-bpmn');
    const publishFeedback = document.querySelector('#publish-feedback');
    publishButton?.addEventListener('click', async () => {
        if (!await window.flowcoreConfirm('Publicar esta versão no motor Flowable? Depois da publicação, o rascunho ficará bloqueado para edição.')) return;

        publishButton.disabled = true;
        setStatus('Salvando o diagrama e publicando no Flowable…');

        try {
            const { xml } = await modeler.saveXML({ format: true });
            const saveResponse = await fetch(canvas.dataset.saveUrl, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ bpmn_xml: xml }),
            });
            const saveResult = await saveResponse.json();
            if (!saveResponse.ok) throw new Error(saveResult.message || 'Falha ao salvar o rascunho.');

            const response = await fetch(canvas.dataset.publishUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Falha ao publicar a versão.');

            setStatus(`${result.message} Recarregando…`);
            window.location.reload();
        } catch (error) {
            setStatus(`Não foi possível publicar: ${error.message}`, true);
            if (publishFeedback) {
                publishFeedback.textContent = `Publicação interrompida. ${error.message}`;
                publishFeedback.hidden = false;
                publishFeedback.scrollIntoView({ behavior: 'smooth', block: 'start' });
                publishFeedback.focus({ preventScroll: true });
            }
            publishButton.disabled = false;
        }
    });

    document.querySelector('#export-bpmn')?.addEventListener('click', async () => {
        try {
            const { xml } = await modeler.saveXML({ format: true });
            const url = URL.createObjectURL(new Blob([xml], { type: 'application/xml' }));
            const link = document.createElement('a');
            link.href = url;
            link.download = 'processo-flowcore.bpmn';
            link.click();
            URL.revokeObjectURL(url);
            setStatus('Cópia XML BPMN exportada.');
        } catch (error) {
            setStatus(`Não foi possível exportar o BPMN: ${error.message}`, true);
        }
    });

    document.querySelector('#open-bpmn')?.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', async (event) => {
        const [file] = event.target.files;
        if (file) await importDiagram(await file.text());
        event.target.value = '';
    });

    document.querySelector('#zoom-in').addEventListener('click', () => modeler.get('zoomScroll').stepZoom(1));
    document.querySelector('#zoom-out').addEventListener('click', () => modeler.get('zoomScroll').stepZoom(-1));
    document.querySelector('#fit-canvas').addEventListener('click', fitDiagram);

    let lastMobileLayout = window.matchMedia('(max-width: 700px)').matches;
    window.addEventListener('resize', () => {
        const mobileLayout = window.matchMedia('(max-width: 700px)').matches;
        if (mobileLayout !== lastMobileLayout) {
            lastMobileLayout = mobileLayout;
            requestAnimationFrame(fitDiagram);
        }
    });

    if (!readOnly) modeler.on('commandStack.changed', () => setStatus('Alterações ainda não salvas.'));
    importDiagram(canvas.dataset.initialXml || starterDiagram, readOnly ? 'Versão publicada · somente leitura.' : undefined);
}
