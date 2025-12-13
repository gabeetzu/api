import ChatUI from '../../components/ChatUI';

export const metadata = {
  title: 'Comfort Coach - Infinity VR Companion',
};

export default function ComfortCoachPage() {
  return (
    <main className="space-y-5">
      <header className="glass-card">
        <h1 className="section-title text-2xl">Comfort Coach</h1>
        <p className="subheading">Tuning your VR setup for calm, steady sessions.</p>
      </header>
      <ChatUI mode="comfort" />
    </main>
  );
}
