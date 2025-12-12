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
    <main className="p-4">
      <h1 className="text-2xl font-bold mb-4">Welcome to Infinity VR Companion</h1>
      <p className="mb-4">Before you start, here are some quick VR tips:</p>
      {tips.map((tip, index) => (
        <TipCard key={index} tip={tip} />
      ))}
      <button
        onClick={handleContinue}
        className="mt-4 px-4 py-2 bg-green-600 text-white font-semibold rounded"
      >
        Let&apos;s Get Started
      </button>
    </main>
  );
}
