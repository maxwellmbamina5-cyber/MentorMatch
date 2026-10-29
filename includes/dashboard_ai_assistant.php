<?php
$assistant_role = $assistant_role ?? ($_SESSION['user_role'] ?? '');
$assistant_is_mentor = $assistant_role === 'mentor';
$assistant_has_subscription = (bool)($assistant_has_subscription ?? false);
$assistant_price = getSubscriptionPrice($assistant_role);
$assistant_welcome = $assistant_is_mentor
    ? 'Welcome! Tell me what you would like to improve. I can help you prepare sessions, explain difficult topics, and make the most of mentee feedback.'
    : 'Hi! Ask me how to find a mentor, prepare for a session, catch up on coursework, or check a booking.';
$assistant_attention = $assistant_is_mentor
    ? 'Need a fresh idea for your next mentoring session?'
    : 'Need help finding a mentor or catching up with coursework?';
?>
<div class="dashboard-ai-widget" data-ai-widget>
    <aside class="dashboard-ai-teaser" data-ai-teaser aria-live="polite">
        <button type="button" class="dashboard-ai-teaser-close" data-ai-dismiss aria-label="Dismiss assistant message">
            <i class="bi bi-x" aria-hidden="true"></i>
        </button>
        <span class="dashboard-ai-teaser-icon" aria-hidden="true"><i class="bi bi-stars"></i></span>
        <p><?php echo htmlspecialchars($assistant_attention, ENT_QUOTES, 'UTF-8'); ?></p>
        <button type="button" class="dashboard-ai-teaser-action" data-ai-open>Open assistant</button>
    </aside>

    <section class="dashboard-ai" id="dashboard-ai-panel" data-ai-assistant
        data-subscribed="<?php echo $assistant_has_subscription ? 'true' : 'false'; ?>"
        data-endpoint="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>api/assistant.php"
        data-ai-welcome="<?php echo htmlspecialchars($assistant_welcome, ENT_QUOTES, 'UTF-8'); ?>"
        aria-labelledby="dashboard-ai-title" hidden>
        <header class="dashboard-ai-header">
            <div class="dashboard-ai-heading">
                <span class="dashboard-ai-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
                <div>
                    <span class="dashboard-ai-eyebrow"><?php echo $assistant_is_mentor ? 'Your mentor coach' : 'Your study guide'; ?></span>
                    <h2 id="dashboard-ai-title">MentorMatch AI</h2>
                    <p><?php echo $assistant_is_mentor
                        ? 'Practical ideas to help you mentor with confidence.'
                        : 'Guidance for finding mentors and making study progress.'; ?></p>
                </div>
            </div>
            <button type="button" class="dashboard-ai-close" data-ai-close aria-label="Close AI assistant">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </header>

        <?php if (!$assistant_has_subscription): ?>
            <div class="dashboard-ai-subscription" role="status">
                <span class="dashboard-ai-subscription-icon"><i class="bi bi-lock-fill" aria-hidden="true"></i></span>
                <h3>Unlock your AI assistant</h3>
                <p>Your monthly subscription includes AI chat. Subscribe to start asking questions and get personalized guidance.</p>
                <a class="btn btn-primary w-100" href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>subscription.php">
                    View subscription · ZMW <?php echo number_format($assistant_price, 0); ?>/month
                </a>
            </div>
        <?php else: ?>
            <div class="dashboard-ai-chat" data-ai-messages aria-live="polite" aria-label="AI conversation">
                <div class="dashboard-ai-message assistant-message">
                    <span class="dashboard-ai-avatar" aria-hidden="true"><i class="bi bi-stars"></i></span>
                    <p><?php echo htmlspecialchars($assistant_welcome, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>

            <div class="dashboard-ai-prompts" aria-label="Suggested questions">
                <?php if ($assistant_is_mentor): ?>
                    <button type="button" class="dashboard-ai-prompt" data-ai-prompt="Give me a simple 30-minute session plan that helps a mentee participate.">Plan a better session</button>
                    <button type="button" class="dashboard-ai-prompt" data-ai-prompt="How can I explain a difficult course topic clearly and check that my mentee understands?">Explain topics clearly</button>
                    <button type="button" class="dashboard-ai-prompt" data-ai-prompt="How can I use mentee feedback constructively without pressuring anyone for a rating?">Learn from feedback</button>
                <?php else: ?>
                    <button type="button" class="dashboard-ai-prompt" data-ai-prompt="How do I find a mentor for one of my courses by course code or course name?">Find a course mentor</button>
                    <button type="button" class="dashboard-ai-prompt" data-ai-prompt="Help me plan my first mentorship session so I can catch up with my course.">Prepare for a session</button>
                    <button type="button" class="dashboard-ai-prompt" data-ai-prompt="How do I check whether a mentor has accepted my session request?">Check a booking</button>
                <?php endif; ?>
            </div>

            <form class="dashboard-ai-form" data-ai-form>
                <label class="visually-hidden" for="dashboard-ai-input">Ask your MentorMatch assistant</label>
                <textarea id="dashboard-ai-input" class="form-control" rows="2" maxlength="1600"
                    placeholder="<?php echo $assistant_is_mentor ? 'Ask for mentoring advice...' : 'Ask a question about mentoring or your studies...'; ?>"
                    data-ai-input required></textarea>
                <button type="submit" class="btn btn-primary dashboard-ai-send" data-ai-send aria-label="Send message">
                    <span>Ask</span> <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </button>
            </form>

            <footer class="dashboard-ai-footer">
                <span data-ai-status>This conversation stays on this page and is sent to the local AI on this computer.</span>
                <button type="button" class="dashboard-ai-reset" data-ai-reset>New chat</button>
            </footer>
        <?php endif; ?>
    </section>

    <button type="button" class="dashboard-ai-launcher" data-ai-toggle aria-expanded="false" aria-controls="dashboard-ai-panel">
        <span class="dashboard-ai-launcher-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
        <span>Ask AI</span>
        <span class="dashboard-ai-launcher-dot" aria-hidden="true"></span>
    </button>
</div>
