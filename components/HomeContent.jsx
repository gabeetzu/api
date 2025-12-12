'use client';

import { useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import tips from '../data/tips';
import TipCard from './TipCard';

export default function HomeContent() {
  const router = useRouter();

  useEffect(() => {
    if (typeof window !== 'undefined' && !localStorage.getItem('onboarded')) {
      router.replace('/onboarding');
    }
  }, [router]);

  const todayIndex = new Date().getDate() % tips.length;
  const todayTip = tips[todayIndex];

  return (
    <main className="p-4">
      <h1 className="text-2xl font-bold mb-4">Infinity VR Companion</h1>
      <TipCard tip={todayTip} />

      <h2 className="text-xl font-semibold mt-6 mb-2">Assistant Options:</h2>
      <ul className="space-y-2">
        <li>
          <Link href="/comfort" className="text-blue-600 underline">
            Comfort Coach
          </Link>
        </li>
        <li>
          <Link href="/troubleshooter" className="text-blue-600 underline">
            Troubleshooter
          </Link>
        </li>
        <li>
          <Link href="/game-finder" className="text-blue-600 underline">
            Game Finder
          </Link>
        </li>
        <li>
          <Link href="/chat" className="text-blue-600 underline">
            Open Chat
          </Link>
        </li>
      </ul>
    </main>
  );
}
