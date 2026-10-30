import type { Metadata } from "next";
import { Questrial } from "next/font/google";
import "./globals.css";

/**
 * Product typography: Questia → Questrial (Google Fonts), the real webfont
 * matching that name. Used for both display and body.
 */
const questia = Questrial({
  weight: "400",
  subsets: ["latin", "latin-ext"],
  variable: "--font-questia",
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
    <html lang="es" className={`${questia.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col font-[family-name:var(--font-questia)]">
        {children}
      </body>
    </html>
  );
}
