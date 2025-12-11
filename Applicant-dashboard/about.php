<?php
// Page-specific variables
$page_title = 'About Us';
$current_page = 'about';

// Include Header
require_once __DIR__ . '/applicant_header.php';

// Include Sidebar
require_once __DIR__ . '/applicant_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle" style="color: var(--accent-color);"></i>
                    About OnlineBizPermit
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Learn more about our mission and services
                </p>
            </div>
        </div>
    </header>

    <div class="about-container">
        <div class="about-section">
            <h2>Our Mission</h2>
            <p>Our mission is to streamline and simplify the business permit application process for entrepreneurs and local government units. We believe that by leveraging technology, we can make public service more efficient, transparent, and accessible for everyone.</p>
            <p>OnlineBizPermit provides a centralized platform for applicants to submit their requirements, track their application status, and receive their permits, all from the comfort of their home or office.</p>
        </div>

        <div class="about-section">
            <h2>Our Vision</h2>
            <p>We envision a future where starting and managing a business is a seamless experience, free from bureaucratic hurdles. We aim to be the leading digital solution for business licensing across the country, fostering a more vibrant and dynamic business environment.</p>
        </div>

        <div class="about-section contact-us">
            <h2>Contact Us</h2>
            <p>Have questions or need assistance? Our team is ready to help. You can reach us through the following channels:</p>
            <div class="contact-details">
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <strong>Phone</strong>
                        <span>(02) 1234-5678</span>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <strong>Email</strong>
                        <span>@OnlineBiz Permit Support</span>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <strong>Office</strong>
                        <span>Municipal of SanMiguel, Business Permit Office</span>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>Hours</strong>
                        <span>Monday-Friday, 8AM-5PM</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.about-container {
    max-width: 900px;
    margin: auto;
    background: #fff;
    padding: 30px 40px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.about-section {
    margin-bottom: 40px;
}

.about-section:last-child {
    margin-bottom: 0;
}

.about-section h2 {
    font-size: 1.5rem;
    color: #232a3b;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f3f9;
}

.about-section p {
    font-size: 1rem;
    color: #5a6a7b;
    line-height: 1.7;
    margin-bottom: 15px;
}

.contact-us h2 {
    color: #4a69bd;
    border-bottom-color: #e3f2fd;
}

.contact-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.contact-item { display: flex; align-items: center; gap: 15px; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef; }
.contact-item i { font-size: 1.5rem; color: #4a69bd; width: 40px; text-align: center; }
.contact-item div { display: flex; flex-direction: column; }
.contact-item strong { font-weight: 600; color: #343a40; font-size: 0.9rem; }
.contact-item span { color: #5a6a7b; }
</style>

<?php
// Include Footer
require_once __DIR__ . '/applicant_footer.php';
?>