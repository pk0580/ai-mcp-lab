<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Agent Chat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .reasoning-step { border-left: 2px solid #e5e7eb; padding-left: 1rem; margin-bottom: 1rem; }
        .step-type { font-size: 0.75rem; font-weight: bold; text-transform: uppercase; color: #6b7280; }
    </style>
</head>
<body class="bg-gray-50 h-screen flex flex-col">
    <header class="bg-white shadow-sm p-4">
        <h1 class="text-xl font-semibold text-gray-800">AI Agent MCP Lab</h1>
    </header>

    <main class="flex-1 overflow-y-auto p-4 space-y-4" id="chat-container">
        <div id="welcome-message" class="text-center text-gray-500 mt-10">
            Введите запрос, чтобы начать работу с агентом.
        </div>
    </main>

    <footer class="bg-white p-4 border-t">
        <form id="chat-form" class="max-w-4xl mx-auto flex gap-2">
            @csrf
            <input type="text" id="prompt-input" name="prompt" placeholder="Напишите ваш запрос..."
                class="flex-1 border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            <button type="submit" id="send-btn" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:bg-gray-400">
                Отправить
            </button>
        </form>
    </footer>

    <script>
        const chatContainer = document.getElementById('chat-container');
        const chatForm = document.getElementById('chat-form');
        const promptInput = document.getElementById('prompt-input');
        const sendBtn = document.getElementById('send-btn');
        const welcomeMessage = document.getElementById('welcome-message');

        let currentRunId = null;
        let eventSource = null;

        function appendStep(step) {
            if (welcomeMessage) welcomeMessage.remove();

            const stepDiv = document.createElement('div');
            stepDiv.className = 'reasoning-step';

            const typeSpan = document.createElement('div');
            typeSpan.className = 'step-type';
            typeSpan.innerText = step.type || 'System';

            const contentDiv = document.createElement('div');
            contentDiv.className = 'text-gray-800 whitespace-pre-wrap';
            contentDiv.innerText = step.content || '';

            if (step.metadata && step.metadata.tool) {
                const toolInfo = document.createElement('div');
                toolInfo.className = 'text-xs text-blue-600 mt-1';
                toolInfo.innerText = `Tool: ${step.metadata.tool}(${JSON.stringify(step.metadata.args)})`;
                contentDiv.appendChild(toolInfo);
            }

            stepDiv.appendChild(typeSpan);
            stepDiv.appendChild(contentDiv);
            chatContainer.appendChild(stepDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        function startStreaming(runId) {
            if (eventSource) eventSource.close();

            eventSource = new EventSource(`/runs/${runId}/stream`);

            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);

                if (data.status) {
                    if (data.status === 'completed' || data.status === 'failed') {
                        eventSource.close();
                        sendBtn.disabled = false;
                        promptInput.disabled = false;
                        appendStep({type: 'system', content: `Run ${data.status}`});
                    }
                    return;
                }

                appendStep(data);
            };

            eventSource.onerror = function(err) {
                console.error("EventSource failed:", err);
                eventSource.close();
                sendBtn.disabled = false;
                promptInput.disabled = false;
            };
        }

        chatForm.onsubmit = async (e) => {
            e.preventDefault();
            const prompt = promptInput.value;
            if (!prompt) return;

            promptInput.value = '';
            sendBtn.disabled = true;
            promptInput.disabled = true;

            const userStep = { type: 'user', content: prompt };
            appendStep(userStep);

            try {
                const response = await fetch('/runs', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({ prompt })
                });

                const run = await response.json();
                currentRunId = run.id;
                startStreaming(run.id);
            } catch (error) {
                console.error("Error creating run:", error);
                sendBtn.disabled = false;
                promptInput.disabled = false;
                appendStep({type: 'error', content: 'Не удалось создать запрос.'});
            }
        };
    </script>
</body>
</html>
