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

  const modes = [
    {
      title: 'Comfort Coach',
      description: 'Optimize ergonomics, reduce motion sickness, and find your calm zone.',
      href: '/comfort',
      icon: '🧘‍♀️',
      badge: 'Balanced',
    },
    {
      title: 'Troubleshooter',
      description: 'Diagnose glitches, guide quick fixes, and keep your headset humming.',
      href: '/troubleshooter',
      icon: '🛠️',
      badge: 'Stability',
    },
    {
      title: 'Game Finder',
      description: 'Discover adventures tuned to your mood, genre, and gear.',
      href: '/game-finder',
      icon: '🎮',
      badge: 'Curated',
    },
    {
      title: 'Open Chat',
      description: 'Ask anything VR—from setup hacks to lore and beyond.',
      href: '/chat',
      icon: '💬',
      badge: 'Freeform',
    },
  ];

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
        <div className="flex items-center justify-between gap-3">
          <div>
            <h2 className="section-title text-xl">Assistant Options</h2>
            <p className="subheading">Pick a mode and we&apos;ll glow the path ahead.</p>
          </div>
          <div className="mode-chip">
            <span className="h-2 w-2 rounded-full bg-emerald-300" />
            Live neural link
          </div>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          {modes.map((mode) => (
            <Link key={mode.title} href={mode.href} className="mode-card group">
              <div className="relative z-[1] flex items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                  <div className="mode-icon">{mode.icon}</div>
                  <div className="space-y-1">
                    <h3 className="text-lg font-semibold tracking-wide group-hover:text-white">
                      {mode.title}
                    </h3>
                    <p className="card-text text-sm leading-relaxed">{mode.description}</p>
                  </div>
                </div>
                <div className="mode-chip">
                  <span className="h-2 w-2 rounded-full bg-cyan-300" />
                  {mode.badge}
                </div>
              </div>
              <div className="relative z-[1] mt-3 flex items-center justify-between text-sm text-white/70">
                <span className="flex items-center gap-2">
                  <span className="inline-block h-1.5 w-1.5 rounded-full bg-gradient-to-r from-cyan-400 to-fuchsia-500" />
                  Enter link
                </span>
                <span className="transition-transform duration-150 group-hover:translate-x-1">→</span>
              </div>
            </Link>
          ))}
        </div>
      </section>
    </main>
  );
}
