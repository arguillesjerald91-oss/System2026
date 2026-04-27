<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TESDA Auto Mechanic Training Centre</title>

<!-- Inline CSS -->
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f8f9fc;
    color: #2d2d2d;
}
/* ================================
   THEME VARIABLES (REQUIRED)
================================ */
:root {
  --background: #f8f9fc;
  --foreground: #2d2d2d;

  --card: #ffffff;
  --card-foreground: #2d2d2d;

  --primary: #2563eb;
  --muted-foreground: #6b7280;

  --radius: 14px;

  --shadow-soft: 0 4px 15px rgba(0,0,0,0.08);
  --shadow-card: 0 8px 25px rgba(0,0,0,0.12);
  --shadow-glow: 0 12px 35px rgba(37, 99, 235, 0.35);
}

/* Header */
header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(6px);
    border-bottom: 1px solid #ddd;
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header .logo {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo-box {
    width: 50px;
    height: 50px;
    border-radius: 50px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.icon-white {
    color: white;
}

header a {
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
}

.nav a{margin:0 15px;color:#555;text-decoration:none}
.nav a:hover{color:#4f46e5;
}

.btn-outline:hover {
    background: #eee;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1e4dcc;
}

/* Hero */

.badge {
    display: inline-flex; 
    align-items: center;
    gap: 6px;
    background: #2563eb22;
    color: #2564ed;
    padding: 6px 15px;
    border-radius: 999px;
    font-size: 14px;
    margin-bottom: -40px; 
}


/* --- BASE HERO CONTAINER ADJUSTMENTS --- */
.hero {
    
    padding: 70px 30px;
}

/* --- FLEX CONTAINER FOR TWO COLUMNS --- */
.hero-content-wrapper {
    display: flex;
    align-items: center; 
    justify-content: space-between; 
    max-width: 1200px; 
    margin: 0 auto;
    gap: 40px; 
}

.hero-text-content {
    flex: 1; 
    text-align: left;
    position: relative; 
    z-index: 20; 
    padding: 20px; 
}

.hero-image-content {
    flex: 1; 
    position: relative; 
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 80px; 
}


.hero-graphic-circle-left {
    position: absolute;
    border-radius: 50%;
    width: 250px;
    height: 250px;
    background-color: #2563eb; 
    opacity: 0.15; 
    left: -80px; 
    top: -50px;
    z-index: 0; 
}

/* --- LEFT BOTTOM BACKGROUND CIRCLE --- */
.hero-graphic-circle-left-bottom {
    position: absolute;
    border-radius: 50%;
    width: 180px;
    height: 180px;
    background-color: #2563eb; 
    opacity: 0.15; 
    left: -50px; 
    bottom: -50px; 
    z-index: 0; 
}

.hero-title {
    font-size: 48px;
    font-weight: bold;
    margin-bottom: 15px;
}

.hero-title span {
    color: #2563eb;
}

.hero p {
  
    max-width: none; 
    margin: 0 0 30px 0; 
    font-size: 17px;
    color: #555;
}

.hero-actions {
    display: flex;
    gap: 15px; 
    align-items: center;
}


.hero-image-content {
    flex: 1; 
    position: relative; 
    display: flex;
    justify-content: center;
    align-items: center;

    min-height: 80px; 
}

.hero-main-image {
    width: 100%; 
    max-width: 450px; 
    height: auto;
    position: relative; 
    z-index: 10; 
    
   
       align-self: flex-end; 
       margin-bottom: 50px; 
}


.hero-image-content {
    flex: 1; 
    position: relative; 
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 80px; 
}

.hero-image-content::after {
    content: '';
    position: absolute;
    top: -10px;
    left: -10px;
    right: -10px;
    bottom: -10px;
    background: linear-gradient(45deg, 
        transparent, 
        rgba(37, 99, 235, 0.1), 
        transparent);
    border-radius: 25px;
    opacity: 0;
    transition: opacity 0.5s ease;
    pointer-events: none;
}

.hero-image-content:hover::after {
    opacity: 1;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.6; }
}

/* --- STYLING FOR BACKGROUND CIRCLES --- */
.hero-graphic-circle {
    position: absolute;
    border-radius: 50%;
}

.orange-bg {
    width: 350px;
    height: 350px;
     opacity: 0.90; 
    background-color: #2563eb; 
    right: 50px;
    top: 0;
    z-index: 5; 
}

.blue-bg {
    width: 100px;
    height: 100px;
    background-color: #2563eb; 
    opacity: 0.7;
    bottom: 20px;
    right: 10px;
    z-index: 5;
}


.experienced-mentor-badge {
    position: absolute;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
    padding: 12px 20px; 
    border-radius: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    backdrop-filter: blur(15px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: #1f2937;
    font-size: 14px;
    min-width: 200px;
    width: fit-content;
    text-align: center;
    z-index: 15; 
    transition: all 0.4s ease;
    bottom: 50px; 
    left: 50%;
    transform: translateX(-50%); 
}

.experienced-mentor-badge:hover {
    transform: translateX(-50%) translateY(-5px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(255, 255, 255, 0.9));
    border-color: rgba(255, 255, 255, 0.5);
}

.experienced-mentor-badge::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(45deg, #2563eb, #1e40af, #2563eb);
    border-radius: 15px;
    z-index: -1;
    opacity: 0;
    transition: opacity 0.4s ease;
}

.experienced-mentor-badge:hover::before {
    opacity: 1;
}


/* --- YOUR EXISTING BUTTON STYLES --- */
.btn-lg {
    padding: 14px 25px;
    font-size: 16px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: background-color 0.3s;
}

/* ===== Stats Section Background ===== */
.stats {
    position: relative;
    background: rgba(241, 245, 249, 0.6); /* semi-transparent */
    backdrop-filter: blur(8px);
    border-top: 1px solid rgba(200, 200, 200, 0.4);
    border-bottom: 1px solid rgba(200, 200, 200, 0.4);
    padding: 20px 20px;
    overflow: hidden; /* para hindi lumabas ang circles */
}

/* ===== Floating Small Circles ===== */
.stats::before,
.stats::after {
    content: "";
    position: absolute;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(37, 99, 235, 0.15); /* blue soft glow */
    filter: blur(1px);
    z-index: 1;
}

/* Circle Positions */
.stats::before {
    top: -40px;
    left: -30px;
}

.stats::after {
    bottom: -40px;
    right: -30px;
}

/* ===== Grid ===== */
.stats-grid {
    position: relative;
    z-index: 5; /* para nasa ibabaw ng circles */
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    text-align: center;
}

/* ===== Individual Cards ===== */
.stats-grid > div {
    background: rgba(255, 255, 255, 0.35);
    backdrop-filter: blur(6px);
    border-radius: 12px;
    padding: 15px 10px;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    transition: 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

/* ===== Text Styles ===== */
.stat-value {
    font-size: 28px;
    color: #2563eb;
    font-weight: bold;
    transition: 0.3s ease;
}

.stat-label {
    font-size: 14px;
    color: #555;
    transition: 0.3s ease;
}

/* ===== Hover (text only) ===== */
.stats-grid > div:hover .stat-value {
    transform: scale(1.15);
    color: #1d4ed8;
}

.stats-grid > div:hover .stat-label {
    transform: scale(1.08);
    color: #333;
}


/* Features */
/* --- Features Section Base --- */
.features {
    position: relative;
    padding: 70px 180px;
    max-width: 1200px;
    margin: auto;
    text-align: center;
    font-family: 'Poppins', sans-serif;
    color: #2d2d2d;
    overflow: hidden;
}

/* --- Background Circles Matching HERO --- */
.features-circle {
    position: absolute;
    border-radius: 50%;
    background-color: #2563eb;
    opacity: 0.20;  /* same as hero */
    z-index: 1;
}

/* Large Left Circle */
.features-circle-left {
    width:250px;
    height: 250px;
    top: -40px;
    left: -50px;
      background-color: #2563eb;
      opacity: 0.80;  /* same as hero */
    z-index: 1;
}


/* --- Center Circle (Behind Text) --- */
.features-circle-center {
    width: 250px;
    height: 250px;
    background-color: #2563eb;
    opacity: 0.10;   /* light opacity so text is readable */
    position: absolute;
    top: 20%;        /* almost center but a bit above */
    left: 36%;
    transform: translate(-50%, -20%);
    z-index: 0;      /* behind everything */
    border-radius: 50%;
}


/* Large Right Circle */
.features-circle-right {
    width: 300px;
    height: 300px;
    bottom: -60px;
    right: -100px;
}

.section-title {
    font-size: 32px;
    font-weight: 600;
}

.section-desc {
    max-width: 650px;
    margin: auto;
    color: #6f6f6f;
    font-size: 1rem;
}

.features-grid {
    margin-top: 30px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

/* Updated Card Style */
.card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
}

/* Same hover effect as notice-card-student */
.card:hover {
    transform: translateY(-6px);
    border-color: #1e4dcc;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Icon Box */
.icon-box {
    width: 55px;
    height: 55px;
    background: #f4f6ff;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: auto;
    margin-bottom: 18px;
    transition: all 0.3s ease;
}

/* Icon Hover Animation */
.card:hover .icon-box {
    background: #2563eb;
    transform: scale(1.1);
}

.card:hover .icon-box i {
    color: white;
}

.card h3 {
    margin-bottom: 10px;
    font-weight: 600;
    color: #2c3e50;
}

.card p {
    color: #5d6d7e;
    font-size: 0.95rem;
}

/* CTA */
/* ===== CTA Section ===== */
.cta {
    position: relative;
    padding: 100px 40px;
    text-align: center;
    background: rgba(255,255,255,0.4);
    backdrop-filter: blur(12px);
    border-radius: 20px;
    margin: 40px auto;
    width: 90%;
    max-width: 900px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}

/* CTA Icons */
.cta i {
    font-size: 40px;
    color: #2563eb;
    margin-bottom: 10px;
    display: block;
}

.cta h2 {
    font-size: 28px;
    margin-bottom: 10px;
    color: #1e3a8a;
}

.cta p {
    font-size: 16px;
    color: #333;
    margin-bottom: 20px;
}

/* CTA button */
.cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #2563eb;
    padding: 12px 28px;
    color: #fff;
    border-radius: 50px;
    font-size: 17px;
    transition: 0.3s ease;
}

.cta-btn:hover {
    background: #1e40af;
}

.cta-actions {
    display: flex;
    gap: 15px;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
}

.cta-actions .btn-lg {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* ===== Floating Circles (Random layout) ===== */
.cta::before,
.cta::after {
    content: "";
    position: absolute;
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: rgba(37, 99, 235, 0.18);
    filter: blur(45px);
    z-index: 0;
}

/* Random-ish positions (not sa gilid mismo) */
.cta::before {
    top: 20%;
    left: 10%;
}

.cta::after {
    bottom: 15%;
    right: 18%;
}

/* Optional extra circles (for more depth) */
.cta .circle-1,
.cta .circle-2 {
    content: "";
    position: absolute;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: rgba(33, 69, 233, 0.2); /* indigo glowing */
    filter: blur(-30px);
    z-index: 0;
}

.cta .circle-1 {
    top: 10%;
    right: 30%;
    
}

.cta .circle-2 {
    bottom: -10%;
    left: 28%;
}

/* Put content above circles */
.cta * {
    position: relative;
    z-index: 5;
}


/* Footer */
footer {
 position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(6px);
    border-bottom: 1px solid #ddd;
    padding: 30px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}



footer .logo {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo-box {
    width: 50px;
    height: 50px;
    border-radius: 50px;
    display: flex;
    justify-content: center;
    align-items: center;
}

footer p {
    font-size: 14px;
    color: #555;
}

/* ================================
    FOOTER — CLEAN LIGHT STYLE (Modified from Dark)
================================ */
.footer-clean {
    /* Changed: Background to white */
    background: #ffffff;
    /* Changed: Default text color for light background */
    color: #333333; 
    padding: 60px 40px 30px 40px;
    font-family: 'Poppins', sans-serif;
}
/* Explicit Divider Styling */
.footer-divider {
    max-width: 1200px;
    margin: 0 auto 20px auto; /* Centered, with space below the line */
    height: 1px;
    background-color: #cccccc; /* Light gray divider line */
}
.footer-clean-wrapper {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 40px;
    max-width: 1200px;
    margin: auto;
    /* Added: Bottom border for the divider line */
    border-bottom: 1px solid #cccccc; /* Light gray divider */
    padding-bottom: 30px; /* Space above the divider */
}

/* Brand Section */
.footer-brand h3 {
    margin: 12px 0 8px 0;
    font-size: 18px;
    font-weight: 600;
    /* Changed: Text color to dark for white background */
    color: #0f172a; 
}

.footer-brand p {
    font-size: 14px;
    line-height: 1.5;
    /* Changed: Text color to a medium gray for body text */
    color: #666666; 
}

/* Links and Contact Headers */
.footer-links h4 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 12px;
    /* Changed: Text color to dark for white background */
    color: #0f172a; 
}

/* Links */
.footer-links a {
    display: block;
    font-size: 14px;
    /* Changed: Link color to medium gray */
    color: #666666; 
    text-decoration: none;
    margin: 6px 0;
    transition: 0.3s ease;
}

.footer-links a:hover {
    /* Kept blue hover color */
    color: #0b5cff; 
}

/* Contact Paragraphs */
.footer-links p {
    font-size: 14px;
    margin: 6px 0;
    /* Changed: Paragraph color to medium gray */
    color: #666666;
}

/* Copyright Section */
.footer-copy {
    /* Reduced margin as the divider is now part of the wrapper */
    margin-top: 20px; 
    text-align: center;
    font-size: 13px;
    /* Changed: Text color to dark gray */
    color: #333333;
    opacity: 1; /* Made it fully visible */
}

/* ================================
    RESPONSIVE
================================ */
@media (max-width: 900px) {
    .footer-clean-wrapper {
        flex-direction: column;
        align-items: center;
        text-align: center;
        /* Ensure border is still visible in mobile layout */
        border-bottom: 1px solid #cccccc; 
    }

    .footer-links {
        margin-top: 20px;
    }
}

/* ===== Sections ===== */
.section {
  padding: 90px 60px;
  background: #f8f9fc;
}

.about-section {
  background: #ffffff;
}

.container {
  max-width: 1200px;
  margin: auto;
}

/* ================================
   ABOUT — CLEAN IMAGE STYLE
================================ */
.about-clean {
  padding: 110px 80px;
  background: #f8fafc;
}

.about-clean-wrapper {
  max-width: 1200px;
  margin: auto;
  display: grid;
  grid-template-columns: 1.15fr 0.85fr;
  gap: 70px;
  align-items: center;
}

/* LEFT */
.about-clean-text h2 {
  font-size: 40px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 20px;
}

.about-clean-text p {
  font-size: 16.5px;
  line-height: 1.7;
  color: #64748b;
  margin-bottom: 18px;
  max-width: 520px;
}

.about-clean-btn {
  margin-top: 20px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: #0b5cff;
  color: #fff;
  padding: 13px 26px;
  border-radius: 50px;
  font-weight: 600;
  text-decoration: none;
  transition: 0.3s ease;
}

.about-clean-btn:hover {
  background: #084bcc;
}

/* RIGHT CARD WRAPPER */
.about-clean-card-wrapper {
  position: relative;
}

/* Soft blue background shape */
.about-clean-card-wrapper::before {
  content: "";
  position: absolute;
  inset: -22px;
  background: #dbe7ff;
  border-radius: 26px;
  transform: rotate(-2deg);
  z-index: 0;
}

/* CARD */
.about-clean-card {
  position: relative;
  z-index: 2;
  background: #ffffff;
  border-radius: 22px;
  padding: 22px 28px;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
}

/* ITEMS */
.about-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 0;
}

.about-item:not(:last-child) {
  border-bottom: 1px solid #eef2f7;
}

/* ICON */
.about-icon {
  width: 42px;
  height: 42px;
  background: #eef4ff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #0b5cff;
}

.about-icon i {
  width: 20px;
  height: 20px;
}

/* TEXT */
.about-item strong {
  font-size: 18px;
  color: #0f172a;
  display: block;
}

.about-item span {
  font-size: 13.5px;
  color: #64748b;
}
.about-item strong,
.about-item span {
    transition: transform 0.3s ease, color 0.3s ease; /* smooth transition */
}

.about-item:hover strong,
.about-item:hover span {
    transform: scale(1.05); /* grow slightly */
    color: #0b5cff; /* optional color change */
}

/* ================================
   RESPONSIVE
================================ */
@media (max-width: 900px) {
  .about-clean-wrapper {
    grid-template-columns: 1fr;
    gap: 50px;
  }

  .about-clean-text {
    text-align: center;
  }

  .about-clean-text p {
    margin-left: auto;
    margin-right: auto;
  }

  .about-clean-card-wrapper::before {
    inset: -14px;
  }
}

/* ================================
   RESPONSIVE DESIGN — FULL SET
   ================================ */


/* ---------- LARGE TABLETS (≤1200px) ---------- */
@media (max-width: 1200px) {
    .features {
        padding: 60px 120px;
    }
}


/* ---------- TABLETS (≤992px) ---------- */
@media (max-width: 992px) {
    /* Header */
    header {
        padding: 15px 25px;
    }

    /* Hero Layout */
    .hero-content-wrapper {
        flex-direction: column;
        text-align: center;
        gap: 40px;
    }

    .hero-text-content {
        text-align: center;
        padding: 10px;
    }

    .hero-title {
        font-size: 38px;
    }

    .hero p {
        font-size: 16px;
        max-width: 550px;
        margin: auto;
    }

    .hero-actions {
        justify-content: center;
    }

    /* Hero Image */
    .hero-main-image {
        max-width: 380px;
        margin-bottom: 0;
    }

    /* Stats */
    .stats {
        padding: 40px 20px;
    }

    /* Features */
    .features {
        padding: 60px 90px;
    }

    .features-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    /* CTA */
    .cta {
        padding: 80px 30px;
    }
}



/* ---------- MOBILE (≤768px) ---------- */
@media (max-width: 768px) {

    /* Header Buttons */
    header a {
        padding: 8px 14px;
        font-size: 13px;
    }

    /* Hero Section */
    .hero {
        padding: 50px 20px;
    }

    .hero-content-wrapper {
        flex-direction: column;
        gap: 30px;
    }

    .hero-title {
        font-size: 32px;
        text-align: center;
    }

    .hero-description {
        font-size: 16px;
        text-align: center;
    }

    /* Background Circles Adjust */
    .hero-graphic-circle-left,
    .hero-graphic-circle-left-bottom,
    .orange-bg {
        display: none; /* hide for mobile cleanliness */
    }

    /* Organized Hero Section */
    .hero-text-content {
        padding: 20px;
        order: 2;
    }

    .hero-image-section {
        order: 1;
    }

    .hero-badges {
        justify-content: center;
        margin-bottom: 20px;
    }

    .hero-features {
        grid-template-columns: 1fr;
        margin: 20px 0;
    }

    .feature-item {
        padding: 12px;
    }

    /* Responsive Image Optimization */
    .logo-box {
        width: 40px;
        height: 40px;
    }

    .logo-box img {
        width: 28px;
        height: 28px;
    }

    .hero-image-container {
        max-width: 350px;
    }

    .hero-main-image {
        border-radius: 15px;
    }

    .image-info {
        padding: 20px;
    }

    .image-info h3 {
        font-size: 20px;
    }

    .experienced-mentor-badge {
        display: none; /* hide on mobile */
    }

    .image-controls {
        bottom: 10px;
        right: 10px;
    }

    .zoom-btn {
        width: 35px;
        height: 35px;
        font-size: 14px;
    }
}

    /* Image Loading Animation */
    .hero-image-content {
        animation: fadeInUp 0.8s ease-out;
    }

    .logo-box {
        animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }



    /* Stats */
    .stats-grid {
        gap: 15px;
        grid-template-columns: repeat(2, 1fr);
    }

    .stats-grid > div {
        min-height: 100px;
        padding: 12px;
    }

    .stat-value {
        font-size: 24px;
    }

    /* Features */
    .features {
        padding: 50px 40px;
    }

    .features-circle-left,
    .features-circle-right,
    .features-circle-center {
        display: none;
    }

    /* CTA Section */
    .cta {
        padding: 60px 20px;
        width: 95%;
    }

    .cta h2 {
        font-size: 24px;
    }

    .cta p {
        font-size: 15px;
    }

    .cta-btn {
        font-size: 15px;
        padding: 10px 22px;
    }

    .cta-actions {
        flex-direction: column;
        width: 100%;
        gap: 12px;
    }

    .cta-actions .btn-lg {
        width: 100%;
        justify-content: center;
    }

    /* Footer */
    footer {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 10px;
    }
}


/* ---------- SMALL MOBILE (≤576px) ---------- */
@media (max-width: 576px) {

    /* Header */
    header {
        padding: 12px 20px;
    }

    .logo-box {
        width: 40px;
        height: 40px;
    }

    /* Hero */
    .hero-title {
        font-size: 28px;
    }

    .hero p {
        font-size: 15px;
        margin-bottom: 25px;
    }

    .hero-actions {
        flex-direction: column;
        gap: 12px;
    }

    .btn-lg {
        width: 100%;
        justify-content: center;
    }

    /* Hero Badge */
    .experienced-mentor-badge {
        bottom: 10px;
        width: 90%;
        font-size: 14px;
        padding: 8px 10px;
    }

    /* Stats mobile */
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-value {
        font-size: 22px;
    }

    /* Features */
    .features {
        padding: 40px 20px;
    }

    .features-grid {
        grid-template-columns: 1fr;
    }

    /* CTA */
    .cta {
        width: 100%;
        padding: 50px 20px;
    }

    .cta h2 {
        font-size: 22px;
    }
}
</style>

<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

</head>

<body>

<!-- Header -->
<header>
    <div class="logo">
        <div class="logo-box">
    <img src="./images/image.png" width="35" height="35" alt="Logo">
</div>

        <strong> TESDA Auto Mechanic Training Centre</strong>
    </div>
    <nav class="nav">
<a href="#about">About</a>
<a href="#programs">Programs</a>
<a href="#enrollment">Enrollment</a>
<a href="#contact">Contact</a>
</nav>

    <div>
        <a class="btn-primary" href="login/index.php">Login</a>
    </div>
</header>

<!-- Hero -->
<section id="home" class="hero">
    <div class="hero-content-wrapper">
        
        <div class="hero-text-content">
            <div class="badge">
            <i data-lucide="settings"></i>
                TESDA Accredited Training Centre
            </div>

              <h1 class="hero-title">
                  <span>Auto Mechanic</span> Training System
              </h1>

              <p>
                  Access pre-enrollment, scholarship applications, competency-based modules, 
                  and manage your automotive training journey-all in one place.
              </p>

              <div class="hero-actions">
                  <a href="pre_enrollment.php" class="btn-lg btn-primary">
                      Start Pre-Enrollment
                      <i data-lucide="arrow-right"></i>
                  </a>
                  <a href="login.php" class="btn-lg btn-outline">
                      <i data-lucide="log-in"></i>
                      Login to Portal
                  </a>
                  </div>
          </div>

        <div class="hero-image-content">
            <img src="./images/graduates.jpg" alt="Graduates accessing online portal" class="hero-main-image">

            <div class="experienced-mentor-badge">
                Access Now Your Student Portal
                </div>
            
            <div class="hero-graphic-circle orange-bg"></div>
            <div class="hero-graphic-circle blue-bg"></div>
        </div>
    </div>
</section>
<!-- Stats -->
<section id="about" class="about-clean">
  <div class="about-clean-wrapper">

    <!-- LEFT CONTENT -->
    <div class="about-clean-text">
      <h2>About TESDA Auto Mechanic Training Centre</h2>

      <p>
        The TESDA Auto Mechanic Training Centre provides comprehensive automotive training --
        from pre-enrollment to certification -- by bringing all essential
        tools into one integrated platform.
      </p>

      <p>
        Accredited by TESDA, we deliver industry-standard automotive training
        with competency-based learning, scholarship programs, and modern workshop facilities.
      </p>

      <a href="pre_enrollment.php" class="about-clean-btn">
        Start Pre-Enrollment
        <i data-lucide="arrow-right"></i>
      </a>
    </div>

    <!-- RIGHT CARD -->
    <div class="about-clean-card-wrapper">
      <div class="about-clean-card">

        <div class="about-item">
          <div class="about-icon">
            <i data-lucide="users"></i>
          </div>
          <div>
            <strong>1,200+</strong>
            <span>Trainees</span>
          </div>
        </div>

        <div class="about-item">
          <div class="about-icon">
            <i data-lucide="wrench"></i>
          </div>
          <div>
            <strong>15+</strong>
            <span>Training Programs</span>
          </div>
        </div>

        <div class="about-item">
          <div class="about-icon">
            <i data-lucide="award"></i>
          </div>
          <div>
            <strong>95%</strong>
            <span>Employment Rate</span>
          </div>
        </div>

      </div>
    </div>

  </div>
</section>




<!-- Features -->
<section id="features" class="features">
        <!-- 🔵 Hero-Style Background Circles -->
    <div class="features-circle features-circle-left"></div>
    <div class="features-circle features-circle-right"></div>

    <div class="features-circle features-circle-center"></div>

    <h2 class="section-title">Everything You Need</h2>
    <p class="section-desc">
        Our portal provides all the tools and features you need to manage your academic life efficiently.
    </p>

    <div class="features-grid">

        <div class="card">
            
            <div class="icon-box"><i data-lucide="file-text"></i></div>
            <h3>Pre-Enrollment</h3>
            <p>Apply for training programs with our streamlined pre-enrollment system.</p>
        </div>

        <div class="card">
            <div class="icon-box"><i data-lucide="award"></i></div>
            <h3>Scholarship Programs</h3>
            <p>Apply for financial assistance and scholarship opportunities.</p>
        </div>

        <div class="card">
            <div class="icon-box"><i data-lucide="book-open"></i></div>
            <h3>Competency Modules</h3>
            <p>Access TESDA-aligned competency-based learning modules.</p>
        </div>

        <div class="card">
            <div class="icon-box"><i data-lucide="wrench"></i></div>
            <h3>Workshop Training</h3>
            <p>Hands-on training with modern automotive workshop equipment.</p>
        </div>

        <div class="card">
           <div class="icon-box">
  <i data-lucide="certificate"></i></div>
            <h3>Certification</h3>
            <p>Earn TESDA certifications and advance your automotive career.</p>
        </div>

<div class="card">
           <div class="icon-box">
  <i data-lucide="shield-check"></i>
</div>
            <h3>Secure Access</h3>
            <p>Your data is protected with enterprise-grade security and encryption.</p>
        </div>
    </div>
</section>


<!-- CTA -->
<section class="cta">
        <div class="circle-1"></div>
    <div class="circle-2"></div>

    <i data-lucide="wrench"></i>
    <h2>Ready to Start Your Automotive Career?</h2>
    <p>Apply now for our TESDA-accredited training programs and gain industry-recognized certifications.</p>

    <div class="cta-actions">
        <a href="pre_enrollment.php" class="btn-lg cta-btn">
            Apply Now
            <i data-lucide="arrow-right"></i>
        </a>
        <a href="login.php" class="btn-lg btn-outline">
            <i data-lucide="log-in"></i>
            Login
        </a>
    </div>
</section>

<footer id="contact" class="footer-clean">
  <div class="footer-clean-wrapper">

    <!-- Brand -->
    <div class="footer-brand">
      <img src="./images/image.png" width="40" alt="TESDA Logo">
      <h3>TESDA Auto Mechanic Training Centre</h3>
      <p>TESDA Accredited Training Centre.<br>Providing quality automotive education and certification.</p>
    </div>

    <!-- Quick Links -->
    <div class="footer-links">
      <h4>Quick Links</h4>
      <a href="#home">Home</a>
      <a href="#programs">Programs</a>
      <a href="#enrollment">Enrollment</a>
      <a href="#about">About</a>
    </div>

    <!-- Portal -->
    <div class="footer-links">
      <h4>Portal</h4>
      <a href="pre_enrollment.php">Pre-Enrollment</a>
      <a href="scholarship_application.php">Scholarship</a>
      <a href="login.php">Student Login</a>
    </div>

    <!-- Contact -->
    <div class="footer-links">
      <h4>Contact</h4>
      <p>Training Centre Location</p>
      <p>Philippines</p>
      <a href="mailto:info@tesda-auto-mechanic.edu.ph">info@tesda-auto-mechanic.edu.ph</a>
    </div>

  </div>

<div class="footer-divider"></div> <p class="footer-copy">
    © 2025 TESDA Auto Mechanic Training Centre. All rights reserved.
  </p>
</footer>


<!-- External JavaScript -->
<script src="./login/index.js"></script>

</body>
</html>
