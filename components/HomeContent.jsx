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
    <main className="space-y-6">
      <header className="glass-card">
        <h1 className="section-title mb-2">Infinity VR Companion</h1>
        <p className="subheading">
          Navigate VR with a neon-lit copilot—personal tips, comfort, troubleshooting, and game
          scouting in one sleek hub.
        </p>
      </header>

      <TipCard tip={todayTip} />

      <section className="glass-card space-y-4">
        <div>
          <h2 className="section-title text-xl">Assistant Options</h2>
          <p className="subheading">Pick a mode and we&apos;ll glow the path ahead.</p>
        </div>
        <ul className="space-y-3">
          <li className="list-tile">
            <span className="font-semibold">Comfort Coach</span>
            <Link href="/comfort" className="neon-link">
              Open
            </Link>
          </li>
          <li className="list-tile">
            <span className="font-semibold">Troubleshooter</span>
            <Link href="/troubleshooter" className="neon-link">
              Open
            </Link>
          </li>
          <li className="list-tile">
            <span className="font-semibold">Game Finder</span>
            <Link href="/game-finder" className="neon-link">
              Open
            </Link>
          </li>
          <li className="list-tile">
            <span className="font-semibold">Open Chat</span>
            <Link href="/chat" className="neon-link">
              Open
            </Link>
          </li>
        </ul>
      </section>
    </main>
  );
}
