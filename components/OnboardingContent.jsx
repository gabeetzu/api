'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import TipCard from './TipCard';
import tips from '../data/tips';

export default function OnboardingContent() {
  const router = useRouter();

  useEffect(() => {
    if (typeof window !== 'undefined' && localStorage.getItem('onboarded')) {
      router.replace('/');
    }
  }, [router]);

  const handleContinue = () => {
    if (typeof window !== 'undefined') {
      localStorage.setItem('onboarded', 'true');
    }
    router.replace('/');
  };

  return (
    <main className="space-y-5">
      <header className="glass-card space-y-2">
        <h1 className="section-title">Welcome to Infinity VR Companion</h1>
        <p className="subheading">Quick neon-lit tips to launch your best VR session.</p>
      </header>
      {tips.map((tip, index) => (
        <TipCard key={index} tip={tip} />
      ))}
      <button onClick={handleContinue} className="neon-button self-start">
        Let&apos;s Get Started
      </button>
    </main>
  );
}
