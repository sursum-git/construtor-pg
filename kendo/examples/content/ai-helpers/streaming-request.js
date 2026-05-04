class StreamingRequest {
    constructor(params, callbacks = {}) {
        this.params = params;
        this.controller = new AbortController();

        this.onStartCallback = callbacks.onStart || (() => { });
        this.onStreamCallback = callbacks.onStream || (() => { });
        this.onCompleteCallback = callbacks.onComplete || (() => { });

        this.make();
    }

    async make() {
        const response = await fetch("https://demos.telerik.com/service/v2/ai/completion", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(this.params),
            signal: this.controller.signal
        });

        await this.processStreamingResponse(response);
    }

    async processStreamingResponse(response) {
        let buffer = "";
        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        this.currentText = "";

        this.onStart();

        try {
            while (true) {
                const result = await reader.read();
                if (result.done) break;

                buffer += decoder.decode(result.value, { stream: !result.done });
                const lines = buffer.split("\n");
                buffer = lines.pop();

                for (const line of lines) {
                    if (line.trim()) {
                        try {
                            const data = JSON.parse(line);
                            this.handleStreamingData(data);
                        } catch (e) {
                            console.error("Failed to parse streaming data:", e);
                        }
                    }
                }
            }
        } catch (error) {
            this.handleStreamingError(error);
        }

        this.controller = null;
    }

    handleStreamingData(data) {
        switch (data.status) {
            case "streaming":
                this.currentText += data.message;
                this.onStream(this.currentText);
                break;
            case "complete":
                this.onComplete(this.currentText);
                break;
        }
    }

    handleStreamingError(error) {
        this.controller = null;
        if (error.name === 'AbortError') {
            console.log('Request was aborted by user');
        } else {
            console.error('Error during streaming:', error);
        }
    }

    abortRequest() {
        if (this.controller) {
            this.controller.abort();
            this.controller = null;
        }
    }

    onStart() {
        this.onStartCallback();
    }

    onStream(fullText) {
        this.onStreamCallback(fullText);
    }

    onComplete(finalText) {
        this.onCompleteCallback(finalText);
    }
}

window.StreamingRequest = StreamingRequest;