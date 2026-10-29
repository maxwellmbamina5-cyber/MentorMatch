<?php
$role = $_SESSION['user_role'] ?? '';
$navigation = [
    'admin' => [
        'home' => ['Dashboard', 'admin/dashboard.php', 'bi-grid'],
        'groups' => [
            ['People & learning', [
                ['Users', 'admin/users.php', 'bi-people'],
                ['Mentor applications', 'admin/applications.php', 'bi-file-earmark-check'],
                ['Course enrollments', 'admin/courses.php', 'bi-book'],
                ['Semesters', 'admin/semesters.php', 'bi-calendar-range'],
            ]],
            ['Sessions & quality', [
                ['Sessions', 'admin/sessions.php', 'bi-calendar-check'],
                ['Certificates', 'admin/certificates.php', 'bi-award'],
                ['Feedback', 'admin/feedback.php', 'bi-star'],
            ]],
            ['Finance', [
                ['Subscriptions', 'admin/subscriptions.php', 'bi-card-checklist'],
                ['Payments', 'admin/payments.php', 'bi-credit-card'],
                ['Payouts', 'admin/payouts.php', 'bi-cash-stack'],
            ]],
            ['Reports & system', [
                ['Reports', 'admin/reports.php', 'bi-file-earmark-text'],
                ['System logs', 'admin/logs.php', 'bi-clipboard-data'],
                ['Backups', 'admin/backup.php', 'bi-database'],
                ['Cache', 'admin/cache.php', 'bi-hdd-stack'],
                ['Settings', 'admin/settings.php', 'bi-gear'],
                ['Profile', 'admin/profile.php', 'bi-person'],
            ]],
        ],
    ],
    'mentor' => [
        'home' => ['Dashboard', 'mentor/dashboard.php', 'bi-grid'],
        'groups' => [
            ['Mentoring', [
                ['Sessions', 'mentor/sessions.php', 'bi-calendar-check'],
                ['Availability', 'mentor/availability.php', 'bi-clock'],
                ['My rates', 'mentor/rates.php', 'bi-currency-dollar'],
                ['History', 'mentor/history.php', 'bi-clock-history'],
                ['Certificates', 'mentor/certificate.php', 'bi-award'],
                ['Feedback privacy', 'mentor/feedback.php', 'bi-shield-lock'],
            ]],
            ['Earnings', [
                ['Earnings', 'mentor/earnings.php', 'bi-wallet'],
                ['Payouts', 'mentor/payout.php', 'bi-cash-stack'],
            ]],
            ['Account', [
                ['Subscription', 'subscription.php', 'bi-card-checklist'],
                ['Profile', 'mentor/profile.php', 'bi-person'],
            ]],
        ],
    ],
    'mentee' => [
        'home' => ['Dashboard', 'mentee/dashboard.php', 'bi-grid'],
        'quick' => ['Find a mentor', 'mentee/search.php', 'bi-search'],
        'groups' => [
            ['My learning', [
                ['Courses', 'mentee/courses.php', 'bi-book'],
                ['Sessions', 'mentee/sessions.php', 'bi-calendar-check'],
                ['Session history', 'mentee/history.php', 'bi-clock-history'],
                ['Feedback', 'mentee/feedback.php', 'bi-star'],
            ]],
            ['Account', [
                ['Subscription', 'subscription.php', 'bi-card-checklist'],
                ['Payments', 'mentee/payments.php', 'bi-credit-card'],
                ['Profile', 'mentee/profile.php', 'bi-person'],
            ]],
        ],
    ],
];

if (isset($navigation[$role])):
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
    $menu_id = 'appNavigation';
?>
<nav class="app-navbar" aria-label="Main navigation">
    <div class="app-navbar-inner">
        <a class="app-navbar-brand" href="<?php echo BASE_URL . $navigation[$role]['home'][1]; ?>">
            <img src="<?php echo BASE_URL; ?>assets/images/mentormatch-mark.svg" alt="" width="40" height="40">
            <span>MentorMatch</span>
        </a>
        <button class="app-navbar-toggle" type="button" aria-controls="<?php echo $menu_id; ?>" aria-expanded="false" aria-label="Open navigation">
            <span></span><span></span><span></span>
        </button>
        <div class="app-navbar-collapse" id="<?php echo $menu_id; ?>">
            <div class="app-navbar-links">
                <?php
                $home = $navigation[$role]['home'];
                $home_active = $current_page === basename($home[1]);
                ?>
                <a class="app-nav-link<?php echo $home_active ? ' active' : ''; ?>" href="<?php echo BASE_URL . $home[1]; ?>">
                    <i class="bi <?php echo $home[2]; ?>" aria-hidden="true"></i><?php echo htmlspecialchars($home[0]); ?>
                </a>
                <?php if (isset($navigation[$role]['quick'])):
                    $quick = $navigation[$role]['quick'];
                    $quick_active = $current_page === basename($quick[1]);
                ?>
                    <a class="app-nav-link<?php echo $quick_active ? ' active' : ''; ?>" href="<?php echo BASE_URL . $quick[1]; ?>">
                        <i class="bi <?php echo $quick[2]; ?>" aria-hidden="true"></i><?php echo htmlspecialchars($quick[0]); ?>
                    </a>
                <?php endif; ?>
                <?php foreach ($navigation[$role]['groups'] as $group):
                    $group_active = false;
                    foreach ($group[1] as $item) {
                        if ($current_page === basename($item[1])) {
                            $group_active = true;
                            break;
                        }
                    }
                ?>
                    <details class="app-nav-group"<?php echo $group_active ? ' open' : ''; ?>>
                        <summary><?php echo htmlspecialchars($group[0]); ?><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                        <div class="app-nav-menu">
                            <?php foreach ($group[1] as $item):
                                $active = $current_page === basename($item[1]);
                            ?>
                                <a class="app-nav-link<?php echo $active ? ' active' : ''; ?>" href="<?php echo BASE_URL . $item[1]; ?>">
                                    <i class="bi <?php echo $item[2]; ?>" aria-hidden="true"></i><?php echo htmlspecialchars($item[0]); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
                <a class="app-nav-link app-nav-logout" href="<?php echo BASE_URL; ?>logout.php" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>Sign out
                </a>
            </div>
        </div>
    </div>
</nav>
<script src="<?php echo BASE_URL; ?>assets/js/mobile-nav.js" defer></script>
<?php endif; ?>
