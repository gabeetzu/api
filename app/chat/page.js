import ChatUI from '../../components/ChatUI';

export const metadata = {
  title: 'AI Chat - Infinity VR Companion',
};

export default function ChatPage({ searchParams }) {
  const presetPrompt = searchParams?.prompt ? String(searchParams.prompt) : '';

  return (
    <main className="space-y-5">
      <header className="glass-card">
        <h1 className="section-title text-2xl">Open Chat</h1>
        <p className="subheading">Freeform VR guidance with a luminous companion voice.</p>
      </header>
      <ChatUI mode="chat" presetPrompt={presetPrompt} />
    </main>
  );
}
