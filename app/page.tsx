import Link from "next/link";

const steps = [
  {
    number: "01",
    icon: "bi-person-lines-fill",
    title: "Create your account",
    description: "Tell us what you are studying and what kind of support would help you most.",
  },
  {
    number: "02",
    icon: "bi-search-heart",
    title: "Find your match",
    description: "Explore mentors by course and choose someone who fits your learning goals.",
  },
  {
    number: "03",
    icon: "bi-calendar2-heart",
    title: "Learn and grow",
    description: "Request a session, learn together, and keep track of your mentorship journey.",
  },
];

export default function HomePage() {
  return (
    <div className="landing-page">
      <a className="skip-link" href="#main-content">Skip to content</a>
      <nav className="navbar navbar-expand-lg landing-nav" aria-label="Main navigation">
        <div className="container">
          <Link className="navbar-brand landing-brand" href="/" aria-label="MentorMatch home">
            <img className="brand-mark-image" src="/assets/images/mentormatch-mark.svg" alt="" />
            MentorMatch
          </Link>
          <div className="navbar-nav ms-auto align-items-lg-center gap-lg-2">
            <a className="nav-link" href="#how-it-works">How it works</a>
            <a className="nav-link" href="#why-mentormatch">Why MentorMatch</a>
            <Link className="btn btn-outline-light landing-nav-login" href="/login">Sign in</Link>
            <Link className="btn btn-light landing-nav-join" href="/register">
              Join MentorMatch <i className="bi bi-arrow-right ms-1" aria-hidden="true" />
            </Link>
          </div>
        </div>
      </nav>

      <main id="main-content">
        <section className="landing-hero">
          <div className="container">
            <div className="row align-items-center g-5">
              <div className="col-lg-6">
                <div className="hero-copy">
                  <span className="eyebrow">
                    <span className="eyebrow-dot" /> A stronger way to learn together
                  </span>
                  <h1>Big goals feel closer <span>with the right mentor.</span></h1>
                  <p className="hero-lede">
                    Connect with a fellow student who has been there. Get practical guidance,
                    work through tough topics, and make every study session count.
                  </p>
                  <div className="hero-actions">
                    <Link className="btn btn-primary btn-lg" href="/register">
                      Find your mentor <i className="bi bi-arrow-up-right ms-2" aria-hidden="true" />
                    </Link>
                    <a className="btn btn-link btn-lg hero-secondary" href="#how-it-works">
                      <i className="bi bi-play-circle me-2" aria-hidden="true" />
                      See how it works
                    </a>
                  </div>
                  <div className="hero-note">
                    <i className="bi bi-check-circle-fill" aria-hidden="true" />
                    Peer-to-peer support, built around your learning goals.
                  </div>
                </div>
              </div>
              <div className="col-lg-6">
                <div className="hero-visual">
                  <img
                    className="hero-photo"
                    src="/assets/images/unza-students.jpg"
                    alt="Students at the University of Zambia walking together on campus"
                    fetchPriority="high"
                  />
                  <div className="hero-photo-shade" aria-hidden="true" />
                  <div className="photo-caption">
                    <span className="caption-icon"><i className="bi bi-chat-heart-fill" aria-hidden="true" /></span>
                    <span>
                      <strong>Learn from someone who gets it</strong>
                      <small>Support that moves you forward</small>
                    </span>
                  </div>
                  <div className="hero-sticker" aria-hidden="true">
                    <i className="bi bi-lightbulb-fill" />
                    <span>Ask questions.<br />Build confidence.</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="trust-strip" aria-label="MentorMatch benefits">
          <div className="container">
            <div className="trust-items">
              <span><i className="bi bi-people-fill" aria-hidden="true" /> Peer-powered learning</span>
              <span><i className="bi bi-calendar2-check-fill" aria-hidden="true" /> Sessions that fit your schedule</span>
              <span><i className="bi bi-bullseye" aria-hidden="true" /> Focused on your goals</span>
            </div>
          </div>
        </section>

        <section className="how-section section-space" id="how-it-works">
          <div className="container">
            <div className="section-heading text-center">
              <span className="eyebrow">A simple path to progress</span>
              <h2>Good support makes all the difference.</h2>
              <p>From finding the right person to celebrating what you can do, every step is designed to feel straightforward.</p>
            </div>
            <div className="row g-4 how-cards">
              {steps.map((step) => (
                <div className="col-md-4" key={step.number}>
                  <article className="how-card">
                    <div className="how-icon">
                      <i className={`bi ${step.icon}`} aria-hidden="true" />
                      <span>{step.number}</span>
                    </div>
                    <h3>{step.title}</h3>
                    <p>{step.description}</p>
                  </article>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="story-section section-space" id="why-mentormatch">
          <div className="container">
            <div className="row align-items-center g-5">
              <div className="col-lg-6">
                <div className="story-photo-wrap">
                  <img
                    className="story-photo"
                    src="/assets/images/cbu-campus.jpg"
                    alt="The Copperbelt University campus in Kitwe, Zambia"
                    loading="lazy"
                  />
                  <div className="story-photo-label">
                    <i className="bi bi-mortarboard-fill" aria-hidden="true" />
                    A campus community where peers can help each other grow.
                  </div>
                </div>
              </div>
              <div className="col-lg-6">
                <span className="eyebrow">Why MentorMatch</span>
                <h2>Learning is better when you do it together.</h2>
                <p>
                  Find support from students who understand your courses, your campus,
                  and the goals you are working toward.
                </p>
                <Link className="btn btn-primary" href="/register">Get started</Link>
              </div>
            </div>
          </div>
        </section>
      </main>

      <footer className="py-4">
        <div className="container d-flex flex-wrap justify-content-between gap-3">
          <span>© {new Date().getFullYear()} MentorMatch</span>
          <Link href="/login">Sign in</Link>
        </div>
      </footer>
    </div>
  );
}
