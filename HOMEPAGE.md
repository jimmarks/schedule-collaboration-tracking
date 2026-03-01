# Family Travel Tracker - Homepage HTML

## Instructions

Copy everything between the `<!-- START HOMEPAGE -->` and `<!-- END HOMEPAGE -->` comments below and paste it directly into a WordPress page using the HTML editor (not the visual editor).

For best results:
1. Create a new page in WordPress
2. Switch to "Code Editor" or "HTML" view
3. Paste this entire HTML block
4. Set as your homepage in Settings → Reading

---

<!-- START HOMEPAGE -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Family Travel Tracker Homepage Styles */
        .ftt-homepage {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            max-width: 100%;
            margin: 0;
            padding: 0;
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
            padding: 18px 40px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
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
        
        /* Features Section */
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
        
        /* Pricing */
        .ftt-pricing {
            padding: 80px 20px;
            background: #F8F5FB;
        }
        
        .ftt-pricing-intro {
            text-align: center;
            max-width: 800px;
            margin: 0 auto 50px;
        }
        
        .ftt-pricing-model {
            background: white;
            padding: 40px;
            border-radius: 8px;
            border: 3px solid #6A3E8E;
            max-width: 600px;
            margin: 0 auto 50px;
            text-align: center;
        }
        
        .ftt-pricing-model h3 {
            font-size: 28px;
            color: #6A3E8E;
            margin: 0 0 20px 0;
        }
        
        .ftt-base-price {
            font-size: 48px;
            font-weight: 700;
            color: #6A3E8E;
            margin: 20px 0;
        }
        
        .ftt-base-price span {
            font-size: 18px;
            color: #666;
            font-weight: 400;
        }
        
        .ftt-addon-price {
            font-size: 20px;
            color: #333;
            margin: 15px 0;
        }
        
        .ftt-addon-price strong {
            color: #F05A5A;
            font-size: 24px;
        }
        
        .ftt-pricing-table {
            max-width: 700px;
            margin: 0 auto 40px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .ftt-pricing-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .ftt-pricing-table th {
            background: #6A3E8E;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .ftt-pricing-table td {
            padding: 15px;
            border-bottom: 1px solid #E9E3F2;
        }
        
        .ftt-pricing-table tr:last-child td {
            border-bottom: none;
        }
        
        .ftt-pricing-table tr:hover {
            background: #F8F5FB;
        }
        
        .ftt-pricing-highlight {
            font-weight: 600;
            color: #6A3E8E;
        }
        
        .ftt-pricing-features {
            max-width: 600px;
            margin: 0 auto;
            text-align: left;
        }
        
        .ftt-pricing-features h4 {
            font-size: 20px;
            color: #6A3E8E;
            margin: 30px 0 15px 0;
            text-align: center;
        }
        
        .ftt-pricing-features ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ftt-pricing-features li {
            padding: 10px 0 10px 30px;
            position: relative;
            color: #555;
        }
        
        .ftt-pricing-features li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #F05A5A;
            font-weight: bold;
            font-size: 18px;
        }
        
        .ftt-trial-banner {
            background: linear-gradient(135deg, #6A3E8E, #5B347A);
            color: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            margin-top: 40px;
        }
        
        .ftt-trial-banner h4 {
            font-size: 24px;
            margin: 0 0 10px 0;
            color: white;
        }
        
        .ftt-trial-banner p {
            margin: 0 0 20px 0;
            opacity: 0.95;
        }
        
        /* Use Cases */
        .ftt-use-cases {
            padding: 80px 20px;
            background: white;
        }
        
        .ftt-use-case-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .ftt-use-case {
            background: #F8F5FB;
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
            background: #F8F5FB;
        }
        
        .ftt-testimonial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .ftt-testimonial {
            background: white;
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
        
        .ftt-cta-note {
            font-size: 14px;
            opacity: 0.9;
            margin: 20px 0 0 0;
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
            .ftt-faq-grid,
            .ftt-problem-grid {
                grid-template-columns: 1fr;
            }
            
            .ftt-hero-cta {
                flex-direction: column;
                align-items: stretch;
            }
            
            .ftt-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

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
                <a href="/register/" class="ftt-btn ftt-btn-primary">
                    Start 14-Day Free Trial
                </a>
                <a href="/login/" class="ftt-btn ftt-btn-secondary">
                    Sign In
                </a>
            </div>
            <p class="ftt-hero-note">✓ No credit card for trial  ✓ Set up in 2 minutes  ✓ Cancel anytime</p>
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

    <!-- Solution/Features Section -->
    <section class="ftt-solution">
        <div class="ftt-container">
            <h2 class="ftt-section-title">One Platform. Every Trip. Complete Peace of Mind.</h2>
            <p class="ftt-section-subtitle">Family Travel Tracker brings order to the chaos with powerful tools designed specifically for families.</p>
            
            <div class="ftt-features-grid">
                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">🗓️</div>
                    <h3>Color-Coded Family Calendar</h3>
                    <p>See every child's schedule in one place. Each child gets their own color, making it easy to see who's doing what.</p>
                    <ul class="ftt-feature-list">
                        <li>Automatic color assignment per child</li>
                        <li>Filter by child or show all at once</li>
                        <li>14 different event types supported</li>
                        <li>Interactive calendar view (FullCalendar)</li>
                        <li>Timezone-aware scheduling</li>
                    </ul>
                </div>

                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">✈️</div>
                    <h3>Complete Travel Tracking</h3>
                    <p>Every trip detail in one place: flights, hotels, who's picking up, what time they land.</p>
                    <ul class="ftt-feature-list">
                        <li>Multi-leg flight itineraries</li>
                        <li>IATA airport code autocomplete</li>
                        <li>Flight numbers and confirmation codes</li>
                        <li>Booking status tracking</li>
                        <li>Housing/hotel information</li>
                        <li>Travel notes and instructions</li>
                    </ul>
                </div>

                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">💰</div>
                    <h3>Smart Flight Price Alerts</h3>
                    <p>Stop obsessing over flight prices. We'll monitor them for you and alert you when it's time to book.</p>
                    <ul class="ftt-feature-list">
                        <li>Track prices for any route, any date</li>
                        <li>Automatic price monitoring</li>
                        <li>Email alerts when prices drop</li>
                        <li>Compare booking options</li>
                        <li>Link flights directly to events</li>
                    </ul>
                </div>

                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">👨‍👩‍👧‍👦</div>
                    <h3>Multi-Parent Access</h3>
                    <p>Perfect for divorced or separated parents. Everyone stays informed without the friction.</p>
                    <ul class="ftt-feature-list">
                        <li>Up to 4 parents/guardians per child</li>
                        <li>Each person has their own login</li>
                        <li>Grandparents can view schedules too</li>
                        <li>Secure invitation system</li>
                        <li>No more "did you get my text?" moments</li>
                    </ul>
                </div>

                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">📱</div>
                    <h3>Calendar Sync Everywhere</h3>
                    <p>Subscribe to your family calendar in any app. Updates sync automatically.</p>
                    <ul class="ftt-feature-list">
                        <li>iCal/ICS feed for any calendar app</li>
                        <li>Works with Google Calendar</li>
                        <li>Works with Apple Calendar</li>
                        <li>Works with Outlook</li>
                        <li>Real-time updates across all devices</li>
                        <li>Mobile-friendly web interface</li>
                    </ul>
                </div>

                <div class="ftt-feature-card">
                    <div class="ftt-feature-icon">⏰</div>
                    <h3>Detailed Time Blocks</h3>
                    <p>Not just "event at 3pm" — track travel time, meals, practice blocks, performance times, and more.</p>
                    <ul class="ftt-feature-list">
                        <li>7 time block types supported</li>
                        <li>Practice, travel, meal, performance blocks</li>
                        <li>Individual start/end times per block</li>
                        <li>As many blocks as you need per event</li>
                        <li>Perfect for all-day tournaments</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section class="ftt-pricing">
        <div class="ftt-container">
            <h2 class="ftt-section-title">Simple, Transparent Pricing</h2>
            <p class="ftt-section-subtitle">Pay only for what you use. Add or remove children anytime.</p>
            
            <div class="ftt-pricing-intro">
                <p><strong>No complicated tiers. No hidden fees. Just simple per-child pricing.</strong></p>
            </div>
            
            <div class="ftt-pricing-model">
                <h3>How Pricing Works</h3>
                <div class="ftt-base-price">
                    $9.99<span>/month</span>
                </div>
                <p><strong>Includes your first child + up to 4 parents/guardians</strong></p>
                
                <div class="ftt-addon-price">
                    Each additional child: <strong>+$5</strong>/month
                </div>
                
                <p style="margin-top: 20px; color: #666; font-size: 14px;">
                    Or save 17% with annual billing: $99/year base + $50/year per additional child
                </p>
            </div>
            
            <div class="ftt-pricing-table">
                <table>
                    <thead>
                        <tr>
                            <th>Number of Children</th>
                            <th>Monthly Price</th>
                            <th>Yearly Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1 child</td>
                            <td class="ftt-pricing-highlight">$9.99</td>
                            <td>$99.00</td>
                        </tr>
                        <tr>
                            <td>2 children</td>
                            <td class="ftt-pricing-highlight">$14.99</td>
                            <td>$149.00</td>
                        </tr>
                        <tr>
                            <td>3 children</td>
                            <td class="ftt-pricing-highlight">$19.99</td>
                            <td>$199.00</td>
                        </tr>
                        <tr>
                            <td>4 children</td>
                            <td class="ftt-pricing-highlight">$24.99</td>
                            <td>$249.00</td>
                        </tr>
                        <tr>
                            <td>5 children</td>
                            <td class="ftt-pricing-highlight">$29.99</td>
                            <td>$299.00</td>
                        </tr>
                        <tr>
                            <td>6+ children</td>
                            <td class="ftt-pricing-highlight">+$5 each</td>
                            <td>+$50 each</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="ftt-pricing-features">
                <h4>Everything Included at Every Level:</h4>
                <ul>
                    <li>Unlimited events and travel tracking</li>
                    <li>Up to 4 parents/guardians per child</li>
                    <li>Flight price tracking and alerts</li>
                    <li>Color-coded calendar views</li>
                    <li>Calendar sync (iCal/Google/Apple/Outlook)</li>
                    <li>Multi-leg flight itineraries</li>
                    <li>Detailed time blocks for complex schedules</li>
                    <li>Mobile-responsive interface</li>
                    <li>Timezone support</li>
                    <li>Email support</li>
                </ul>
            </div>
            
            <div class="ftt-trial-banner">
                <h4>🎉 Start with a 14-Day Free Trial</h4>
                <p>Try all features risk-free. No credit card required for trial. Cancel anytime.</p>
                <a href="/register/" class="ftt-btn ftt-btn-primary">Start Free Trial</a>
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

    <!-- FAQ -->
    <section class="ftt-faq">
        <div class="ftt-container">
            <h2 class="ftt-section-title">Frequently Asked Questions</h2>
            <div class="ftt-faq-grid">
                <div class="ftt-faq-item">
                    <h3>Do I need to download an app?</h3>
                    <p>No! Family Travel Tracker works in any web browser on your phone, tablet, or computer. Just log in and go. Plus, you can subscribe to your calendar in any calendar app (Google, Apple, Outlook).</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>How does the 14-day trial work?</h3>
                    <p>Sign up and get full access to all features for 14 days—no credit card required during trial. After the trial, choose your plan and billing.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>Can I add or remove children later?</h3>
                    <p>Absolutely! Your pricing adjusts automatically. Add a child for +$5/month or remove one to reduce your monthly cost. No contracts, no hassles.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>How does flight price tracking work?</h3>
                    <p>Enter your desired route and dates when creating a travel event. We'll check prices regularly and email you when prices drop so you can book at the right time.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>Is my family's data secure?</h3>
                    <p>Yes. We use bank-level encryption, secure servers, and never share your data with third parties. Your privacy is our priority.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>Can grandparents or other family members see the calendar?</h3>
                    <p>Yes! You can invite up to 4 parents or guardians per child. Each person gets their own login and can view (and optionally edit) that child's schedule.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>What happens if I forget to cancel my trial?</h3>
                    <p>You won't be charged until after your 14-day trial ends. We'll send you reminder emails at day 7 and day 12 so you have plenty of notice.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>Can I cancel anytime?</h3>
                    <p>Yes! No contracts, no cancellation fees. If you cancel, you'll keep access until the end of your current billing period.</p>
                </div>
                <div class="ftt-faq-item">
                    <h3>Do you offer refunds?</h3>
                    <p>We follow industry-standard refund policies. If you're not satisfied, contact us within 7 days of your first charge and we'll work with you.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="ftt-final-cta">
        <div class="ftt-container">
            <h2>Ready to Bring Order to Your Family's Travel Chaos?</h2>
            <p>Join hundreds of families who've simplified their schedules and saved money on travel.</p>
            <a href="/register/" class="ftt-btn ftt-btn-primary">
               Start Your 14-Day Free Trial
            </a>
            <p class="ftt-cta-note">No credit card required for trial • Set up in minutes • Cancel anytime</p>
        </div>
    </section>
</div>

</body>
</html>

<!-- END HOMEPAGE -->
