import Head from 'next/head';
import Link from 'next/link';

export default function OfflinePage() {
  const containerStyle = {
    minHeight: '100vh',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '2rem',
    backgroundColor: '#0b1021',
    color: '#f5f6fa',
    fontFamily: 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    textAlign: 'center',
  };

  const cardStyle = {
    maxWidth: '480px',
    width: '100%',
    background: 'rgba(255, 255, 255, 0.04)',
    border: '1px solid rgba(255, 255, 255, 0.08)',
    borderRadius: '16px',
    padding: '24px',
    boxShadow: '0 20px 50px rgba(0, 0, 0, 0.35)',
    backdropFilter: 'blur(8px)',
  };

  const buttonRowStyle = {
    display: 'flex',
    gap: '12px',
    justifyContent: 'center',
    marginTop: '20px',
    flexWrap: 'wrap',
  };

  const buttonStyle = {
    padding: '12px 18px',
    borderRadius: '10px',
    border: 'none',
    cursor: 'pointer',
    fontWeight: 600,
    fontSize: '15px',
    transition: 'transform 120ms ease, box-shadow 120ms ease',
  };

  const primaryButtonStyle = {
    ...buttonStyle,
    background: 'linear-gradient(135deg, #7c3aed, #22d3ee)',
    color: '#0b1021',
    boxShadow: '0 12px 30px rgba(34, 211, 238, 0.25)',
  };

  const secondaryButtonStyle = {
    ...buttonStyle,
    background: 'rgba(255, 255, 255, 0.08)',
    color: '#f5f6fa',
    border: '1px solid rgba(255, 255, 255, 0.12)',
  };

  return (
    <div style={containerStyle}>
      <Head>
        <title>You&apos;re offline</title>
      </Head>
      <div style={cardStyle}>
        <h1 style={{ fontSize: '26px', marginBottom: '12px' }}>You&apos;re offline</h1>
        <p style={{ lineHeight: 1.6, color: 'rgba(245, 246, 250, 0.85)' }}>
          Infinity VR Companion needs an internet connection for the AI assistant.
          When you&apos;re back online, retry to continue where you left off.
        </p>
        <div style={buttonRowStyle}>
          <Link href="/" legacyBehavior>
            <a style={primaryButtonStyle}>Go Home</a>
          </Link>
          <button
            type="button"
            onClick={() => window.location.reload()}
            style={secondaryButtonStyle}
          >
            Retry
          </button>
        </div>
      </div>
    </div>
  );
}
