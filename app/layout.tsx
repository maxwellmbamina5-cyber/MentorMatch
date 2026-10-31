import type { Metadata } from "next";
import type { ReactNode } from "react";
import "./globals.css";

export const metadata: Metadata = {
  title: "MentorMatch | Learn better, together",
  description:
    "Connect with a fellow student who can help you move forward, one session at a time.",
  icons: {
    icon: "/assets/images/mentormatch-mark.svg",
  },
};

export default function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  return (
    <html lang="en">
      <head>
        <meta name="theme-color" content="#123c43" />
        <link
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          rel="stylesheet"
        />
        <link
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"
          rel="stylesheet"
        />
        <link href="/assets/css/style.css" rel="stylesheet" />
        <link href="/assets/css/mobile.css" rel="stylesheet" />
        <link href="/assets/css/landing.css" rel="stylesheet" />
      </head>
      <body>{children}</body>
    </html>
  );
}
