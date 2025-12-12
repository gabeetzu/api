import ChatUI from '../../components/ChatUI';

export const metadata = {
  title: 'Troubleshooter - Infinity VR Companion',
};

export default function TroubleshooterPage() {
  return (
    <main className="p-4">
      <h1 className="text-2xl font-bold mb-4">Troubleshooter</h1>
      <ChatUI mode="troubleshoot" />
    </main>
  );
}
