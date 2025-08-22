<?php
/**
 * Landing Page Template for Primates Shoppers
 * Dynamic and SEO-optimized landing page promoting product search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ps-landing-page">
    <!-- Hero Section -->
    <section class="ps-hero-section">
        <div class="ps-hero-content">
            <h1 class="ps-hero-title">
                Shop Smart, Share Rewards, Support Causes
            </h1>
            <p class="ps-hero-subtitle">
                The first platform that democratizes shopping rewards - every purchase you make generates revenue that's shared with you and donated to charities you care about.
            </p>
            <div class="ps-hero-cta">
                <a href="#search-products" class="ps-cta-button ps-cta-primary" id="ps-hero-cta">
                    Start Shopping & Earning
                </a>
                <p class="ps-hero-subtext">
                    Search millions of products from Amazon, eBay & Walmart
                </p>
            </div>
        </div>
        <div class="ps-hero-visual">
            <div class="ps-platform-logos">
                <div class="ps-platform-logo">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjMwIiB2aWV3Qm94PSIwIDAgMTAwIDMwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMjAgMTVMMjUgMjBIMzVMMzAgMTVIMjVMMjAgMTBIMTVMMjAgMTVaIiBmaWxsPSIjRkY5OTAwIi8+Cjwvc3ZnPgo=" alt="Amazon" class="ps-logo-amazon">
                </div>
                <div class="ps-platform-logo">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjMwIiB2aWV3Qm94PSIwIDAgMTAwIDMwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMjAgMTVMMjUgMjBIMzVMMzAgMTVIMjVMMjAgMTBIMTVMMjAgMTVaIiBmaWxsPSIjMDA0Q0Q3Ii8+Cjwvc3ZnPgo=" alt="eBay" class="ps-logo-ebay">
                </div>
                <div class="ps-platform-logo">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjMwIiB2aWV3Qm94PSIwIDAgMTAwIDMwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMjAgMTVMMjUgMjBIMzVMMzAgMTVIMjVMMjAgMTBIMTVMMjAgMTVaIiBmaWxsPSIjMDA0Njg1Ii8+Cjwvc3ZnPgo=" alt="Walmart" class="ps-logo-walmart">
                </div>
            </div>
        </div>
    </section>

    <!-- Value Proposition Section -->
    <section class="ps-value-section">
        <div class="ps-container">
            <h2 class="ps-section-title">Revolutionary Revenue Sharing</h2>
            <div class="ps-value-grid">
                <div class="ps-value-card">
                    <div class="ps-value-icon">💰</div>
                    <h3>Earn From Every Search</h3>
                    <p>Get a share of advertising revenue from every product search you make. Your shopping activity generates real income.</p>
                </div>
                <div class="ps-value-card">
                    <div class="ps-value-icon">🤝</div>
                    <h3>Support Charities</h3>
                    <p>Choose which charities receive a portion of the revenue. Make a positive impact while you shop.</p>
                </div>
                <div class="ps-value-card">
                    <div class="ps-value-icon">🔍</div>
                    <h3>Smart Product Search</h3>
                    <p>Search across Amazon, eBay, and Walmart simultaneously. Find the best deals with advanced filtering and sorting.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Search Section -->
    <section class="ps-search-section" id="search-products">
        <div class="ps-container">
            <h2 class="ps-section-title">Start Your Rewarding Shopping Journey</h2>
            <p class="ps-section-subtitle">Search millions of products and start earning rewards instantly</p>
            
            <!-- This will be replaced with the actual search form -->
            <div class="ps-search-form-container">
                <?php echo do_shortcode('[primates_shoppers]'); ?>
            </div>

            <!-- Features below search -->
            <div class="ps-search-features">
                <div class="ps-feature">
                    <span class="ps-feature-icon">⚡</span>
                    <span>Real-time price comparison</span>
                </div>
                <div class="ps-feature">
                    <span class="ps-feature-icon">🎯</span>
                    <span>Advanced filtering options</span>
                </div>
                <div class="ps-feature">
                    <span class="ps-feature-icon">📊</span>
                    <span>Price per unit sorting</span>
                </div>
                <div class="ps-feature">
                    <span class="ps-feature-icon">🏷️</span>
                    <span>Best deal detection</span>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="ps-how-it-works">
        <div class="ps-container">
            <h2 class="ps-section-title">How Revenue Sharing Works</h2>
            <div class="ps-steps">
                <div class="ps-step">
                    <div class="ps-step-number">1</div>
                    <div class="ps-step-content">
                        <h3>Search Products</h3>
                        <p>Use our advanced search to find products across multiple platforms</p>
                    </div>
                </div>
                <div class="ps-step">
                    <div class="ps-step-number">2</div>
                    <div class="ps-step-content">
                        <h3>Generate Revenue</h3>
                        <p>Your searches and clicks generate advertising and affiliate revenue</p>
                    </div>
                </div>
                <div class="ps-step">
                    <div class="ps-step-number">3</div>
                    <div class="ps-step-content">
                        <h3>Share Rewards</h3>
                        <p>Revenue is democratically shared between you and your chosen charities</p>
                    </div>
                </div>
                <div class="ps-step">
                    <div class="ps-step-number">4</div>
                    <div class="ps-step-content">
                        <h3>Make Impact</h3>
                        <p>Track your earnings and charitable contributions in your dashboard</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="ps-stats-section">
        <div class="ps-container">
            <div class="ps-stats-grid">
                <div class="ps-stat">
                    <div class="ps-stat-number" data-target="1000000">0</div>
                    <div class="ps-stat-label">Products Searchable</div>
                </div>
                <div class="ps-stat">
                    <div class="ps-stat-number" data-target="3">3</div>
                    <div class="ps-stat-label">Major Platforms</div>
                </div>
                <div class="ps-stat">
                    <div class="ps-stat-number" data-target="50">0</div>
                    <div class="ps-stat-label">% Revenue Shared</div>
                </div>
                <div class="ps-stat">
                    <div class="ps-stat-number" data-target="100">0</div>
                    <div class="ps-stat-label">Charities Supported</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="ps-testimonials">
        <div class="ps-container">
            <h2 class="ps-section-title">What Our Users Say</h2>
            <div class="ps-testimonials-grid">
                <div class="ps-testimonial">
                    <div class="ps-testimonial-content">
                        "Finally, a platform that shares the wealth! I've earned over $200 while supporting my favorite environmental charity."
                    </div>
                    <div class="ps-testimonial-author">
                        <strong>Sarah M.</strong> - Active Shopper
                    </div>
                </div>
                <div class="ps-testimonial">
                    <div class="ps-testimonial-content">
                        "The search functionality is amazing - I can compare prices across all major platforms in one place and earn money doing it."
                    </div>
                    <div class="ps-testimonial-author">
                        <strong>Mike R.</strong> - Deal Hunter
                    </div>
                </div>
                <div class="ps-testimonial">
                    <div class="ps-testimonial-content">
                        "Love that I can contribute to charity just by shopping. It's a win-win-win situation for everyone involved."
                    </div>
                    <div class="ps-testimonial-author">
                        <strong>Lisa K.</strong> - Charity Supporter
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Revenue Sharing Explanation Section -->
    <section class="ps-revenue-explanation">
        <div class="ps-container">
            <h2 class="ps-section-title">How Our Revenue Sharing Works</h2>
            <div class="ps-explanation-content">
                <p class="ps-explanation-text">
                    At no extra cost to you
                    <span class="ps-info-popup">
                        <span class="ps-info-icon">i</span>
                        <div class="ps-popup-content">
                            Most large online retailers pay commissions to sites like this one for directing sales to them. But instead of pocketing the money, we will donate 80% of it to organizations that our users choose to support.<br><br>
                            We would actually love to give 80% back to our users, but most affiliate contracts forbid doing so. Booo! So the next best thing is to support worthy causes, as decided by our users.
                        </div>
                    </span>
                    , your shopping activity generates revenue that we share with charities you choose to support.
                </p>
                <p class="ps-explanation-text">
                    Instead of making the rich richer
                    <span class="ps-info-popup">
                        <span class="ps-info-icon">i</span>
                        <div class="ps-popup-content">
                            And that's just the beginning. As our community grows, we will soon be able to charge for advertising on this site, also. And then 80% of that revenue WILL go directly to our users who create content that attracts the advertising.
                        </div>
                    </span>
                    , we're democratizing the wealth by sharing it with causes that matter to our community.
                </p>
            </div>
        </div>
    </section>

    <!-- Final CTA Section -->
    <section class="ps-final-cta">
        <div class="ps-container">
            <h2>Ready to Start Earning While Shopping?</h2>
            <p>Join thousands of users who are already earning rewards and supporting causes they care about.</p>
            <a href="#search-products" class="ps-cta-button ps-cta-secondary" id="ps-final-cta">
                Begin Your Shopping Journey
            </a>
        </div>
    </section>
</div>

<style>
/* Landing Page Styles */
.ps-landing-page {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    line-height: 1.6;
    color: #333;
}

.ps-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Hero Section */
.ps-hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.ps-hero-title {
    font-size: 3.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.2;
}

.ps-hero-subtitle {
    font-size: 1.3rem;
    margin-bottom: 2rem;
    opacity: 0.95;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.ps-cta-button {
    display: inline-block;
    padding: 16px 32px;
    font-size: 1.1rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
}

.ps-cta-primary {
    background: #ff6b6b;
    color: white;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
}

.ps-cta-primary:hover {
    background: #ff5252;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 107, 107, 0.6);
    color: white;
    text-decoration: none;
}

.ps-cta-secondary {
    background: white;
    color: #667eea;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.ps-cta-secondary:hover {
    background: #f8f9ff;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    color: #667eea;
    text-decoration: none;
}

.ps-hero-subtext {
    margin-top: 1rem;
    font-size: 0.95rem;
    opacity: 0.8;
}

.ps-platform-logos {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-top: 3rem;
}

.ps-platform-logo img {
    height: 120px;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.ps-platform-logo:hover img {
    opacity: 1;
}

/* Value Section */
.ps-value-section {
    padding: 80px 20px;
    background: #f8f9ff;
}

.ps-section-title {
    text-align: center;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 3rem;
    color: #2c3e50;
}

.ps-value-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
}

.ps-value-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.ps-value-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.ps-value-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.ps-value-card h3 {
    font-size: 1.3rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #2c3e50;
}

/* Search Section */
.ps-search-section {
    padding: 80px 20px;
    background: white;
}

.ps-section-subtitle {
    text-align: center;
    font-size: 1.2rem;
    color: #666;
    margin-bottom: 3rem;
}

.ps-search-form-container {
    max-width: 800px;
    margin: 0 auto 3rem;
}

.ps-search-features {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 2rem;
    margin-top: 2rem;
}

.ps-feature {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    color: #555;
}

.ps-feature-icon {
    font-size: 1.2rem;
}

/* Info Popup Styles */
.ps-info-popup {
    position: relative;
    display: inline-block;
    cursor: help;
}

.ps-info-popup .ps-info-icon {
    display: inline-block;
    width: 16px;
    height: 16px;
    background: #667eea;
    color: white;
    border-radius: 50%;
    text-align: center;
    line-height: 16px;
    font-size: 10px;
    font-weight: bold;
    margin-left: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.ps-info-popup .ps-info-icon:hover {
    background: #5a6fd8;
}

.ps-info-popup .ps-popup-content {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #2c3e50;
    color: white;
    padding: 15px;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.4;
    max-width: 300px;
    width: max-content;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 1000;
    margin-bottom: 10px;
}

.ps-info-popup .ps-popup-content::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 8px solid transparent;
    border-top-color: #2c3e50;
}

.ps-info-popup:hover .ps-popup-content {
    opacity: 1;
    visibility: visible;
}

/* Revenue Explanation Section */
.ps-revenue-explanation {
    padding: 80px 20px;
    background: #f8f9ff;
}

.ps-explanation-content {
    max-width: 800px;
    margin: 0 auto;
}

.ps-explanation-text {
    font-size: 1.1rem;
    line-height: 1.7;
    margin-bottom: 1.5rem;
    color: #2c3e50;
    text-align: center;
}

/* How It Works */
.ps-how-it-works {
    padding: 80px 20px;
    background: #f8f9ff;
}

.ps-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
}

.ps-step {
    text-align: center;
}

.ps-step-number {
    width: 60px;
    height: 60px;
    background: #667eea;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 auto 1rem;
}

.ps-step h3 {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #2c3e50;
}

/* Statistics */
.ps-stats-section {
    padding: 60px 20px;
    background: #2c3e50;
    color: white;
}

.ps-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
}

.ps-stat {
    text-align: center;
}

.ps-stat-number {
    font-size: 3rem;
    font-weight: 700;
    color: #ff6b6b;
    display: block;
}

.ps-stat-label {
    font-size: 1rem;
    margin-top: 0.5rem;
    opacity: 0.9;
}

/* Testimonials */
.ps-testimonials {
    padding: 80px 20px;
    background: white;
}

.ps-testimonials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
}

.ps-testimonial {
    background: #f8f9ff;
    padding: 2rem;
    border-radius: 12px;
    border-left: 4px solid #667eea;
}

.ps-testimonial-content {
    font-style: italic;
    margin-bottom: 1rem;
    font-size: 1.1rem;
    line-height: 1.6;
}

.ps-testimonial-author {
    color: #666;
}

/* Final CTA */
.ps-final-cta {
    padding: 80px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
}

.ps-final-cta h2 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.ps-final-cta p {
    font-size: 1.2rem;
    margin-bottom: 2rem;
    opacity: 0.95;
}

/* Responsive Design */
@media (max-width: 768px) {
    .ps-hero-title {
        font-size: 2.5rem;
    }
    
    .ps-hero-subtitle {
        font-size: 1.1rem;
    }
    
    .ps-section-title {
        font-size: 2rem;
    }
    
    .ps-platform-logos {
        gap: 1rem;
    }
    
    .ps-platform-logo img {
        height: 30px;
    }
    
    .ps-search-features {
        flex-direction: column;
        align-items: center;
    }
    
    .ps-final-cta h2 {
        font-size: 2rem;
    }
}

/* Smooth scrolling for anchor links */
html {
    scroll-behavior: smooth;
}

/* Animation for statistics */
@keyframes countUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.ps-stat-number {
    animation: countUp 0.8s ease-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Landing page loaded');
    
    // Smooth scrolling for CTA buttons
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Animate statistics when they come into view
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const statNumbers = entry.target.querySelectorAll('.ps-stat-number');
                statNumbers.forEach(statNumber => {
                    const target = parseInt(statNumber.getAttribute('data-target'));
                    animateCounter(statNumber, target);
                });
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const statsSection = document.querySelector('.ps-stats-section');
    if (statsSection) {
        observer.observe(statsSection);
    }

    function animateCounter(element, target) {
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 40);
    }

    // Track CTA clicks for analytics
    document.querySelectorAll('.ps-cta-button').forEach(button => {
        button.addEventListener('click', function() {
            const buttonId = this.id || 'unknown';
            console.log('CTA clicked:', buttonId);
            
            // If using Google Analytics, track the event
            if (typeof gtag !== 'undefined') {
                gtag('event', 'click', {
                    event_category: 'CTA',
                    event_label: buttonId,
                    value: 1
                });
            }
            
            // If using Facebook Pixel, track the event
            if (typeof fbq !== 'undefined') {
                fbq('track', 'Lead', {
                    content_name: 'Landing Page CTA',
                    content_category: 'Shopping'
                });
            }
        });
    });

    // Track scroll depth for engagement analytics
    let maxScroll = 0;
    window.addEventListener('scroll', function() {
        const scrollPercent = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);
        if (scrollPercent > maxScroll) {
            maxScroll = scrollPercent;
            
            // Track 25%, 50%, 75%, 100% milestones
            if (maxScroll >= 25 && maxScroll < 50 && !window.ps_tracked_25) {
                window.ps_tracked_25 = true;
                console.log('25% scroll depth reached');
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'scroll', { event_category: 'Engagement', event_label: '25%' });
                }
            } else if (maxScroll >= 50 && maxScroll < 75 && !window.ps_tracked_50) {
                window.ps_tracked_50 = true;
                console.log('50% scroll depth reached');
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'scroll', { event_category: 'Engagement', event_label: '50%' });
                }
            } else if (maxScroll >= 75 && maxScroll < 100 && !window.ps_tracked_75) {
                window.ps_tracked_75 = true;
                console.log('75% scroll depth reached');
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'scroll', { event_category: 'Engagement', event_label: '75%' });
                }
            } else if (maxScroll >= 100 && !window.ps_tracked_100) {
                window.ps_tracked_100 = true;
                console.log('100% scroll depth reached');
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'scroll', { event_category: 'Engagement', event_label: '100%' });
                }
            }
        }
    });
});
</script> 