import ChatUI from '../../components/ChatUI';

export const metadata = {
  title: 'AI Chat - Infinity VR Companion',
};

export default function ChatPage() {
  return (
    <main className="p-4">
      <h1 className="text-2xl font-bold mb-4">Open Chat</h1>
      <ChatUI mode="chat" />
    </main>
  );
}
