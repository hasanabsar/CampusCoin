<?php
require_once __DIR__ . '/includes/auth.php';

/* ---------- Contact form handler ---------- */
$contact = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
$contactErrors = [];
if (isPost() && post('form') === 'contact') {
    requireCsrf();
    $contact = ['name' => post('name'), 'email' => post('email'), 'subject' => post('subject'), 'message' => post('message')];
    if (mb_strlen($contact['name']) < 2 || mb_strlen($contact['name']) > 100) $contactErrors['name'] = 'Please enter your name.';
    if (!isValidEmail($contact['email'])) $contactErrors['email'] = 'Enter a valid email address.';
    if (mb_strlen($contact['subject']) < 3 || mb_strlen($contact['subject']) > 150) $contactErrors['subject'] = 'Please add a short subject.';
    if (mb_strlen($contact['message']) < 10 || mb_strlen($contact['message']) > 2000) $contactErrors['message'] = 'Message must be between 10 and 2000 characters.';
    if (!$contactErrors && (int) dbValue('SELECT COUNT(*) FROM contact_messages WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [clientIp()]) >= 5) {
        $contactErrors['message'] = 'Too many messages from your network. Please try again later.';
    }
    if (!$contactErrors) {
        dbRun('INSERT INTO contact_messages (name, email, subject, message, ip) VALUES (?, ?, ?, ?, ?)',
            [$contact['name'], $contact['email'], $contact['subject'], $contact['message'], clientIp()]);
        flash('success', 'Thanks for reaching out — we will get back to you soon.');
        redirect('index.php#contact');
    }
    flash('error', 'Please fix the highlighted fields and try again.');
}

$layout = 'public';
$pageTitle = 'Smart Money Management for Students';
require __DIR__ . '/includes/header.php';

$features = [
    ['trending-up', 'emerald', 'Income Tracking', 'Log allowance, scholarships and part-time pay so you always know what is coming in.'],
    ['minus-circle', 'coral', 'Expense Tracking', 'Add an expense in seconds. Smart category suggestions do the sorting for you.'],
    ['wallet', 'blue', 'Smart Budgets', 'Set monthly limits per category and get warned well before you overspend.'],
    ['bar-chart', 'navy', 'Spending Reports', 'Daily, weekly and monthly charts that show exactly where your money goes.'],
    ['bulb', 'amber', 'Saving Tips', 'Practical suggestions generated from your own spending — not generic advice.'],
    ['sparkles', 'blue', 'Financial Insights', 'A plain-language monthly summary of what changed and what to do next.'],
];
$steps = [
    ['Create Account', 'Sign up free with your email and set a monthly savings goal.'],
    ['Track Money', 'Record income and expenses as they happen.'],
    ['Set Budget', 'Give every category a monthly limit.'],
    ['Understand Spending', 'Open reports and charts to spot patterns.'],
    ['Save More', 'Follow tailored tips and hit your goal.'],
];
?>
<section class="hero" id="top">
    <span class="shape s1"></span><span class="shape s2"></span>
    <div class="container hero-grid">
        <div class="hero-anim">
            <span class="hero-badge"><i><?= icon('check', 13) ?></i> Free for every student</span>
            <h1><span class="hero-brand">CampusCoin</span>Smart Money Management for Students</h1>
            <p class="lead">Track your income, control your spending, manage your budget and build better financial habits.</p>
            <div class="hero-actions">
                <?php if ($authUser): ?>
                    <a class="btn btn-accent btn-lg" href="<?= e(url(homeFor($authUser))) ?>">Open dashboard <?= icon('arrow-right', 18) ?></a>
                <?php else: ?>
                    <a class="btn btn-accent btn-lg" href="<?= e(url('register.php')) ?>">Get Started <?= icon('arrow-right', 18) ?></a>
                    <a class="btn btn-ghost-light btn-lg" href="<?= e(url('login.php')) ?>">Login</a>
                <?php endif; ?>
            </div>
            <div class="hero-trust">
                <span><?= icon('shield', 18) ?> Private by design</span>
                <span><?= icon('wallet', 18) ?> Budgets &amp; alerts</span>
                <span><?= icon('bar-chart', 18) ?> Clear reports</span>
            </div>
        </div>

        <div class="hero-visual" aria-hidden="false">
            <div class="hero-photo">
                <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1100&q=70" alt="Group of university students talking together on campus" loading="eager" onerror="this.remove()">
            </div>
            <div class="float-card a">
                <span class="float-icon stat-icon emerald"><?= icon('check-circle', 20) ?></span>
                <div><strong>Budget on track</strong><small>Food · 62% used</small></div>
            </div>
            <div class="mock-dash" role="img" aria-label="Preview of the CampusCoin dashboard showing balance and spending">
                <div class="mock-top"><span>Remaining balance</span><span>This month</span></div>
                <div class="mock-value">Rs. 48,250</div>
                <div class="mock-delta">+ Rs. 12,400 saved</div>
                <div class="mock-bars"><span style="height:38%"></span><span style="height:62%"></span><span style="height:46%"></span><span style="height:78%"></span><span class="hi" style="height:100%"></span><span style="height:58%"></span></div>
                <div class="mock-row"><span>Campus Cafe</span><b class="amt-expense">-Rs. 500</b></div>
                <div class="mock-row"><span>Scholarship</span><b class="amt-income">+Rs. 20,000</b></div>
            </div>
            <div class="float-card b">
                <span class="float-icon stat-icon amber"><?= icon('target', 20) ?></span>
                <div><strong>Goal reached</strong><small>Saved Rs. 4,200 extra</small></div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="features">
    <div class="container">
        <div class="section-head">
            <h2>Everything a student budget needs</h2>
            <p>Simple tools that replace the notes app, the spreadsheet and the guesswork.</p>
        </div>
        <div class="features-grid">
            <?php foreach ($features as [$ico, $tone, $title, $text]): ?>
                <article class="feature-card">
                    <div class="feature-icon stat-icon <?= e($tone) ?>"><?= icon($ico, 24) ?></div>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($text) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section alt" id="how-it-works">
    <div class="container">
        <div class="section-head">
            <h2>From first entry to real savings</h2>
            <p>Five small steps that turn scattered spending into a habit you control.</p>
        </div>
        <ol class="timeline">
            <?php foreach ($steps as $i => [$title, $text]): ?>
                <li class="step">
                    <div class="step-num"><?= $i + 1 ?></div>
                    <div><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="section" id="about">
    <div class="container about-grid">
        <div class="about-photo">
            <img src="https://images.unsplash.com/photo-1541339907198-e08756dedf3f?auto=format&fit=crop&w=1000&q=70" alt="University campus building with students walking outside" loading="lazy" onerror="this.remove()">
        </div>
        <div class="about-copy">
            <h2>Built for how students actually live</h2>
            <p>University is often the first time you manage money on your own — allowance that arrives once a month, a part-time job, tuition, transport, and a social life to fund.</p>
            <p>CampusCoin gives you a clear picture of every rupee without the complexity of banking apps. See what you earn, set limits that make sense, and get gentle nudges when you drift.</p>
            <ul class="check-list">
                <li><?= icon('check', 18) ?> Categories designed around student spending</li>
                <li><?= icon('check', 18) ?> Monthly savings goal with progress tracking</li>
                <li><?= icon('check', 18) ?> Your data stays yours — admins only see aggregate statistics</li>
            </ul>
        </div>
    </div>
</section>

<section class="section alt" id="contact">
    <div class="container">
        <div class="section-head">
            <h2>Questions? Say hello</h2>
            <p>Send us a message and we will reply as soon as we can.</p>
        </div>
        <div class="contact-grid">
            <ul class="contact-info">
                <li><span class="feature-icon stat-icon blue"><?= icon('mail', 22) ?></span><div><strong>Email</strong><small>support@campuscoin.example</small></div></li>
                <li><span class="feature-icon stat-icon emerald"><?= icon('cap', 22) ?></span><div><strong>Built for students</strong><small>Feedback from students shapes every release.</small></div></li>
                <li><span class="feature-icon stat-icon amber"><?= icon('shield', 22) ?></span><div><strong>Privacy first</strong><small>We never sell or share your financial data.</small></div></li>
            </ul>
            <form class="card" method="post" action="<?= e(url('index.php#contact')) ?>" novalidate data-validate data-loading>
                <div class="card-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="form" value="contact">
                    <div class="form-grid">
                        <div class="field<?= isset($contactErrors['name']) ? ' has-error' : '' ?>">
                            <label for="c_name">Name</label>
                            <input id="c_name" name="name" class="input" required maxlength="100" autocomplete="name" value="<?= e($contact['name']) ?>">
                            <?php if (isset($contactErrors['name'])): ?><p class="field-error"><?= e($contactErrors['name']) ?></p><?php endif; ?>
                        </div>
                        <div class="field<?= isset($contactErrors['email']) ? ' has-error' : '' ?>">
                            <label for="c_email">Email</label>
                            <input id="c_email" name="email" type="email" class="input" required maxlength="190" autocomplete="email" value="<?= e($contact['email']) ?>">
                            <?php if (isset($contactErrors['email'])): ?><p class="field-error"><?= e($contactErrors['email']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="field<?= isset($contactErrors['subject']) ? ' has-error' : '' ?>">
                        <label for="c_subject">Subject</label>
                        <input id="c_subject" name="subject" class="input" required maxlength="150" value="<?= e($contact['subject']) ?>">
                        <?php if (isset($contactErrors['subject'])): ?><p class="field-error"><?= e($contactErrors['subject']) ?></p><?php endif; ?>
                    </div>
                    <div class="field<?= isset($contactErrors['message']) ? ' has-error' : '' ?>">
                        <label for="c_message">Message</label>
                        <textarea id="c_message" name="message" class="textarea" required minlength="10" maxlength="2000"><?= e($contact['message']) ?></textarea>
                        <?php if (isset($contactErrors['message'])): ?><p class="field-error"><?= e($contactErrors['message']) ?></p><?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg"><span class="btn-label"><?= icon('send', 18) ?> Send message</span></button>
                </div>
            </form>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>Start understanding your money today</h2>
                <p>Create a free account in under a minute.</p>
            </div>
            <a class="btn btn-accent btn-lg" href="<?= e(url($authUser ? homeFor($authUser) : 'register.php')) ?>"><?= $authUser ? 'Open dashboard' : 'Get Started' ?> <?= icon('arrow-right', 18) ?></a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
