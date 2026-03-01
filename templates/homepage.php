<?php
/**
 * Template: Homepage - Family Travel Tracker Landing Page
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get registration page URL
$register_url = '#';
$pages = get_pages();
foreach ($pages as $page) {
    if (has_shortcode($page->post_content, 'ftt_register')) {
        $register_url = get_permalink($page->ID);
        break;
    }
}

// Get dashboard or login URL
$dashboard_url = FTT_Pages::get_page_url('dashboard');
$login_url = wp_login_url($dashboard_url);
?>

<div class="ftt-homepage">
    <!-- Hero Section -->
    <section class="ftt-hero">
        <div class="ftt-hero-content">
            <h1 class="ftt-hero-title">
                Never Miss a Moment.<br>
                <span class="ftt-hero-subtitle">Track Every Journey Together.</span>
            </h1>
            <p class="ftt-hero-description">
                The complete family travel coordination platform for busy parents managing multiple kids' activities, 
                camps, college visits, and more. Stay organized, save money on flights, and keep everyone connected.
            </p>
            <div class="ftt-hero-cta">
                <a href="<?php echo esc_url($register_url); ?>" class="ftt-btn ftt-btn-primary ftt-btn-large">
                    Get Started Free
                </a>
                <a href="<?php echo esc_url($login_url); ?>" class="ftt-btn ftt-btn-secondary ftt-btn-large">
                    Sign In
                </a>
            </div>
            <p class="ftt-hero-note">✓ No credit card required  ✓ Set up in 2 minutes</p>
        </div>
    </section>

    <!-- Problem Section -->
    <section class="ftt-problem">
        <div class="ftt-container">
            <h2 class="ftt-section-title">Is This Your Life?</h2>
            <div class="ftt-problem-grid">
                <div class="ftt-problem-card">
                    <div class="ftt-problem-icon">📅</div>
                    <h3>Calendar Chaos</h3>
                    <p>Three kids, five different schedules, two parents trying to coordinate pickups, drop-offs, and who's where when.</p>
                </div>
                <div class="ftt-problem-card">
                    <div class="ftt-problem-icon">✈️</div>
                    <h3>Flight Price Stress</h3>
                    <p>Constantly checking prices for summer camps, college visits, or auditions. Did you book at the right time?</p>
                </div>
                <div class="ftt-problem-card">
                    <div class="ftt-problem-icon">💬</div>
                    <h3>Communication Gaps</h3>
                    <p>Co-parenting? Grandparents want updates? Everyone needs access but you're stuck forwarding emails.</p>
                </div>
                <div class="ftt-problem-card">
                    <div class="ftt-problem-icon">🎒</div>
                    <h3>Activity Overload</h3>
                    <p>Sports tournaments, music camps, college orientations, family vacations—how do you keep track of it all?</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Solution Section -->
    <section class="ftt-solution">
        <div class="ftt-container">
            <h2 class="ftt-section-title">One Platform. Every Trip. Complete Peace of Mind.</h2>
            <p class="ftt-section-subtitle">Family Travel Tracker brings order to the chaos with powerful tools designed specifically for families.</p>
            
            <div class="ftt-features-grid">
                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">🗓️</div>
                    <h3>Shared Family Calendar</h3>
                    <p>See every child's schedule in one place. Color-coded by kid, filterable by event type, accessible to everyone you choose.</p>
                    <ul class="ftt-feature-list">
                        <li>Multi-child support with individual colors</li>
                        <li>Event types: camps, visits, performances, medical, etc.</li>
                        <li>Subscribe from any calendar app (Google, Apple, Outlook)</li>
                        <li>Automatic timezone conversion</li>
                    </ul>
                </div>

                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">💰</div>
                    <h3>Smart Flight Price Tracking</h3>
                    <p>Stop obsessing over flight prices. We'll monitor them for you and alert you when it's time to book.</p>
                    <ul class="ftt-feature-list">
                        <li>Track prices for any route, any date</li>
                        <li>Email alerts when prices drop</li>
                        <li>Compare booking options side-by-side</li>
                        <li>Link flights to calendar events</li>
                    </ul>
                </div>

                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">👨‍👩‍👧‍👦</div>
                    <h3>Multi-Parent Access</h3>
                    <p>Perfect for divorced or separated parents. Everyone stays informed without the friction.</p>
                    <ul class="ftt-feature-list">
                        <li>Invite multiple parents or guardians</li>
                        <li>Grandparents can view schedules</li>
                        <li>Each person has appropriate access</li>
                        <li>No more "did you get my text?" moments</li>
                    </ul>
                </div>

                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">✈️</div>
                    <h3>Complete Travel Details</h3>
                    <p>Every trip in one place: flights, hotels, who's picking up, what time they land.</p>
                    <ul class="ftt-feature-list">
                        <li>Flight tracking with airline and confirmation #</li>
                        <li>Airport codes auto-complete</li>
                        <li>Housing/hotel tracking</li>
                        <li>Notes and special instructions</li>
                    </ul>
                </div>

                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">🔔</div>
                    <h3>Daily Email Digests</h3>
                    <p>Get a morning email with everything happening today and tomorrow. Start your day informed.</p>
                    <ul class="ftt-feature-list">
                        <li>Customizable digest times</li>
                        <li>See only your children's events</li>
                        <li>Flight prices at a glance</li>
                        <li>Booking reminders</li>
                    </ul>
                </div>

                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">📱</div>
                    <h3>Works Everywhere</h3>
                    <p>Mobile-friendly web app. No app store downloads required. Access from any device, anytime.</p>
                    <ul class="ftt-feature-list">
                        <li>Responsive design for phones and tablets</li>
                        <li>Works on iPhone, Android, iPad</li>
                        <li>Desktop or laptop friendly</li>
                        <li>Secure cloud-based storage</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="ftt-how-it-works">
        <div class="ftt-container">
            <h2 class="ftt-section-title">How It Works</h2>
            <div class="ftt-steps">
                <div class="ftt-step">
                    <div class="ftt-step-number">1</div>
                    <h3>Create Your Account</h3>
                    <p>Sign up in 2 minutes. Add your kids and their activities. Free to start.</p>
                </div>
                <div class="ftt-step">
                    <div class="ftt-step-number">2</div>
                    <h3>Add Events & Travel</h3>
                    <p>Enter camp dates, college visits, competitions—whatever's on the calendar. Include flight details if needed.</p>
                </div>
                <div class="ftt-step">
                    <div class="ftt-step-number">3</div>
                    <h3>Invite Family</h3>
                    <p>Share access with co-parents, grandparents, or anyone who needs to know. They get their own login.</p>
                </div>
                <div class="ftt-step">
                    <div class="ftt-step-number">4</div>
                    <h3>Stay Informed</h3>
                    <p>Get daily digests, price alerts, and calendar sync. Everyone knows where kids are and when.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Use Cases -->
    <section class="ftt-use-cases">
        <div class="ftt-container">
            <h2 class="ftt-section-title">Perfect For...</h2>
            <div class="ftt-use-case-grid">
                <div class="ftt-use-case">
                    <h3>🎭 Performing Arts Families</h3>
                    <p>Band, orchestra, drum corps, theater—track rehearsals, performances, and travel schedules for multiple competitions and tours.</p>
                </div>
                <div class="ftt-use-case">
                    <h3>⚽ Sports Families</h3>
                    <p>Tournament travel, team practices, sports camp sessions. Keep everyone on the same page about who's going where.</p>
                </div>
                <div class="ftt-use-case">
                    <h3>🎓 College-Bound Families</h3>
                    <p>Campus visits, auditions, orientation sessions, move-in dates. Track everything as your student prepares for college.</p>
                </div>
                <div class="ftt-use-case">
                    <h3>👨‍👩‍👧 Co-Parents</h3>
                    <p>Divorced or separated? Share custody schedules, coordinate pickups, and keep both households informed without constant texts.</p>
                </div>
                <div class="ftt-use-case">
                    <h3>☀️ Summer Camp Planners</h3>
                    <p>Multiple kids at different camps? Track start dates, end dates, visitor days, and pickup times all in one place.</p>
                </div>
                <div class="ftt-use-case">
                    <h3>✈️ Frequent Family Travelers</h3>
                    <p>Family vacations, extended family visits, international travel. Keep all the details organized and accessible.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="ftt-testimonials">
        <div class="ftt-container">
            <h2 class="ftt-section-title">What Families Are Saying</h2>
            <div class="ftt-testimonial-grid">
                <div class="ftt-testimonial">
                    <p class="ftt-testimonial-text">"As a divorced mom with two kids in different activities, this is a lifesaver. My ex actually uses it too, which means no more 'I didn't know about that' excuses!"</p>
                    <p class="ftt-testimonial-author">— Sarah M., Parent of 2</p>
                </div>
                <div class="ftt-testimonial">
                    <p class="ftt-testimonial-text">"The flight price tracking alone has saved us over $800 this summer. We get alerts when prices drop and can book at the perfect time."</p>
                    <p class="ftt-testimonial-author">— James K., Parent of 3</p>
                </div>
                <div class="ftt-testimonial">
                    <p class="ftt-testimonial-text">"My parents wanted to know when their grandkids had performances. I gave them access and now they can check anytime without me playing secretary."</p>
                    <p class="ftt-testimonial-author">— Michelle R., Parent of 1</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section class="ftt-pricing">
        <div class="ftt-container">
            <h2 class="ftt-section-title">Simple, Transparent Pricing</h2>
            <p class="ftt-section-subtitle">Start free. Upgrade when you're ready. Cancel anytime.</p>
            
            <div class="ftt-pricing-grid">
                <div class="ftt-pricing-card">
                    <h3>Free</h3>
                    <div class="ftt-price">$0<span>/month</span></div>
                    <ul class="ftt-pricing-features">
                        <li>✓ Up to 2 children</li>
                        <li>✓ Unlimited events</li>
                        <li>✓ Calendar sync</li>
                        <li>✓ 1 parent account</li>
                        <li>✓ Basic travel tracking</li>
                    </ul>
                    <a href="<?php echo esc_url($register_url); ?>" class="ftt-btn ftt-btn-secondary">Start Free</a>
                </div>

                <div class="ftt-pricing-card ftt-pricing-featured">
                    <div class="ftt-badge">Most Popular</div>
                    <h3>Family Plan</h3>
                    <div class="ftt-price">$9.99<span>/month</span></div>
                    <ul class="ftt-pricing-features">
                        <li>✓ <strong>Unlimited children</strong></li>
                        <li>✓ Unlimited events</li>
                        <li>✓ Calendar sync</li>
                        <li>✓ <strong>Multiple parent accounts</strong></li>
                        <li>✓ <strong>Flight price tracking</strong></li>
                        <li>✓ <strong>Daily email digests</strong></li>
                        <li>✓ Priority support</li>
                    </ul>
                    <a href="<?php echo esc_url($register_url); ?>" class="ftt-btn ftt-btn-primary">Get Started</a>
                </div>

                <div class="ftt-pricing-card">
                    <h3>Organization</h3>
                    <div class="ftt-price">Custom</div>
                    <ul class="ftt-pricing-features">
                        <li>✓ All Family Plan features</li>
                        <li>✓ Multiple families</li>
                        <li>✓ Bulk user management</li>
                        <li>✓ Custom branding</li>
                        <li>✓ Dedicated support</li>
                        <li>✓ Team or organization licenses</li>
                    </ul>
                    <a href="mailto:support@familytraveltracker.app" class="ftt-btn ftt-btn-secondary">Contact Us</a>
                </div>
            </div>
            
            <p class="ftt-pricing-note">All plans include 14-day money-back guarantee • No contracts • Cancel anytime</p>
        </div>
    </section>

    <!-- FAQ -->
    <section class="ftt-faq">
        <div class="ftt-container">
            <h2 class="ftt-section-title">Frequently Asked Questions</h2>
            <div class="ftt-faq-grid">
                <div class="ftt-faq-item">
                    <h3>Do I need to download an app?</h3>
                    <p>No! Family Travel Tracker works in any web browser on your phone, tablet, or computer. Just log in and go.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>Can I try it before paying?</h3>
                    <p>Absolutely! The free plan gives you full access for up to 2 children. You can upgrade to unlimited children anytime.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>How does flight price tracking work?</h3>
                    <p>Enter your desired route and dates, and we'll check prices multiple times per day. When prices drop, you'll get an email alert.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>Is my family's data secure?</h3>
                    <p>Yes. We use bank-level encryption, secure servers, and never share your data with third parties. Your privacy is our priority.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>Can grandparents or other family members access it?</h3>
                    <p>Yes! You can invite anyone to view your calendar. Each person gets their own login with the appropriate access level.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>What if I have questions or need help?</h3>
                    <p>We offer email support for all users, and Family Plan subscribers get priority support. We're here to help!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="ftt-final-cta">
        <div class="ftt-container">
            <h2>Ready to Bring Order to Your Family's Travel Chaos?</h2>
            <p>Join hundreds of families who've simplified their schedules and saved money on travel.</p>
            <div class="ftt-cta-buttons">
                <a href="<?php echo esc_url($register_url); ?>" class="ftt-btn ftt-btn-primary ftt-btn-large">
                    Start Free Today
                </a>
            </div>
            <p class="ftt-cta-note">No credit card required • Set up in minutes</p>
        </div>
    </section>
</div>

<style>
/* Homepage Styles */
.ftt-homepage {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.6;
    color: #333333;
}

.ftt-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Hero Section */
.ftt-hero {
    background: linear-gradient(135deg, #6A3E8E 0%, #5B347A 100%);
    color: white;
    padding: 80px 20px;
    text-align: center;
}

.ftt-hero-content {
    max-width: 800px;
    margin: 0 auto;
}

.ftt-hero-title {
    font-size: 48px;
    font-weight: 700;
    margin: 0 0 15px 0;
    line-height: 1.2;
}

.ftt-hero-subtitle {
    color: #FFD700;
    display: block;
    margin-top: 10px;
}

.ftt-hero-description {
    font-size: 20px;
    margin: 20px 0 35px;
    opacity: 0.95;
    line-height: 1.7;
}

.ftt-hero-cta {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.ftt-hero-note {
    font-size: 14px;
    opacity: 0.9;
    margin: 0;
}

/* Buttons */
.ftt-btn {
    display: inline-block;
    padding: 14px 32px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.ftt-btn-large {
    padding: 18px 40px;
    font-size: 18px;
}

.ftt-btn-primary {
    background: #F05A5A;
    color: white !important;
}

.ftt-btn-primary:hover {
    background: #E84E4E;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(240, 90, 90, 0.3);
}

.ftt-btn-secondary {
    background: white;
    color: #6A3E8E !important;
    border: 2px solid white;
}

.ftt-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
}

/* Section Titles */
.ftt-section-title {
    font-size: 38px;
    font-weight: 700;
    text-align: center;
    margin: 0 0 15px 0;
    color: #6A3E8E;
}

.ftt-section-subtitle {
    font-size: 18px;
    text-align: center;
    color: #666;
    margin: 0 auto 50px;
    max-width: 700px;
}

/* Problem Section */
.ftt-problem {
    padding: 80px 20px;
    background: #F8F5FB;
}

.ftt-problem-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-top: 50px;
}

.ftt-problem-card {
    background: white;
    padding: 30px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.ftt-problem-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.ftt-problem-card h3 {
    font-size: 22px;
    margin: 0 0 12px 0;
    color: #6A3E8E;
}

.ftt-problem-card p {
    color: #666;
    margin: 0;
}

/* Solution/Features Section */
.ftt-solution {
    padding: 80px 20px;
    background: white;
}

.ftt-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 40px;
    margin-top: 50px;
}

.ftt-feature-card {
    background: #F8F5FB;
    padding: 35px;
    border-radius: 8px;
    border: 2px solid #E9E3F2;
    transition: all 0.3s;
}

.ftt-feature-card:hover {
    border-color: #6A3E8E;
    box-shadow: 0 4px 12px rgba(106, 62, 142, 0.15);
    transform: translateY(-4px);
}

.ftt-feature-icon {
    font-size: 42px;
    margin-bottom: 15px;
}

.ftt-feature-card h3 {
    font-size: 24px;
    margin: 0 0 12px 0;
    color: #6A3E8E;
}

.ftt-feature-card > p {
    color: #666;
    margin-bottom: 20px;
}

.ftt-feature-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ftt-feature-list li {
    padding: 8px 0 8px 24px;
    position: relative;
    color: #555;
}

.ftt-feature-list li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #F05A5A;
    font-weight: bold;
}

/* How It Works */
.ftt-how-it-works {
    padding: 80px 20px;
    background: white;
}

.ftt-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
    margin-top: 50px;
}

.ftt-step {
    text-align: center;
}

.ftt-step-number {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #6A3E8E, #5B347A);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: bold;
    margin: 0 auto 20px;
}

.ftt-step h3 {
    font-size: 22px;
    margin: 0 0 12px 0;
    color: #6A3E8E;
}

.ftt-step p {
    color: #666;
    margin: 0;
}

/* Use Cases */
.ftt-use-cases {
    padding: 80px 20px;
    background: #F8F5FB;
}

.ftt-use-case-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 50px;
}

.ftt-use-case {
    background: white;
    padding: 30px;
    border-radius: 8px;
    border-left: 4px solid #F05A5A;
}

.ftt-use-case h3 {
    font-size: 20px;
    margin: 0 0 10px 0;
    color: #6A3E8E;
}

.ftt-use-case p {
    color: #666;
    margin: 0;
}

/* Testimonials */
.ftt-testimonials {
    padding: 80px 20px;
    background: white;
}

.ftt-testimonial-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 50px;
}

.ftt-testimonial {
    background: #F8F5FB;
    padding: 30px;
    border-radius: 8px;
    border-top: 4px solid #6A3E8E;
}

.ftt-testimonial-text {
    font-size: 16px;
    font-style: italic;
    color: #555;
    margin: 0 0 15px 0;
    line-height: 1.7;
}

.ftt-testimonial-author {
    font-size: 14px;
    font-weight: 600;
    color: #6A3E8E;
    margin: 0;
}

/* Pricing */
.ftt-pricing {
    padding: 80px 20px;
    background: #F8F5FB;
}

.ftt-pricing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 50px;
    max-width: 1000px;
    margin-left: auto;
    margin-right: auto;
}

.ftt-pricing-card {
    background: white;
    padding: 40px 30px;
    border-radius: 8px;
    border: 2px solid #E9E3F2;
    text-align: center;
    position: relative;
    transition: all 0.3s;
}

.ftt-pricing-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 8px 20px rgba(106, 62, 142, 0.15);
}

.ftt-pricing-featured {
    border-color: #6A3E8E;
    border-width: 3px;
    transform: scale(1.05);
}

.ftt-pricing-featured:hover {
    transform: scale(1.05) translateY(-8px);
}

.ftt-badge {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: #F05A5A;
    color: white;
    padding: 5px 20px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.ftt-pricing-card h3 {
    font-size: 24px;
    margin: 0 0 15px 0;
    color: #6A3E8E;
}

.ftt-price {
    font-size: 48px;
    font-weight: 700;
    color: #6A3E8E;
    margin: 0 0 25px 0;
}

.ftt-price span {
    font-size: 18px;
    font-weight: 400;
    color: #666;
}

.ftt-pricing-features {
    list-style: none;
    padding: 0;
    margin: 0 0 30px 0;
    text-align: left;
}

.ftt-pricing-features li {
    padding: 10px 0;
    color: #555;
    border-bottom: 1px solid #E9E3F2;
}

.ftt-pricing-features li:last-child {
    border-bottom: none;
}

.ftt-pricing-note {
    text-align: center;
    margin-top: 40px;
    color: #666;
    font-size: 14px;
}

/* FAQ */
.ftt-faq {
    padding: 80px 20px;
    background: white;
}

.ftt-faq-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 40px;
    margin-top: 50px;
}

.ftt-faq-item h3 {
    font-size: 20px;
    margin: 0 0 10px 0;
    color: #6A3E8E;
}

.ftt-faq-item p {
    color: #666;
    margin: 0;
}

/* Final CTA */
.ftt-final-cta {
    padding: 80px 20px;
    background: linear-gradient(135deg, #6A3E8E 0%, #5B347A 100%);
    color: white;
    text-align: center;
}

.ftt-final-cta h2 {
    font-size: 38px;
    margin: 0 0 15px 0;
    color: white;
}

.ftt-final-cta p {
    font-size: 20px;
    margin: 0 0 35px 0;
    opacity: 0.95;
}

.ftt-cta-buttons {
    margin-bottom: 20px;
}

.ftt-cta-note {
    font-size: 14px;
    opacity: 0.9;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .ftt-hero-title {
        font-size: 32px;
    }
    
    .ftt-hero-description {
        font-size: 16px;
    }
    
    .ftt-section-title {
        font-size: 28px;
    }
    
    .ftt-features-grid,
    .ftt-use-case-grid,
    .ftt-faq-grid {
        grid-template-columns: 1fr;
    }
    
    .ftt-pricing-grid {
        grid-template-columns: 1fr;
    }
    
    .ftt-pricing-featured {
        transform: scale(1);
    }
    
    .ftt-pricing-featured:hover {
        transform: translateY(-8px);
    }
}
</style>
