document.addEventListener('DOMContentLoaded', () => {
    const toggleButton = document.getElementById('chat-toggle-button');
    if (!toggleButton) return;

    const closeButton = document.getElementById('chat-close-button');
    const chatWindow = document.getElementById('chat-window');
    const chatForm = document.getElementById('chat-input-form');
    const chatInput = document.getElementById('chat-input');
    const messagesContainer = document.getElementById('chat-messages');
    let typingIndicator;
    let welcomeShown = false; // Prevent multiple welcome messages

    const toggleChat = (show) => {
        if (show) {
            toggleButton.style.display = 'none';
            chatWindow.style.display = 'flex';

            if (!welcomeShown) {
                welcomeShown = true;
                showTypingIndicator();

                setTimeout(async () => {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'welcome');

                        const response = await fetch('chatbot_api.php', {
                            method: 'POST',
                            body: formData
                        });

                        if (response.ok) {
                            const data = await response.json();
                            hideTypingIndicator();
                            addMessage(data.reply, 'bot', data.choices);
                        } else {
                            hideTypingIndicator();
                            addMessage("Hello! I'm the FAQ bot. How can I help you with your application today?", 'bot');
                        }
                    } catch (error) {
                        hideTypingIndicator();
                        addMessage("Hello! I'm the FAQ bot. How can I help you with your application today?", 'bot');
                    }
                }, 1000);
            }
            chatInput.focus();
        } else {
            toggleButton.style.display = 'flex';
            chatWindow.style.display = 'none';
        }
    };

    const showTypingIndicator = () => {
        typingIndicator = document.createElement('div');
        typingIndicator.className = 'typing-indicator bot-message';

        const typingDots = document.createElement('div');
        typingDots.className = 'typing-dots';

        for (let i = 0; i < 3; i++) {
            const dot = document.createElement('div');
            dot.className = 'typing-dot';
            typingDots.appendChild(dot);
        }

        typingIndicator.appendChild(typingDots);
        messagesContainer.appendChild(typingIndicator);
        scrollToBottom();
    };

    const hideTypingIndicator = () => {
        if (typingIndicator) {
            typingIndicator.remove();
            typingIndicator = null;
        }
    };

    const addMessage = (text, sender, choices = null) => {
        const messageContainer = document.createElement('div');
        messageContainer.className = `chat-message-container ${sender}-message`;

        const avatar = document.createElement('div');
        avatar.className = 'avatar';
        if (sender === 'bot') {
            avatar.innerHTML = '<i class="fas fa-robot"></i>';
        } else {
            avatar.innerHTML = '<i class="fas fa-user"></i>';
        }

        const messageBubble = document.createElement('div');
        messageBubble.className = 'chat-message-bubble';
        messageBubble.innerHTML = text.replace(/\n/g, '<br>');

        if (sender === 'user') {
            messageContainer.appendChild(messageBubble);
            messageContainer.appendChild(avatar);
        } else {
            messageContainer.appendChild(avatar);
            messageContainer.appendChild(messageBubble);
        }

        if (choices && choices.length > 0) {
            const choicesContainer = document.createElement('div');
            choicesContainer.className = 'choices-container';

            choices.forEach(choice => {
                const choiceButton = document.createElement('button');
                choiceButton.className = 'choice-button';
                choiceButton.textContent = choice.text;
                choiceButton.setAttribute('data-action', choice.action);
                choiceButton.addEventListener('click', () => handleChoiceClick(choice.action, choice.text));
                choicesContainer.appendChild(choiceButton);
            });

            messageContainer.appendChild(choicesContainer);
        }

        messagesContainer.appendChild(messageContainer);
        scrollToBottom();
    };

    async function handleChoiceClick(action, choiceText) {
        addMessage(choiceText, 'user');

        const choiceButtons = document.querySelectorAll('.choice-button');
        choiceButtons.forEach(btn => {
            btn.disabled = true;
            btn.classList.add('disabled');
        });

        showTypingIndicator();

        try {
            const formData = new FormData();
            formData.append('action', action);

            const response = await fetch('chatbot_api.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => null);
                throw new Error(errorData?.error || 'Network response was not ok');
            }

            const data = await response.json();
            hideTypingIndicator();
            addMessage(data.reply, 'bot', data.choices);

        } catch (error) {
            console.error('Error sending choice:', error);
            hideTypingIndicator();
            addMessage('Sorry, something went wrong. Please try again later.', 'bot');
        }
    }

    const scrollToBottom = () => {
        messagesContainer.scrollTo({
            top: messagesContainer.scrollHeight,
            behavior: 'smooth'
        });
    };

    toggleButton.addEventListener('click', () => toggleChat(true));
    closeButton.addEventListener('click', () => toggleChat(false));

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const userInput = chatInput.value.trim();
        if (userInput === '') return;

        addMessage(userInput, 'user');
        chatInput.value = '';
        chatInput.disabled = true;
        chatForm.querySelector('button').disabled = true;

        showTypingIndicator(); // ✅ now also for manual input

        try {
            const formData = new FormData();
            formData.append('message', userInput);

            const response = await fetch('chatbot_api.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => null);
                throw new Error(errorData?.error || 'Network response was not ok');
            }

            const data = await response.json();
            hideTypingIndicator();
            addMessage(data.reply, 'bot', data.choices);

        } catch (error) {
            console.error('Error sending message:', error);
            hideTypingIndicator();
            addMessage('Sorry, something went wrong. Please try again later.', 'bot');
        } finally {
            chatInput.disabled = false;
            chatForm.querySelector('button').disabled = false;
            chatInput.focus();
        }
    });

    chatInput.addEventListener('input', () => {
        chatForm.querySelector('button').disabled = chatInput.value.trim() === '';
    });

    chatForm.querySelector('button').disabled = true;
});
