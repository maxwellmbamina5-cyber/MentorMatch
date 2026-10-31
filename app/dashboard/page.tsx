import Link from "next/link";
import { redirect } from "next/navigation";
import { logoutAction } from "@/app/login/actions";
import { getSession } from "@/lib/session";

export default async function DashboardPage() {
  const session = await getSession();
  const user = session.user;
  if (!user) {
    redirect("/login");
  }

  return (
    <main className="container py-5">
      <section className="card mx-auto p-4" style={{ maxWidth: 680 }}>
        <span className="eyebrow">MentorMatch</span>
        <h1 className="mt-2">Welcome, {user.name}</h1>
        <p className="text-muted">
          Your {user.role} account is {user.status}. Role-specific tools are being migrated.
        </p>
        <div className="d-flex flex-wrap gap-3">
          {user.status === "active" && (
            <Link className="btn btn-primary" href={`/${user.role}/dashboard`}>
              Open dashboard
            </Link>
          )}
          <form action={logoutAction}>
            <button type="submit" className="btn btn-outline-secondary">Sign out</button>
          </form>
        </div>
      </section>
    </main>
  );
}
