import { notFound, redirect } from "next/navigation";
import { logoutAction } from "@/app/login/actions";
import { getSession } from "@/lib/session";

interface RoleDashboardProps {
  params: Promise<{ role: string }>;
}

const roles = new Set(["admin", "mentor", "mentee"]);

export default async function RoleDashboardPage({ params }: RoleDashboardProps) {
  const { role } = await params;
  if (!roles.has(role)) {
    notFound();
  }

  const session = await getSession();
  const user = session.user;
  if (!user) {
    redirect("/login");
  }
  if (user.role !== role) {
    redirect(`/${user.role}/dashboard`);
  }
  if (user.status !== "active") {
    redirect("/dashboard");
  }

  return (
    <main className="container py-5">
      <section className="card mx-auto p-4" style={{ maxWidth: 760 }}>
        <span className="eyebrow">{role}</span>
        <h1 className="mt-2">Welcome, {user.name}</h1>
        <p className="text-muted">
          This dashboard is being migrated from the existing MentorMatch application.
        </p>
        <form action={logoutAction}>
          <button type="submit" className="btn btn-outline-secondary">Sign out</button>
        </form>
      </section>
    </main>
  );
}
