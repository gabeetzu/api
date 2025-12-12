import './globals.css';

export const metadata = {
  title: 'Infinity VR Companion',
  description: 'Your VR Companion Assistant PWA'
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
