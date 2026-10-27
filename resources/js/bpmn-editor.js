import BpmnModeler from 'bpmn-js/lib/Modeler';
import BpmnViewer from 'bpmn-js/lib/Viewer';

const canvas = document.querySelector('#bpmn-canvas');

if (canvas) {
    const status = document.querySelector('#editor-status');
    const fileInput = document.querySelector('#bpmn-file');
    const readOnly = canvas.dataset.published === 'true';
    const Modeler = readOnly ? BpmnViewer : BpmnModeler;
    const modeler = new Modeler({
        container: canvas,
        keyboard: { bindTo: document },
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
    const elementIdLabel = document.querySelector('#selected-element-id');
    const taskInstructionsField = document.querySelector('#task-instructions-field');
    const taskInstructionsInput = document.querySelector('#selected-task-instructions');
    const applyElementNameButton = document.querySelector('#apply-element-name');
    const elementTypeNames = {
        'bpmn:UserTask': 'Tarefa humana',
        'bpmn:ServiceTask': 'Tarefa de serviço',
        'bpmn:StartEvent': 'Evento de início',
        'bpmn:EndEvent': 'Evento de fim',
        'bpmn:ExclusiveGateway': 'Gateway exclusivo',
        'bpmn:ParallelGateway': 'Gateway paralelo',
        'bpmn:SequenceFlow': 'Conexão',
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

    modeler.on('selection.changed', ({ newSelection }) => {
        selectedElement = newSelection.length === 1 ? (newSelection[0].labelTarget || newSelection[0]) : null;

        if (!selectedElement?.businessObject) {
            propertiesEmpty.hidden = false;
            propertiesForm.hidden = true;
            return;
        }

        const businessObject = selectedElement.businessObject;
        elementTypeLabel.textContent = elementTypeNames[businessObject.$type] || businessObject.$type.replace('bpmn:', '');
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
    });

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
        } catch (error) {
            setStatus(`Não foi possível salvar o rascunho: ${error.message}`, true);
        }
    });

    const publishButton = document.querySelector('#publish-bpmn');
    publishButton?.addEventListener('click', async () => {
        if (!window.confirm('Publicar esta versão no motor Flowable? Depois da publicação, o rascunho ficará bloqueado para edição.')) return;

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
