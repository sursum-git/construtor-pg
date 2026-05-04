class StreamingService {
    constructor(serviceURL) {
        this.config = {
            apiBaseUrl: serviceURL
        };

        this.controller = null;
    }
    async streamAIResponse(chatId, message, files = [], callbacks = {}) {
        this.controller = new AbortController();

        const formData = new FormData();

        formData.append('message', message);
        if (files.length > 0) {
            files.forEach(file => {
                formData.append("images", file.rawFile);
            });
        }

        try {
            const response = await fetch(`${this.config.apiBaseUrl}/chat/${encodeURIComponent(chatId)}`, {
                method: 'POST',
                body: formData,
                signal: this.controller.signal
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            await this.processStreamingResponse(response, callbacks);
        } catch (error) {
            if (error.name === 'AbortError') {
                callbacks.onAbort?.();
            } else {
                callbacks.onError?.(error);
            }
        } finally {
            this.controller = null;
        }
    }

    async processStreamingResponse(response, callbacks) {
        let buffer = "";
        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        while (true) {
            const result = await reader.read();
            if (result.done) break;

            buffer += decoder.decode(result.value, { stream: !result.done });
            const lines = buffer.split("\n");
            buffer = lines.pop();

            for (const line of lines) {
                if (line.trim()) {
                    const data = JSON.parse(line);
                    this.handleStreamingData(data, callbacks);
                }
            }
        }
    }

    handleStreamingData(data, callbacks) {
        switch (data.status) {
            case "start": {
                callbacks.onStart?.(data);
                break;
            }
            case "streaming":
                callbacks.onStreaming?.(data);
                break;

            case "complete":
                callbacks.onComplete?.(data);
                break;
        }
    }

    abortRequest() {
        if (this.controller) {
            this.controller.abort();
            this.controller = null;
        }
    }

    // API methods
    async #apiRequest(endpoint, options = {}) {
        const response = await fetch(`${this.config.apiBaseUrl}${endpoint}`, {
            headers: { 'Content-Type': 'application/json' },
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response;
    }

    // Retrives the ids of all chat rooms that have been created so far.
    // Also retreives the current token usage.
    async loadChatSession() {
        try {
            const response = await this.#apiRequest('/chat/conversations');
            const data = await response.json();

            return data;
        } catch (err) {
            console.error("Failed to load chat list:", err);
        }
    }

    // Loads the messages for a particular chat room.
    async loadChatInfo(chatId) {
        if (!chatId) return;

        try {
            const response = await this.#apiRequest(`/chat/${encodeURIComponent(chatId)}`);
            const data = await response.json();

            return data;
        } catch (err) {
            console.error("Failed to load chat info:", err);
        }
    }

    // Creates a new chat room with no messages. The AI won't be aware of the previous conversation.
    async createNewChat() {
        try {
            const response = await this.#apiRequest('/chat/conversations', { method: 'POST' });
            const data = await response.json();

            return data;
        } catch (err) {
            console.error("Failed to create new chat:", err);
        }
    }

    // Removes a chat room by given id.
    async deleteChat(chatId) {
        if (!chatId) return;

        try {
            await this.#apiRequest(`/chat/conversations/${encodeURIComponent(chatId)}`, {
                method: 'DELETE'
            });
        } catch (err) {
            console.error("Failed to delete chat:", err);
        }
    }
}

window.StreamingService = StreamingService;