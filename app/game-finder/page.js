import ChatUI from '../../components/ChatUI';

export const metadata = {
  title: 'Game Finder - Infinity VR Companion',
};

export default function GameFinderPage() {
  return (
    <main className="space-y-5">
      <header className="glass-card">
        <h1 className="section-title text-2xl">Game Finder</h1>
        <p className="subheading">Discover neon-worthy VR worlds tailored to your vibe.</p>
      </header>
      <ChatUI mode="game" />
    </main>
  );
}
