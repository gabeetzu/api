'use client';

import { useEffect, useMemo, useRef, useState } from 'react';

const greetings = {
  comfort:
    "Hello! I'm your VR Comfort Coach. How can I help you feel more comfortable in VR today?",
  troubleshoot: "Hi, I'm your VR Troubleshooting assistant. What issue can I help you solve?",
  game:
    "Hello! I'm your VR Game Finder. Tell me what you like, and I'll recommend a VR game for you.",
  chat: "Hi there! I'm your VR companion. Ask me anything about VR!",
};

export default function ChatUI({ mode, presetPrompt }) {
  const [messages, setMessages] = useState([]);
  const [conversation, setConversation] = useState([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [streamStatus, setStreamStatus] = useState('idle');
  const [activeAssistantId, setActiveAssistantId] = useState(null);
  const presetHandledRef = useRef('');

  const generateId = () =>
    (typeof crypto !== 'undefined' && crypto.randomUUID
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random()}`);

  useEffect(() => {
    if (mode && greetings[mode]) {
      const greetingMessage = { role: 'assistant', type: 'text', content: greetings[mode] };
      setMessages([greetingMessage]);
      setConversation([greetingMessage]);
      setInput(presetPrompt || '');
      presetHandledRef.current = '';
    } else {
      setMessages([]);
      setConversation([]);
      setInput('');
      presetHandledRef.current = '';
    }
  }, [mode, presetPrompt]);

  const detectMessageType = (text) => {
    const urlRegex = /https?:\/\/[^\s]+/i;
    const match = text.match(urlRegex);
    if (!match) return 'text';
    const url = match[0];
    if (/youtube\.com|youtu\.be|vimeo\.com/i.test(url)) return 'video';
    return 'link';
  };

  const updateLastUserMessage = (updates) => {
    setMessages((prev) => {
      const copy = [...prev];
      for (let i = copy.length - 1; i >= 0; i -= 1) {
        if (copy[i].role === 'user') {
          copy[i] = { ...copy[i], ...updates };
          break;
        }
      }
      return copy;
    });

    setConversation((prev) => {
      const copy = [...prev];
      for (let i = copy.length - 1; i >= 0; i -= 1) {
        if (copy[i].role === 'user') {
          copy[i] = { ...copy[i], ...updates };
          break;
        }
      }
      return copy;
    });
  };

  const updateAssistantMessage = (id, updates) => {
    setMessages((prev) => prev.map((msg) => (msg.id === id ? { ...msg, ...updates } : msg)));
  };

  const processStream = async (conversationSnapshot, assistantId, attempt = 0) => {
    try {
      setStreamStatus(attempt > 0 ? 'reconnecting' : 'streaming');

      const response = await fetch('/api/ask', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ messages: conversationSnapshot, mode }),
      });

      if (!response.ok) {
        const errorBody = await response.json().catch(() => ({}));
        throw new Error(errorBody?.error || 'Request failed');
      }

      const reader = response.body?.getReader();
      if (!reader) {
        throw new Error('No stream available');
      }

      if (attempt > 0) {
        updateAssistantMessage(assistantId, { content: '' });
      }

      const decoder = new TextDecoder();
      let buffer = '';

      const handleEvent = (rawEvent) => {
        if (!rawEvent?.trim()) return;
        const normalized = rawEvent.startsWith('data:')
          ? rawEvent.slice(5).trim()
          : rawEvent.trim();

        if (!normalized) return;

        let parsed;
        try {
          parsed = JSON.parse(normalized);
        } catch {
          return;
        }

        if (parsed.type === 'enrichedUser' && parsed.data) {
          updateLastUserMessage(parsed.data);
          return;
        }

        if (parsed.type === 'delta' && typeof parsed.data === 'string') {
          setMessages((prev) =>
            prev.map((msg) =>
              msg.id === assistantId
                ? { ...msg, content: `${msg.content || ''}${parsed.data}` }
                : msg,
            ),
          );
          return;
        }

        if (parsed.type === 'done' && parsed.data?.assistant) {
          const { assistant, enrichedUser } = parsed.data;
          updateAssistantMessage(assistantId, {
            ...assistant,
            streaming: false,
          });
          setConversation((prev) => [...prev, { id: assistantId, ...assistant }]);
          if (enrichedUser) {
            updateLastUserMessage(enrichedUser);
          }
          setStreamStatus('idle');
          setLoading(false);
          setActiveAssistantId(null);
          return;
        }

        if (parsed.type === 'error') {
          throw new Error(parsed.data?.message || 'Stream error');
        }
      };

      while (true) {
        const { value, done } = await reader.read();
        buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
        const events = buffer.split('\n\n');
        buffer = events.pop() || '';
        events.forEach(handleEvent);
        if (done) {
          handleEvent(buffer);
          break;
        }
      }

      setStreamStatus('idle');
      setLoading(false);
      setActiveAssistantId(null);
    } catch (err) {
      console.error('Stream error:', err);
      if (attempt < 1) {
        return processStream(conversationSnapshot, assistantId, attempt + 1);
      }
      setStreamStatus('error');
      setLoading(false);
      updateAssistantMessage(assistantId, {
        content: 'Connection lost. Please try again.',
        error: true,
      });
      setActiveAssistantId(null);
    }
  };

  const sendMessage = async (payloadText, baseConversation = conversation) => {
    const messageText = typeof payloadText === 'string' ? payloadText : input;
    if (!messageText?.trim()) return;

    const userText = messageText.trim();
    const userMessage = { role: 'user', content: userText, type: detectMessageType(userText) };
    setMessages((prev) => [...prev, userMessage]);
    const nextConversation = [...baseConversation, userMessage];
    setConversation(nextConversation);
    if (typeof payloadText !== 'string') {
      setInput('');
    }
    setLoading(true);

    const assistantId = generateId();
    const assistantPlaceholder = { id: assistantId, role: 'assistant', content: '', type: 'text', streaming: true };
    setMessages((prev) => [...prev, assistantPlaceholder]);
    setActiveAssistantId(assistantId);

    await processStream(nextConversation, assistantId);
  };

  useEffect(() => {
    if (!presetPrompt || presetHandledRef.current === presetPrompt) return;
    if (!conversation.length && mode) return;
    presetHandledRef.current = presetPrompt;
    sendMessage(presetPrompt, conversation);
  }, [conversation, mode, presetPrompt]);

  const messageRenderer = useMemo(
    () => ({
      text: (msg) => <p className="mt-2 leading-relaxed text-white/90">{msg.content}</p>,
      link: (msg) => (
        <div className="space-y-2">
          <p className="mt-2 leading-relaxed text-white/90">{msg.content}</p>
          <MessageCard message={msg} label="Link Preview">
            <LinkPreview metadata={msg.metadata} fallbackUrl={msg.content} />
          </MessageCard>
        </div>
      ),
      video: (msg) => (
        <div className="space-y-2">
          <p className="mt-2 leading-relaxed text-white/90">{msg.content}</p>
          <MessageCard message={msg} label="Video">
            <VideoPreview metadata={msg.metadata} fallbackUrl={msg.content} />
          </MessageCard>
        </div>
      ),
    }),
    [],
  );

  const renderMessageContent = (msg) => {
    const renderer = messageRenderer[msg.type] || messageRenderer.text;
    return renderer(msg);
  };

  const isBusy = streamStatus === 'streaming' || streamStatus === 'reconnecting' || loading;
  const statusMessage =
    {
      streaming: 'Receiving response…',
      reconnecting: 'Connection dropped. Reconnecting…',
      error: 'Stream interrupted. Please try again.',
    }[streamStatus] || '';
  const buttonLabel =
    streamStatus === 'reconnecting'
      ? 'Reconnecting'
      : streamStatus === 'streaming'
        ? 'Streaming'
        : loading
          ? 'Sending'
          : 'Send';
  const buttonIcon =
    streamStatus === 'reconnecting'
      ? '🔄'
      : streamStatus === 'streaming'
        ? '🌐'
        : loading
          ? '⏳'
          : '🚀';

  return (
    <div className="chat-shell glass-card overflow-hidden">
      <div className="absolute inset-0 pointer-events-none holo-grid" aria-hidden />
      <div className="relative space-y-4">
        <div className="holo-panel space-y-4 max-h-[420px] overflow-y-auto pr-2">
          {messages.map((msg, idx) => (
            <div
              key={msg.id || idx}
              className={`message-row ${msg.role === 'assistant' ? 'assistant' : 'user'}`}
            >
              <div className={`message-avatar ${msg.role === 'assistant' ? 'assistant' : 'user'}`}>
                {msg.role === 'assistant' ? '🤖' : '🧑‍🚀'}
              </div>
              <div
                className={`message-bubble ${msg.role === 'assistant' ? 'assistant' : 'user'}`}
              >
                <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-white/70">
                  <span className="inline-block h-1.5 w-1.5 rounded-full bg-gradient-to-r from-cyan-400 to-fuchsia-500" />
                  {msg.role === 'assistant' ? 'Assistant' : 'You'}
                </div>
                {renderMessageContent(msg)}
                {msg.id === activeAssistantId && streamStatus === 'streaming' && (
                  <p className="mt-1 text-xs text-white/70">Receiving response…</p>
                )}
                {msg.id === activeAssistantId && streamStatus === 'reconnecting' && (
                  <p className="mt-1 text-xs text-amber-200">Connection lost. Reconnecting…</p>
                )}
                {msg.id === activeAssistantId && streamStatus === 'error' && (
                  <p className="mt-1 text-xs text-rose-200">Stream interrupted. Please resend.</p>
                )}
              </div>
            </div>
          ))}
        </div>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          <div className="flex-1 rounded-full border border-white/10 bg-[radial-gradient(circle_at_20%_50%,rgba(77,232,244,0.18),transparent_32%),radial-gradient(circle_at_80%_50%,rgba(255,63,164,0.16),transparent_32%),rgba(255,255,255,0.04)] shadow-[0_0_0_1px_rgba(255,255,255,0.08),0_10px_40px_rgba(0,0,0,0.35)]">
            <input
              type="text"
              className="chat-input"
              placeholder="Type your message..."
              value={input}
              onChange={(e) => setInput(e.target.value)}
              disabled={isBusy}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  sendMessage();
                }
              }}
            />
          </div>
          <button
            onClick={sendMessage}
            className="send-button sm:w-auto w-full"
            disabled={isBusy}
          >
            <span className="text-lg">{buttonIcon}</span>
            <span className="font-semibold tracking-[0.12em] uppercase">
              {buttonLabel}
            </span>
          </button>
        </div>
        {statusMessage && (
          <p className="text-xs text-white/70">{statusMessage}</p>
        )}
      </div>
    </div>
  );
}

function MessageCard({ message, children, label }) {
  return (
    <div className="message-card" aria-label={`${label} from ${message.role === 'assistant' ? 'assistant' : 'you'}`}>
      {children}
    </div>
  );
}

function LinkPreview({ metadata, fallbackUrl }) {
  const href = metadata?.url || fallbackUrl;
  return (
    <a
      href={href}
      target="_blank"
      rel="noreferrer"
      className="preview-card"
    >
      {metadata?.image && (
        <div className="preview-media" aria-hidden>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={metadata.image} alt="" className="preview-image" />
        </div>
      )}
      <div className="preview-body">
        <p className="preview-kicker">{metadata?.siteName || 'External link'}</p>
        <p className="preview-title">{metadata?.title || href}</p>
        <p className="preview-description">{metadata?.description || 'Tap to open this link'}</p>
      </div>
    </a>
  );
}

function VideoPreview({ metadata, fallbackUrl }) {
  const href = metadata?.url || fallbackUrl;
  return (
    <div className="preview-card video">
      <a href={href} target="_blank" rel="noreferrer" className="preview-media" aria-label="Open video">
        {metadata?.image ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={metadata.image} alt="Video thumbnail" className="preview-image" />
        ) : (
          <div className="preview-placeholder">🎬 Video link</div>
        )}
      </a>
      <div className="preview-body">
        <p className="preview-kicker">{metadata?.siteName || 'Video'}</p>
        <p className="preview-title">{metadata?.title || 'Watch now'}</p>
        <p className="preview-description">{metadata?.description || href}</p>
      </div>
    </div>
  );
}
