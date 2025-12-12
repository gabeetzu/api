import ChatUI from '../../components/ChatUI';

export const metadata = {
  title: 'Comfort Coach - Infinity VR Companion',
};

export default function ComfortCoachPage() {
  return (
    <main className="p-4">
      <h1 className="text-2xl font-bold mb-4">Comfort Coach</h1>
      <ChatUI mode="comfort" />
    </main>
  );
}
