import ChatUI from '../../components/ChatUI';

export const metadata = {
  title: 'Troubleshooter - Infinity VR Companion',
};

export default function TroubleshooterPage() {
  return (
    <main className="space-y-5">
      <header className="glass-card">
        <h1 className="section-title text-2xl">Troubleshooter</h1>
        <p className="subheading">Diagnostics with neon clarity for every VR hiccup.</p>
      </header>
      <ChatUI mode="troubleshoot" />
    </main>
  );
}
