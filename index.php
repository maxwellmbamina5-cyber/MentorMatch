<?php
session_start();

$dashboard_url = null;
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    $dashboard_pages = [
        'admin' => 'admin/dashboard.php',
        'mentor' => 'mentor/dashboard.php',
        'mentee' => 'mentee/dashboard.php',
    ];

    if (isset($dashboard_pages[$role])) {
        header('Location: ' . $dashboard_pages[$role]);
        exit();
    }

    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123c43">
    <meta name="description" content="Learn with a peer mentor, build confidence in your courses, and make progress together with MentorMatch.">
    <title>MentorMatch | Learn better, together</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/mentormatch-mark.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/mobile.css" rel="stylesheet">
    <link href="assets/css/landing.css" rel="stylesheet">
</head>
<body class="landing-page">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-lg landing-nav" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand landing-brand" href="index.php" aria-label="MentorMatch home">
                <img class="brand-mark-image" src="assets/images/mentormatch-mark.svg" alt="">
                MentorMatch
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNavigation" aria-controls="landingNavigation" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="landingNavigation">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <a class="nav-link" href="#how-it-works">How it works</a>
                    <a class="nav-link" href="#why-mentormatch">Why MentorMatch</a>
                    <a class="btn btn-outline-light landing-nav-login" href="login.php">Sign in</a>
                    <a class="btn btn-light landing-nav-join" href="register.php">Join MentorMatch <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <main id="main-content">
        <section class="landing-hero">
            <div class="container">
                <div class="row align-items-center g-5">
                    <div class="col-lg-6">
                        <div class="hero-copy">
                            <span class="eyebrow"><span class="eyebrow-dot"></span> A stronger way to learn together</span>
                            <h1>Big goals feel closer <span>with the right mentor.</span></h1>
                            <p class="hero-lede">Connect with a fellow student who has been there. Get practical guidance, work through tough topics, and make every study session count.</p>
                            <div class="hero-actions">
                                <a class="btn btn-primary btn-lg" href="register.php">Find your mentor <i class="bi bi-arrow-up-right ms-2" aria-hidden="true"></i></a>
                                <a class="btn btn-link btn-lg hero-secondary" href="#how-it-works"><i class="bi bi-play-circle me-2" aria-hidden="true"></i>See how it works</a>
                            </div>
                            <div class="hero-note"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Peer-to-peer support, built around your learning goals.</div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="hero-visual">
                            <img class="hero-photo" src="assets/images/unza-students.jpg" alt="Students at the University of Zambia walking together on campus" fetchpriority="high">
                            <div class="hero-photo-shade" aria-hidden="true"></div>
                            <div class="photo-caption"><span class="caption-icon"><i class="bi bi-chat-heart-fill" aria-hidden="true"></i></span><span><strong>Learn from someone who gets it</strong><small>Support that moves you forward</small></span></div>
                            <div class="hero-sticker" aria-hidden="true"><i class="bi bi-lightbulb-fill"></i><span>Ask questions.<br>Build confidence.</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="trust-strip" aria-label="MentorMatch benefits">
            <div class="container">
                <div class="trust-items">
                    <span><i class="bi bi-people-fill" aria-hidden="true"></i> Peer-powered learning</span>
                    <span><i class="bi bi-calendar2-check-fill" aria-hidden="true"></i> Sessions that fit your schedule</span>
                    <span><i class="bi bi-bullseye" aria-hidden="true"></i> Focused on your goals</span>
                </div>
            </div>
        </section>

        <section class="how-section section-space" id="how-it-works">
            <div class="container">
                <div class="section-heading text-center">
                    <span class="eyebrow">A simple path to progress</span>
                    <h2>Good support makes all the difference.</h2>
                    <p>From finding the right person to celebrating what you can do, every step is designed to feel straightforward.</p>
                </div>
                <div class="row g-4 how-cards">
                    <div class="col-md-4">
                        <article class="how-card">
                            <div class="how-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i><span>01</span></div>
                            <h3>Create your account</h3>
                            <p>Tell us what you are studying and what kind of support would help you most.</p>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="how-card">
                            <div class="how-icon how-icon-sand"><i class="bi bi-search-heart" aria-hidden="true"></i><span>02</span></div>
                            <h3>Find your match</h3>
                            <p>Explore mentors by course, check their details, and choose someone who fits your goals.</p>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="how-card">
                            <div class="how-icon how-icon-sky"><i class="bi bi-calendar2-heart" aria-hidden="true"></i><span>03</span></div>
                            <h3>Learn and grow</h3>
                            <p>Request a session, learn together, and keep track of your mentorship journey.</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="story-section section-space" id="why-mentormatch">
            <div class="container">
                <div class="row align-items-center g-5">
                    <div class="col-lg-6">
                        <div class="story-photo-wrap">
                            <img class="story-photo" src="assets/images/cbu-campus.jpg" alt="The Copperbelt University clinic in Kitwe, Zambia" loading="lazy">
                            <div class="story-photo-label"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i> A campus community where peers can help each other grow.</div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="story-copy">
                            <span class="eyebrow">More than a study session</span>
                            <h2>Make progress at your own pace.</h2>
                            <p class="story-lede">Sometimes the best person to learn from is someone who remembers what it was like to be in your shoes.</p>
                            <ul class="benefit-list">
                                <li><span><i class="bi bi-check2" aria-hidden="true"></i></span><div><strong>Get help that feels approachable</strong><p>Talk through course topics with a fellow student in a supportive setting.</p></div></li>
                                <li><span><i class="bi bi-check2" aria-hidden="true"></i></span><div><strong>Keep your learning organised</strong><p>Manage your profile, session requests, and upcoming sessions from one place.</p></div></li>
                                <li><span><i class="bi bi-check2" aria-hidden="true"></i></span><div><strong>Share what you know</strong><p>Experienced students can offer guidance and make a real difference to a peer.</p></div></li>
                            </ul>
                            <a class="btn btn-dark btn-lg" href="register.php">Get started today <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="role-section section-space">
            <div class="container">
                <div class="role-panel">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <span class="eyebrow eyebrow-light">There is a place for you here</span>
                            <h2>Learn from a peer. Or become the person you once needed.</h2>
                            <p>Whether you are looking for guidance or ready to share your experience, start with the role that fits you.</p>
                        </div>
                        <div class="col-lg-5">
                            <div class="role-actions">
                                <a class="role-link" href="register.php?role=mentee"><span class="role-link-icon"><i class="bi bi-book-half" aria-hidden="true"></i></span><span><strong>I want a mentor</strong><small>Find support for your studies</small></span><i class="bi bi-arrow-up-right ms-auto" aria-hidden="true"></i></a>
                                <a class="role-link" href="register.php?role=mentor"><span class="role-link-icon role-link-icon-gold"><i class="bi bi-stars" aria-hidden="true"></i></span><span><strong>I want to mentor</strong><small>Share your knowledge with peers</small></span><i class="bi bi-arrow-up-right ms-auto" aria-hidden="true"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        <div class="container d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <a class="landing-brand footer-brand" href="index.php"><img class="brand-mark-image" src="assets/images/mentormatch-mark.svg" alt=""> MentorMatch</a>
            <div class="landing-footer-details">
                <p class="mb-0">Learn better, together. <span aria-hidden="true">·</span> <a href="login.php">Sign in</a></p>
                <small>
                    Photos:
                    <a href="https://commons.wikimedia.org/wiki/File:University_students_on_the_move.jpg" target="_blank" rel="noopener noreferrer">Duke Makangila</a>
                    and
                    <a href="https://commons.wikimedia.org/wiki/File:Picture_of_the_Copperbelt_University_clinic.jpg" target="_blank" rel="noopener noreferrer">Mwewa Chipepo</a>,
                    <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a>.
                </small>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
