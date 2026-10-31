import Link from "next/link";
import { loginAction } from "@/app/login/actions";

interface LoginPageProps {
  searchParams: Promise<{ error?: string; registered?: string }>;
}

export default async function LoginPage({ searchParams }: LoginPageProps) {
  const { error, registered } = await searchParams;

  return (
    <main className="container">
      <div className="auth-shell row g-0 mx-auto">
        <div className="col-md-5 d-none d-md-flex">
          <section className="auth-story" aria-label="Learn together">
            <Link className="auth-brand text-white mb-auto" href="/">
              <img className="brand-mark-image" src="/assets/images/mentormatch-mark.svg" alt="" />
              MentorMatch
            </Link>
            <div>
              <h2>Make your next learning goal a little closer.</h2>
              <p>Find a fellow student who can help you move forward, one session at a time.</p>
            </div>
          </section>
        </div>
        <div className="col-md-7">
          <section className="auth-form-panel">
            <Link className="auth-brand mb-4 d-inline-flex d-md-none" href="/">
              <img className="brand-mark-image" src="/assets/images/mentormatch-mark.svg" alt="" />
              MentorMatch
            </Link>
            <span className="eyebrow mb-2">Welcome back</span>
            <h1>Sign in to your account</h1>
            <p className="text-muted mb-4">Pick up where your learning journey left off.</p>
            {registered && (
              <div className="alert alert-success" role="status">
                Account created. Sign in after your registration is reviewed.
              </div>
            )}
            {error && <div className="alert alert-danger" role="alert">{error}</div>}
            <form action={loginAction}>
              <div className="mb-3">
                <label htmlFor="email" className="form-label">Email address</label>
                <input
                  type="email"
                  className="form-control"
                  id="email"
                  name="email"
                  autoComplete="email"
                  required
                />
              </div>
              <div className="mb-4">
                <label htmlFor="password" className="form-label">Password</label>
                <input
                  type="password"
                  className="form-control"
                  id="password"
                  name="password"
                  autoComplete="current-password"
                  required
                />
              </div>
              <button type="submit" className="btn btn-primary btn-lg w-100">
                Sign in <i className="bi bi-arrow-right ms-2" aria-hidden="true" />
              </button>
            </form>
            <p className="text-center text-muted mt-4 mb-0">
              New to MentorMatch? <Link href="/register" className="fw-semibold">Create an account</Link>
            </p>
            <p className="text-center mt-3 mb-0">
              <Link href="/" className="small text-muted text-decoration-none">Back to home</Link>
            </p>
          </section>
        </div>
      </div>
    </main>
  );
}
