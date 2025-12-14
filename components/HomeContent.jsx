'use client';

import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import tips from '../data/tips';
import homeContent from '../data/homeContent';
import TipCard from './TipCard';

export default function HomeContent() {
  const router = useRouter();
  const [content, setContent] = useState({ reels: [], guides: [], games: [] });
  const [loadingContent, setLoadingContent] = useState(true);

  useEffect(() => {
    if (typeof window !== 'undefined' && !localStorage.getItem('onboarded')) {
      router.replace('/onboarding');
    }
  }, [router]);

  useEffect(() => {
    let mounted = true;
    setLoadingContent(true);

    const timer = setTimeout(() => {
      if (!mounted) return;
      const reels = homeContent?.reels ?? [];
      const guides = homeContent?.guides ?? [];
      const games = homeContent?.games ?? [];
      setContent({ reels, guides, games });
      setLoadingContent(false);
    }, 200);

    return () => {
      mounted = false;
      clearTimeout(timer);
    };
  }, []);

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

  const sectionConfigs = useMemo(
    () => [
      {
        id: 'reels',
        title: 'Trending Reels',
        description: 'Quick-hit highlights and walkthroughs making waves right now.',
        emptyCopy: 'When reels are offline, you can still ping the assistant about your latest clips.',
        items: content.reels,
        badgeLabel: 'Reel',
        ctaLabel: 'Ask about this reel',
      },
      {
        id: 'guides',
        title: 'Featured Guides',
        description: 'Curated how-tos that pair perfectly with on-demand coaching.',
        emptyCopy: 'Guides will reload soon—ask the assistant to tailor a setup checklist meanwhile.',
        items: content.guides,
        badgeLabel: 'Guide',
        ctaLabel: 'Tailor this guide',
      },
      {
        id: 'games',
        title: 'Hot Game Picks',
        description: 'Fresh titles the companion can brief you on before you dive in.',
        emptyCopy: 'Game picks are warming up—ask the assistant for a recommendation while you wait.',
        items: content.games,
        badgeLabel: 'Game',
        ctaLabel: 'Plan my session',
      },
    ],
    [content],
  );

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

      {sectionConfigs.map((section) => (
        <ContentSection
          key={section.id}
          {...section}
          loading={loadingContent}
          chatLabel={section.ctaLabel}
        />
      ))}
    </main>
  );
}

function ContentSection({ title, description, items, badgeLabel, chatLabel, loading, emptyCopy }) {
  return (
    <section className="glass-card space-y-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div className="space-y-1">
          <h2 className="section-title text-xl">{title}</h2>
          <p className="subheading text-sm sm:text-base">{description}</p>
        </div>
        <div className="mode-chip">
          <span className="h-2 w-2 rounded-full bg-fuchsia-300" />
          Powered feed preview
        </div>
      </div>

      {loading ? (
        <LoadingGrid />
      ) : items.length ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {items.map((item) => (
            <ContentCard key={item.id} item={item} badgeLabel={badgeLabel} chatLabel={chatLabel} />
          ))}
        </div>
      ) : (
        <EmptyState message={emptyCopy} />
      )}
    </section>
  );
}

function ContentCard({ item, badgeLabel, chatLabel }) {
  const chatHref = `/chat?prompt=${encodeURIComponent(item.prompt)}`;

  return (
    <article className="relative overflow-hidden rounded-xl border border-white/10 bg-white/5 p-3 shadow-[0_14px_35px_rgba(0,0,0,0.35)]">
      <div className="absolute inset-0 bg-gradient-to-br from-white/10 via-transparent to-white/5" aria-hidden />
      <div className="relative space-y-3">
        <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-white/70">
          <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-2 py-1 text-[11px] font-semibold">
            <span className="inline-block h-2 w-2 rounded-full bg-cyan-300" />
            {badgeLabel}
          </span>
          {item.duration && <span className="text-white/60">{item.duration}</span>}
        </div>

        <div className="card-image">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={item.thumbnail}
            alt={item.title}
            className="h-44 w-full object-cover"
            loading="lazy"
          />
        </div>

        <div className="space-y-2">
          <h3 className="text-lg font-semibold tracking-wide">{item.title}</h3>
          <p className="card-text text-sm leading-relaxed">{item.description}</p>
          <div className="flex flex-wrap gap-2">
            {item.tags?.map((tag) => (
              <span
                key={tag}
                className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-white/80"
              >
                {tag}
              </span>
            ))}
          </div>
        </div>

        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <Link
            href={item.link}
            className="inline-flex items-center gap-2 text-sm font-semibold text-cyan-200 hover:text-white"
            target="_blank"
            rel="noreferrer"
          >
            View source
            <span aria-hidden>↗</span>
          </Link>
          <Link
            href={chatHref}
            className="inline-flex items-center justify-center gap-2 rounded-full border border-cyan-300/50 bg-gradient-to-r from-cyan-400/20 to-fuchsia-500/20 px-3 py-2 text-sm font-semibold uppercase tracking-[0.14em] text-white shadow-[0_0_18px_rgba(77,232,244,0.28)] transition hover:border-fuchsia-300/60"
          >
            {chatLabel}
            <span aria-hidden>💬</span>
          </Link>
        </div>
      </div>
    </article>
  );
}

function LoadingGrid() {
  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {Array.from({ length: 3 }).map((_, idx) => (
        <div
          key={idx}
          className="animate-pulse space-y-3 rounded-xl border border-white/10 bg-white/5 p-3 shadow-[0_14px_35px_rgba(0,0,0,0.25)]"
        >
          <div className="flex items-center gap-2">
            <span className="h-5 w-20 rounded-full bg-white/10" />
            <span className="h-4 w-10 rounded-full bg-white/10" />
          </div>
          <div className="h-44 w-full rounded-lg bg-white/10" />
          <div className="h-5 w-3/4 rounded-full bg-white/10" />
          <div className="h-4 w-full rounded-full bg-white/10" />
          <div className="flex gap-2">
            <span className="h-6 w-16 rounded-full bg-white/10" />
            <span className="h-6 w-14 rounded-full bg-white/10" />
          </div>
          <div className="flex gap-2">
            <span className="h-10 w-24 rounded-full bg-white/10" />
            <span className="h-10 w-28 rounded-full bg-white/10" />
          </div>
        </div>
      ))}
    </div>
  );
}

function EmptyState({ message }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-xl border border-dashed border-white/20 bg-white/5 p-4">
      <div className="space-y-1">
        <p className="text-sm font-semibold uppercase tracking-[0.14em] text-white/80">Feed offline</p>
        <p className="card-text text-sm leading-relaxed">{message}</p>
      </div>
      <span className="text-2xl" role="img" aria-label="sleeping robot">
        🤖
      </span>
    </div>
  );
}
