document.querySelectorAll('[data-ai-assistant]').forEach(function (assistant) {
    const widget = assistant.closest('[data-ai-widget]');
    const toggleButton = widget && widget.querySelector('[data-ai-toggle]');
    const closeButton = assistant.querySelector('[data-ai-close]');
    const teaser = widget && widget.querySelector('[data-ai-teaser]');
    const dismissTeaser = widget && widget.querySelector('[data-ai-dismiss]');
    const openFromTeaser = widget && widget.querySelector('[data-ai-open]');
    const isSubscribed = assistant.dataset.subscribed === 'true';

    function setAssistantOpen(isOpen) {
        assistant.hidden = !isOpen;
        if (toggleButton) {
            toggleButton.setAttribute('aria-expanded', String(isOpen));
            toggleButton.querySelector('.dashboard-ai-launcher-icon i').className = isOpen
                ? 'bi bi-x-lg'
                : 'bi bi-stars';
        }
        if (teaser && isOpen) {
            teaser.hidden = true;
        }
    }

    function openAssistant() {
        setAssistantOpen(true);
        if (isSubscribed) {
            assistant.querySelector('[data-ai-input]').focus();
        }
    }

    if (toggleButton) {
        toggleButton.addEventListener('click', function () {
            if (assistant.hidden) {
                openAssistant();
            } else {
                setAssistantOpen(false);
            }
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', function () {
            setAssistantOpen(false);
            if (toggleButton) {
                toggleButton.focus();
            }
        });
    }

    if (openFromTeaser) {
        openFromTeaser.addEventListener('click', function () {
            openAssistant();
        });
    }

    if (dismissTeaser) {
        dismissTeaser.addEventListener('click', function () {
            teaser.hidden = true;
        });
    }

    if (!isSubscribed) {
        return;
    }

    const form = assistant.querySelector('[data-ai-form]');
    const input = assistant.querySelector('[data-ai-input]');
    const messagesContainer = assistant.querySelector('[data-ai-messages]');
    const sendButton = assistant.querySelector('[data-ai-send]');
    const status = assistant.querySelector('[data-ai-status]');
    const endpoint = assistant.dataset.endpoint;
    const conversation = [];
    let isSending = false;

    function appendMessage(role, text, isError) {
        const message = document.createElement('div');
        message.className = 'dashboard-ai-message ' + (role === 'assistant' ? 'assistant-message' : 'user-message');
        if (isError) {
            message.classList.add('error-message');
        }

        if (role === 'assistant') {
            const avatar = document.createElement('span');
            avatar.className = 'dashboard-ai-avatar';
            avatar.setAttribute('aria-hidden', 'true');
            const icon = document.createElement('i');
            icon.className = 'bi bi-stars';
            avatar.appendChild(icon);
            message.appendChild(avatar);
        }

        const textElement = document.createElement('p');
        textElement.textContent = text;
        message.appendChild(textElement);
        messagesContainer.appendChild(message);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        return message;
    }

    async function sendMessage(messageText) {
        const text = messageText.trim();
        if (!text || isSending) {
            return;
        }

        isSending = true;
        input.value = '';
        input.disabled = true;
        sendButton.disabled = true;
        assistant.querySelectorAll('[data-ai-prompt]').forEach(function (button) {
            button.disabled = true;
        });
        appendMessage('user', text);
        conversation.push({ role: 'user', content: text });
        const loadingMessage = appendMessage('assistant', 'Thinking with your local AI…');
        loadingMessage.classList.add('loading-message');
        status.textContent = 'The local assistant is preparing a response.';

        try {
            const response = await fetch(new URL(endpoint, window.location.href), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ messages: conversation.slice(-10) })
            });
            const responseText = await response.text();
            let payload;
            try {
                payload = JSON.parse(responseText);
            } catch (parseError) {
                const contentType = response.headers.get('content-type') || '';
                if (!contentType.toLowerCase().includes('application/json')) {
                    throw new Error('The assistant endpoint returned a web page instead of chat data. Reload the dashboard and try again.');
                }
                throw new Error('The assistant returned an unreadable response. Please try again.');
            }
            loadingMessage.remove();

            if (!response.ok) {
                throw new Error(payload.error || 'The assistant could not answer. Please try again.');
            }
            if (typeof payload.reply !== 'string' || payload.reply.trim() === '') {
                throw new Error('The assistant returned an empty response. Please try again.');
            }

            conversation.push({ role: 'assistant', content: payload.reply });
            appendMessage('assistant', payload.reply);
            status.textContent = 'This conversation is kept on this page and sent to the local AI on this computer.';
        } catch (error) {
            loadingMessage.remove();
            appendMessage('assistant', error.message || 'The assistant is unavailable. Please try again.', true);
            conversation.pop();
            status.textContent = 'The assistant could not complete that request.';
        } finally {
            isSending = false;
            input.disabled = false;
            sendButton.disabled = false;
            assistant.querySelectorAll('[data-ai-prompt]').forEach(function (button) {
                button.disabled = false;
            });
            input.focus();
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage(input.value);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    assistant.querySelectorAll('[data-ai-prompt]').forEach(function (button) {
        button.addEventListener('click', function () {
            sendMessage(button.dataset.aiPrompt || '');
        });
    });

    assistant.querySelector('[data-ai-reset]').addEventListener('click', function () {
        if (isSending) {
            return;
        }

        conversation.length = 0;
        messagesContainer.replaceChildren();
        appendMessage('assistant', assistant.dataset.aiWelcome);
        status.textContent = 'Your previous conversation is cleared from this page.';
        input.focus();
    });
});
