import ChatUI from '../../components/ChatUI';

export const metadata = {
  title: 'Game Finder - Infinity VR Companion',
};

export default function GameFinderPage() {
  return (
    <main className="p-4">
      <h1 className="text-2xl font-bold mb-4">Game Finder</h1>
      <ChatUI mode="game" />
    </main>
  );
}
