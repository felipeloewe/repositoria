import type { Metadata } from "next";
import { Plus_Jakarta_Sans } from "next/font/google";
import "./globals.css";

/**
 * Modern geometric sans for the vibrant shell language.
 * (Questia/Questrial remains a historical preference; Felipe asked for livelier UI.)
 */
const sansModern = Plus_Jakarta_Sans({
  subsets: ["latin", "latin-ext"],
  variable: "--font-sans-modern",
  weight: ["400", "500", "600", "700"],
});

export const metadata: Metadata = {
  title: {
    default: "Repositoria",
    template: "%s · Repositoria",
  },
  description:
    "Repositorio documental multi-empresa: organización, sucursales y perfiles.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="es" className={`${sansModern.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col font-[family-name:var(--font-sans-modern)]">
        {children}
      </body>
    </html>
  );
}
