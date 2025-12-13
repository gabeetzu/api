'use client';

import { useEffect, useState } from 'react';

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
      const greetingMessage = { role: 'assistant', content: greetings[mode] };
      setMessages([greetingMessage]);
      setConversation([greetingMessage]);
    } else {
      setMessages([]);
      setConversation([]);
    }
  }, [mode]);

  const sendMessage = async () => {
    if (!input.trim()) return;

    const userText = input.trim();
    const userMessage = { role: 'user', content: userText };
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
      if (data.assistant) {
        const assistantMessage = { role: 'assistant', content: data.assistant };
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

  return (
    <div className="glass-card space-y-4">
      <div className="messages space-y-3 max-h-[420px] overflow-y-auto pr-1">
        {messages.map((msg, idx) => (
          <div
            key={idx}
            className={`chat-bubble ${msg.role === 'assistant' ? 'assistant' : 'user'}`}
          >
            <strong className="block text-sm uppercase tracking-wide text-white/80">
              {msg.role === 'assistant' ? 'Assistant' : 'You'}
            </strong>
            <p className="mt-1 leading-relaxed">{msg.content}</p>
          </div>
        ))}
      </div>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <input
          type="text"
          className="input-field"
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
        <button onClick={sendMessage} className="neon-button sm:w-auto w-full" disabled={loading}>
          {loading ? 'Sending...' : 'Send'}
        </button>
      </div>
    </div>
  );
}
