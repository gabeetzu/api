import './globals.css';

export const metadata = {
  title: 'Infinity VR Companion',
  description: 'Your VR Companion Assistant PWA',
  manifest: '/manifest.webmanifest',
  themeColor: '#000000',
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
