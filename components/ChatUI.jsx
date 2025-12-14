'use client';

import { useEffect, useMemo, useState } from 'react';

const greetings = {
  comfort:
    "Hello! I'm your VR Comfort Coach. How can I help you feel more comfortable in VR today?",
  troubleshoot: "Hi, I'm your VR Troubleshooting assistant. What issue can I help you solve?",
  game:
    "Hello! I'm your VR Game Finder. Tell me what you like, and I'll recommend a VR game for you.",
  chat: "Hi there! I'm your VR companion. Ask me anything about VR!",
};

export default function ChatUI({ mode }) {
  const [messages, setMessages] = useState([]);
  const [conversation, setConversation] = useState([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (mode && greetings[mode]) {
      const greetingMessage = { role: 'assistant', type: 'text', content: greetings[mode] };
      setMessages([greetingMessage]);
      setConversation([greetingMessage]);
    } else {
      setMessages([]);
      setConversation([]);
    }
  }, [mode]);

  const detectMessageType = (text) => {
    const urlRegex = /https?:\/\/[^\s]+/i;
    const match = text.match(urlRegex);
    if (!match) return 'text';
    const url = match[0];
    if (/youtube\.com|youtu\.be|vimeo\.com/i.test(url)) return 'video';
    return 'link';
  };

  const sendMessage = async () => {
    if (!input.trim()) return;

    const userText = input.trim();
    const userMessage = { role: 'user', content: userText, type: detectMessageType(userText) };
    setMessages((prev) => [...prev, userMessage]);
    const nextConversation = [...conversation, userMessage];
    setConversation(nextConversation);
    setInput('');
    setLoading(true);

    try {
      const response = await fetch('/api/ask', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ messages: nextConversation, mode }),
      });

      const data = await response.json();

      if (data?.enrichedUser) {
        setMessages((prev) =>
          prev.map((msg, idx) =>
            idx === prev.length - 1 ? { ...msg, ...data.enrichedUser } : msg,
          ),
        );
        setConversation((prev) =>
          prev.map((msg, idx) =>
            idx === prev.length - 1 ? { ...msg, ...data.enrichedUser } : msg,
          ),
        );
      }

      if (data.assistant) {
        const assistantMessage =
          typeof data.assistant === 'string'
            ? { role: 'assistant', content: data.assistant, type: 'text' }
            : { role: 'assistant', type: 'text', ...data.assistant };
        setMessages((prev) => [...prev, assistantMessage]);
        setConversation((prev) => [...prev, assistantMessage]);
      } else if (data.error) {
        setMessages((prev) => [
          ...prev,
          { role: 'assistant', content: 'Oops, something went wrong. Please try again.' },
        ]);
      }
    } catch (err) {
      console.error('Error sending message:', err);
      setMessages((prev) => [
        ...prev,
          { role: 'assistant', content: 'Network error. Please check your connection.' },
      ]);
    } finally {
      setLoading(false);
    }
  };

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

  return (
    <div className="chat-shell glass-card overflow-hidden">
      <div className="absolute inset-0 pointer-events-none holo-grid" aria-hidden />
      <div className="relative space-y-4">
        <div className="holo-panel space-y-4 max-h-[420px] overflow-y-auto pr-2">
          {messages.map((msg, idx) => (
            <div
              key={idx}
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
              disabled={loading}
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
            disabled={loading}
          >
            <span className="text-lg">{loading ? '⏳' : '🚀'}</span>
            <span className="font-semibold tracking-[0.12em] uppercase">
              {loading ? 'Sending' : 'Send'}
            </span>
          </button>
        </div>
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
