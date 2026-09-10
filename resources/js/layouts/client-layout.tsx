import { Link } from "@inertiajs/react";
import type { PropsWithChildren } from "react";
import { dashboard, logout } from "@/routes/client";
import { create } from "@/routes/client/orders";

export default function ClientLayout({ children }: PropsWithChildren) {
    return (
        <div className="bg-background text-foreground min-h-screen">
            <header className="border-b">
                <nav
                    className="mx-auto flex max-w-5xl flex-wrap items-center gap-6 px-6 py-4"
                    aria-label="Client navigation"
                >
                    <Link href={dashboard()} className="font-semibold">
                        Customer dashboard
                    </Link>
                    <Link href={create()} className="text-sm">
                        Order hosting
                    </Link>
                    <Link href={logout()} method="post" as="button" className="ml-auto text-sm">
                        Log out
                    </Link>
                </nav>
            </header>
            <main className="mx-auto max-w-5xl px-6 py-10">{children}</main>
        </div>
    );
}
