<?php
session_start();
require_once 'config/database.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_guest') {
    $db = db();
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
    if ($first_name !== '' && $last_name !== '') {
        try {
            if (!$db) { throw new Exception('no_db'); }
            $db->exec("CREATE TABLE IF NOT EXISTS guests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                first_name VARCHAR(50) NOT NULL,
                last_name VARCHAR(50) NOT NULL,
                email VARCHAR(100),
                phone VARCHAR(20),
                address TEXT,
                id_type VARCHAR(50),
                id_number VARCHAR(50),
                id_photo_url VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            try { $db->query("SELECT username,password_hash FROM guests LIMIT 1"); } catch (Throwable $e) { try { $db->exec("ALTER TABLE guests ADD COLUMN username VARCHAR(50) NULL, ADD COLUMN password_hash VARCHAR(255) NULL"); } catch (Throwable $e2) {} }
            $stmt = $db->prepare("INSERT INTO guests (first_name,last_name,email,phone,address,username,password_hash) VALUES (:fn,:ln,:em,:ph,:ad,:un,:pw)");
            $stmt->execute([':fn' => $first_name, ':ln' => $last_name, ':em' => $email, ':ph' => $phone, ':ad' => $address, ':un' => ($username!==''?$username:null), ':pw' => $password_hash]);
            $_SESSION['registered_guest_id'] = (int)$db->lastInsertId();
            $_SESSION['registered_guest_name'] = $first_name . ' ' . $last_name;
            header('Location: guest/dashboard.php');
            exit();
        } catch (Throwable $e) {
            $_SESSION['landing_error'] = 'Registration failed';
            header('Location: landing.php?error=1');
            exit();
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Torres farm and Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --navy: #0f1c2d;
            --gold: #d4af37;
            --text: #333;
            --muted: #666;
            --light: #fff;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            color: var(--text);
            background-color: #ffffff;
            background-image: 
                radial-gradient(at 40% 20%, rgba(212, 175, 55, 0.08) 0px, transparent 50%),
                radial-gradient(at 80% 0%, rgba(15, 28, 45, 0.05) 0px, transparent 50%),
                radial-gradient(at 0% 50%, rgba(212, 175, 55, 0.05) 0px, transparent 50%);
            background-attachment: fixed;
            overflow-x: hidden;
        }
        
        /* Scroll Reveal Effect */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.5, 0, 0, 1);
        }
        
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Staggered delays */
        .reveal.delay-100 { transition-delay: 0.1s; }
        .reveal.delay-200 { transition-delay: 0.2s; }
        .reveal.delay-300 { transition-delay: 0.3s; }

        /* Header */
        header {
            position: absolute;
            top: 0;
            width: 100%;
            z-index: 100;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .top-bar {
            background: var(--navy);
            padding: 10px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #fff;
        }

        .contact-info {
            display: flex;
            gap: 30px;
        }

        .contact-info span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .auth-links a {
            color: #fff;
            text-decoration: none;
            margin-left: 15px;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 50px;
        }

        .logo {
            font-size: 28px;
            color: var(--gold);
            font-weight: 300;
            letter-spacing: 2px;
            font-family: 'Playfair Display', serif;
        }

        .logo span {
            display: block;
            font-size: 10px;
            letter-spacing: 4px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        .nav-links a {
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: color 0.3s;
            position: relative;
            padding-bottom: 5px;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 50%;
            background-color: var(--gold);
            transition: all 0.3s ease-in-out;
            transform: translateX(-50%);
        }

        .nav-links a:hover {
            color: var(--gold);
        }
        
        .nav-links a:hover::after {
            width: 100%;
        }

        .book-btn {
            background: var(--gold);
            border: none;
            color: var(--navy);
            padding: 10px 30px;
            cursor: pointer;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .book-btn:hover {
            background: #fff;
            color: var(--navy);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.5);
            transform: translateY(-2px);
        }

        .menu-toggle { display: none; background: transparent; color: #fff; border: none; font-size: 24px; cursor: pointer; padding: 8px; border-radius: 4px; transition: background-color 0.3s; }
        .menu-toggle:hover { background: rgba(255,255,255,0.1); }

        /* Hero Section */
        .hero {
            height: 100vh;
            background: linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: #fff;
        }

        .hero h1 {
            font-size: 64px;
            font-weight: 400;
            font-family: 'Playfair Display', serif;
            margin-bottom: 20px;
            letter-spacing: 2px;
        }

        .hero p {
            font-size: 20px;
            margin-bottom: 40px;
            font-weight: 300;
        }

        .explore-btn {
            background: #d4af37;
            border: none;
            color: #000;
            padding: 15px 40px;
            cursor: pointer;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .explore-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: 0.5s;
            z-index: -1;
        }
        
        .explore-btn:hover::before {
            left: 100%;
        }
        
        .explore-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(212, 175, 55, 0.3);
        }

        .hero-promo {
            margin-top: 14px;
            font-size: 14px;
            font-weight: 400;
            color: #f5f5f5;
            opacity: 0.9;
        }

        /* Booking Form */
        .booking-form {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            padding: 24px 24px 24px 24px;
            display: flex;
            gap: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            max-width: 1200px;
            margin: -60px auto 0;
            position: relative;
            z-index: 10;
            align-items: flex-end;
            transition: background-color 0.25s ease, box-shadow 0.25s ease;
        }

        .booking-form:hover,
        .booking-form:focus-within {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 -10px 50px rgba(0, 0, 0, 0.35);
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
            color: #666;
            letter-spacing: 1px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            background: rgba(255, 255, 255, 0.8);
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }

        .search-btn {
            background: var(--gold);
            border: none;
            color: var(--navy);
            padding: 15px 32px;
            cursor: pointer;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            align-self: flex-end;
            border-radius: 6px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .search-btn:hover {
            background: #fff;
            color: var(--navy);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
            transform: translateY(-2px);
        }

        /* Welcome Section */
        .welcome {
            padding: 80px 50px;
            max-width: 1400px;
            margin: 40px auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .welcome-text {
            padding-right: 50px;
        }

        .section-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #999;
            margin-bottom: 20px;
        }

        .welcome h2 {
            font-size: 42px;
            font-weight: 400;
            font-family: 'Playfair Display', serif;
            margin-bottom: 30px;
            line-height: 1.3;
        }

        .welcome h3 {
            font-size: 24px;
            font-weight: 400;
            margin-bottom: 30px;
        }

        .welcome p {
            line-height: 1.8;
            color: #666;
            margin-bottom: 20px;
        }

        .ceo-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 40px;
        }

        .ceo-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #ddd;
        }

        .ceo-details h4 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .ceo-details p {
            font-size: 12px;
            color: #d4af37;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .signature {
            font-family: 'Brush Script MT', cursive;
            font-size: 36px;
            color: #333;
            margin-left: auto;
        }

        .welcome-images {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .welcome-img {
            width: 100%;
            height: 300px;
            background: linear-gradient(135deg, rgba(255,255,255,0.4), rgba(255,255,255,0.1));
            object-fit: cover;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,.08);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .welcome-img:first-child {
            grid-column: 1 / 2;
            grid-row: 1 / 3;
            height: 100%;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .welcome-images .welcome-img:nth-child(2) { border-bottom-left-radius: 0; }
        .welcome-images .welcome-img:nth-child(3) { border-top-left-radius: 0; }

        .welcome-video {
            padding: 100px 50px;
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }
        .welcome-video .vs-player { max-width: 100%; }
        .video-grid { 
            background:
              linear-gradient(90deg, rgba(99,102,241,.08) 1px, transparent 1px) 0 0/24px 24px,
              linear-gradient(0deg, rgba(99,102,241,.08) 1px, transparent 1px) 0 0/24px 24px,
              linear-gradient(180deg, rgba(255,255,255,0.8), rgba(249,250,251,0.8));
            backdrop-filter: blur(5px);
            border-radius: 20px;
            padding: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }
        .section-divider { border: 0; height: 1px; background: linear-gradient(to right, transparent, rgba(0,0,0,.12), transparent); margin: 60px 0; }

        /* Rooms Section */
        .rooms {
            background: var(--navy);
            padding: 100px 50px;
            text-align: center;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .rooms-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            filter: blur(10px) brightness(0.55);
            transform: scale(1.04);
            transition: opacity 800ms cubic-bezier(0.4, 0, 0.2, 1), 
                        transform 800ms cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 1;
        }

        .rooms-bg.rooms-bg-new {
            opacity: 0;
            transform: scale(1.08);
        }
        
        .rooms-bg.fade-in {
            animation: bgFadeIn 1s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        @keyframes bgFadeIn {
            0% {
                opacity: 0;
                transform: scale(1.08);
                filter: blur(15px) brightness(0.4);
            }
            100% {
                opacity: 1;
                transform: scale(1.04);
                filter: blur(10px) brightness(0.55);
            }
        }

        .rooms-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(11,19,34,0.6), rgba(11,19,34,0.6));
            transition: background 600ms ease;
        }
        
        .rooms-overlay.pulse {
            animation: overlayPulse 800ms ease;
        }
        
        @keyframes overlayPulse {
            0%, 100% {
                background: linear-gradient(180deg, rgba(11,19,34,0.6), rgba(11,19,34,0.6));
            }
            50% {
                background: linear-gradient(180deg, rgba(11,19,34,0.75), rgba(11,19,34,0.75));
            }
        }

        .rooms-content {
            position: relative;
            z-index: 1;
        }

        .rooms-hero {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 40px;
            align-items: center;
        }

        .room-info {
            text-align: left;
        }

        .room-title-large {
            font-size: 44px;
            font-family: 'Playfair Display', serif;
            font-weight: 400;
            margin: 10px 0 16px;
            transition: opacity 400ms ease, transform 400ms ease;
        }
        
        .room-title-large.updating {
            opacity: 0;
            transform: translateY(10px);
        }

        .room-desc {
            color: #cbd5e1;
            max-width: 600px;
        }

        .room-actions { 
            display: flex; 
            gap: 12px; 
            margin-top: 24px; 
        }
        
        .room-actions button { 
            display: flex;
            align-items: center;
            gap: 8px;
            border: none; 
            padding: 14px 28px; 
            border-radius: 6px; 
            font-weight: 700; 
            font-size: 15px;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 250ms cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .room-actions button .btn-icon {
            font-size: 18px;
            transition: transform 250ms ease;
        }
        
        .room-actions .primary { 
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(5px);
            color: #000; 
            box-shadow: 0 2px 8px rgba(255,255,255,0.2);
        }
        
        .room-actions .primary:hover { 
            background: rgba(255,255,255,0.9);
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(255,255,255,0.3);
        }
        
        .room-actions .primary:hover .btn-icon {
            transform: translateX(2px);
        }
        
        .room-actions .primary:active {
            transform: scale(0.98);
        }
        
        .room-actions .secondary { 
            background: rgba(109,109,110,0.7);
            color: #fff; 
            backdrop-filter: blur(10px);
        }
        
        .room-actions .secondary:hover { 
            background: rgba(109,109,110,0.5);
            transform: scale(1.05);
        }
        
        .room-actions .secondary:active {
            transform: scale(0.98);
        }

        .room-carousel { position: relative; padding: 24px; overflow: visible; }
        .room-carousel .glass-panel {
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            backdrop-filter: blur(10px) saturate(120%);
            box-shadow: 0 16px 40px rgba(0,0,0,0.35);
            pointer-events: none;
            z-index: 0;
        }
        .carousel-track {
            display: flex;
            gap: 18px;
            overflow-x: auto;
            overflow-y: visible;
            scroll-snap-type: x mandatory;
            padding: 24px 12px 32px 12px;
            position: relative;
            z-index: 1;
        }
        .carousel-track::-webkit-scrollbar { height: 10px; }
        .carousel-track::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.25); border-radius: 10px; }

        .room-carousel .room-card { 
            width: 180px; 
            flex: 0 0 auto; 
            scroll-snap-align: center; 
            background: transparent; 
            border-radius: 12px; 
            cursor: pointer;
            position: relative;
            transition: transform 450ms cubic-bezier(0.4, 0, 0.2, 1), z-index 0ms 250ms;
            z-index: 1;
            will-change: transform;
        }
        
        .room-carousel .room-card:hover {
            transform: scale(1.25) translateY(-12px);
            z-index: 100;
            transition: transform 450ms cubic-bezier(0.4, 0, 0.2, 1), z-index 0ms 0ms;
        }
        
        .room-carousel .room-card.active {
            transform: scale(1.05);
        }
        
        .room-carousel .room-card.active:hover {
            transform: scale(1.28) translateY(-12px);
        }
        
        .room-carousel .room-img { 
            width: 100%;
            height: 260px; 
            object-fit: cover;
            border-radius: 12px; 
            box-shadow: 0 8px 24px rgba(0,0,0,0.35);
            transition: box-shadow 450ms ease, border 450ms ease, filter 450ms ease;
            border: 2px solid transparent;
            display: block;
            filter: brightness(0.85);
        }
        
        .room-carousel .room-card:hover .room-img {
            box-shadow: 0 24px 64px rgba(0,0,0,0.7);
            filter: brightness(1.1);
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .room-carousel .room-card.active .room-img {
            box-shadow: 0 12px 40px rgba(212, 175, 55, 0.4);
            border: 2px solid rgba(212, 175, 55, 0.6);
            filter: brightness(1);
        }
        
        .room-carousel .room-card.active:hover .room-img {
            box-shadow: 0 24px 64px rgba(212, 175, 55, 0.7);
            filter: brightness(1.15);
            border: 2px solid rgba(212, 175, 55, 0.9);
        }
        
        .room-carousel .room-title { 
            position: absolute; 
            left: 12px; 
            bottom: 12px; 
            font-size: 13px; 
            background: rgba(15,28,45,0.7); 
            padding: 6px 10px; 
            border-radius: 8px;
            transition: all 400ms cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(8px);
            opacity: 0.9;
            z-index: 1;
        }
        
        .room-carousel .room-card:hover .room-title {
            opacity: 0;
            transform: translateY(10px);
        }
        .room-carousel .room-card.touch-hover .room-title {
            opacity: 0;
            transform: translateY(10px);
        }
        
        .room-carousel .room-card.active .room-title {
            background: rgba(212, 175, 55, 0.9);
            color: #000;
            font-weight: 600;
            opacity: 1;
        }
        
        .room-carousel .room-card.active:hover .room-title {
            background: rgba(212, 175, 55, 1);
            transform: translateY(-4px);
            font-size: 14px;
            padding: 8px 12px;
        }
        
        /* Netflix-style info overlay */
        .room-card-info {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.7) 50%, transparent 100%);
            border-radius: 12px;
            opacity: 0;
            transition: opacity 400ms cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 16px;
            pointer-events: none;
            z-index: 2;
        }
        
        .room-carousel .room-card:hover .room-card-info {
            opacity: 1;
            pointer-events: all;
        }
        
        .room-card-info-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
            transform: translateY(10px);
            transition: transform 400ms cubic-bezier(0.4, 0, 0.2, 1) 100ms;
        }
        
        .room-carousel .room-card:hover .room-card-info-title {
            transform: translateY(0);
        }
        
        .room-card-info-features {
            display: flex;
            gap: 8px;
            font-size: 11px;
            color: rgba(255,255,255,0.8);
            transform: translateY(10px);
            transition: transform 400ms cubic-bezier(0.4, 0, 0.2, 1) 150ms;
        }
        
        .room-carousel .room-card:hover .room-card-info-features {
            transform: translateY(0);
        }
        
        .room-card-info-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            transform: translateY(10px);
            transition: transform 400ms cubic-bezier(0.4, 0, 0.2, 1) 200ms;
        }
        
        .room-carousel .room-card:hover .room-card-info-actions {
            transform: translateY(0);
        }
        
        .room-card-info-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.8);
            background: rgba(255,255,255,0.2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 250ms ease;
            backdrop-filter: blur(10px);
            pointer-events: all;
            font-size: 14px;
        }
        
        .room-card-info-btn:hover {
            background: rgba(255,255,255,0.9);
            color: #000;
            border-color: #fff;
            transform: scale(1.1);
        }

        .slider-btn { position: absolute; top: 50%; transform: translateY(-50%); width: 40px; height: 40px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.4); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(6px); transition: all 250ms ease; z-index: 20; }
        .slider-btn:hover { background: rgba(255,255,255,0.9); color: #000; transform: translateY(-50%) scale(1.1); }
        .slider-btn.prev { left: 6px; }
        .slider-btn.next { right: 6px; }
        .room-carousel .room-card.dim { transform: scale(0.92); opacity: 0.8; filter: grayscale(0.05); }
        
        /* Netflix-style animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideInRight {
            from { 
                opacity: 0;
                transform: translateX(100px);
            }
            to { 
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutRight {
            from { 
                opacity: 1;
                transform: translateX(0);
            }
            to { 
                opacity: 0;
                transform: translateX(100px);
            }
        }
        
        /* Netflix-style modal */
        .netflix-modal .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(8px);
            animation: fadeIn 300ms ease;
        }
        
        .netflix-modal .modal-content {
            position: relative;
            background: rgba(24, 24, 24, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            max-width: 850px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 64px rgba(0,0,0,0.8);
            animation: modalSlideIn 400ms cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .netflix-modal .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(0,0,0,0.7);
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: all 250ms ease;
            backdrop-filter: blur(10px);
        }
        
        .netflix-modal .modal-close:hover {
            background: rgba(255,255,255,0.9);
            color: #000;
            transform: scale(1.1);
        }
        
        .netflix-modal .modal-hero {
            height: 400px;
            border-radius: 16px 16px 0 0;
            display: flex;
            align-items: flex-end;
            padding: 40px;
            position: relative;
        }
        
        .netflix-modal .modal-hero-content h2 {
            font-size: 42px;
            font-family: 'Playfair Display', serif;
            color: #fff;
            margin-bottom: 20px;
            text-shadow: 0 4px 12px rgba(0,0,0,0.8);
        }
        
        .netflix-modal .modal-actions {
            display: flex;
            gap: 12px;
        }
        
        .netflix-modal .modal-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 6px;
            border: none;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 250ms ease;
        }
        
        .netflix-modal .modal-btn.primary {
            background: #fff;
            color: #000;
        }
        
        .netflix-modal .modal-btn.primary:hover {
            background: rgba(255,255,255,0.85);
            transform: scale(1.05);
        }
        
        .netflix-modal .modal-details {
            padding: 32px 40px 40px;
            color: #fff;
        }
        
        .netflix-modal .modal-details h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--gold);
        }
        
        .netflix-modal .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .netflix-modal .feature {
            background: rgba(255,255,255,0.08);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .netflix-modal .room-description {
            line-height: 1.6;
            color: rgba(255,255,255,0.8);
            font-size: 15px;
        }
        
        @media (max-width: 768px) {
            .netflix-modal .modal-content {
                width: 95%;
                max-height: 95vh;
            }
            
            .netflix-modal .modal-hero {
                height: 300px;
                padding: 24px;
            }
            
            .netflix-modal .modal-hero-content h2 {
                font-size: 28px;
            }
            
            .netflix-modal .modal-details {
                padding: 24px;
            }
            
            .netflix-modal .features-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
        }

        @media (max-width: 1024px) {
            .rooms-hero { grid-template-columns: 1fr; gap: 20px; }
            .room-info { text-align: center; }
            .room-desc { margin: 0 auto; }
        }

        .rooms h2 {
            font-size: 42px;
            font-weight: 300;
            margin-bottom: 60px;
        }

        .room-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .room-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            cursor: pointer;
        }

        .room-card.active {
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            transform: translateY(-4px);
        }

        .room-card:hover {
            transform: translateY(-10px);
        }

        .contact {
            padding: 100px 50px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .contact-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-top: 40px;
        }

        .contact-info h3,
        .contact-form h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--navy);
        }

        .contact-info p {
            margin-bottom: 15px;
            font-size: 16px;
        }

        .contact-form form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .contact-form input,
        .contact-form textarea {
            padding: 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            font-size: 16px;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        
        .contact-form input:focus,
        .contact-form textarea:focus {
            background: rgba(255, 255, 255, 0.8);
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }

        .contact-form textarea {
            resize: vertical;
            min-height: 100px;
        }

        .contact-form button {
            background: var(--gold);
            border: none;
            color: var(--navy);
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .contact-form button:hover {
            background: #c19b2e;
        }

        /* Team Swiper Section */
        .team-swiper-section {
            padding: 100px 50px;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            text-align: center;
            overflow-x: hidden;
            overflow-y: visible;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .team-swiper-section h2 {
            font-size: 42px;
            font-weight: 400;
            font-family: 'Playfair Display', serif;
            margin-bottom: 20px;
            color: var(--navy);
        }

        .team-swiper-section p {
            color: var(--muted);
            font-size: 18px;
            margin-bottom: 60px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .team-swiper-container {
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
            position: relative;
            padding: 60px 0 80px;
            perspective: 1200px;
            overflow: hidden;
        }

        .team-swiper-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            min-height: 500px;
            width: 100%;
            overflow: visible;
        }

        /* Team Card - Default (Side Cards) */
        .team-card {
            min-width: 260px;
            max-width: 260px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 25px 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: absolute;
            cursor: pointer;
            opacity: 0.5;
            transform: scale(0.85) translateY(20px);
            filter: blur(1px);
            z-index: 1;
        }

        .team-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--gold), #c19b2a);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.5s ease;
        }

        /* Center Card (Active) */
        .team-card.center {
            opacity: 1;
            transform: scale(1.15) translateY(0);
            filter: blur(0);
            z-index: 10;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25);
            min-width: 320px;
            max-width: 320px;
            padding: 35px 28px;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .team-card.center:hover {
            box-shadow: 0 30px 90px rgba(212, 175, 55, 0.3);
            transform: scale(1.18) translateY(-5px);
            border-color: rgba(212, 175, 55, 0.5);
        }

        .team-card.center::before {
            transform: scaleX(1);
            height: 5px;
        }

        /* Position cards */
        .team-card.left-2 {
            left: 8%;
            transform: scale(0.75) translateY(30px);
            opacity: 0.3;
            filter: blur(2px);
            z-index: 0;
        }

        .team-card.left-1 {
            left: 25%;
            transform: scale(0.85) translateY(20px);
            opacity: 0.6;
            filter: blur(1px);
            z-index: 5;
        }

        .team-card.center {
            left: 50%;
            transform: translateX(-50%) scale(1.15);
        }

        .team-card.right-1 {
            right: 25%;
            transform: scale(0.85) translateY(20px);
            opacity: 0.6;
            filter: blur(1px);
            z-index: 5;
        }

        .team-card.right-2 {
            right: 8%;
            transform: scale(0.75) translateY(30px);
            opacity: 0.3;
            filter: blur(2px);
            z-index: 0;
        }

        /* Hover effects for side cards */
        .team-card:not(.center):hover {
            opacity: 0.8;
            transform: scale(0.9) translateY(10px);
            filter: blur(0.5px);
        }

        /* Featured Card (CEO) */
        .team-card.featured {
            background: linear-gradient(135deg, var(--navy) 0%, #1a2942 100%);
            color: #fff;
            border: 3px solid var(--gold);
        }

        .team-card.featured::before {
            background: linear-gradient(90deg, var(--gold), #fff);
            height: 6px;
        }

        /* Avatar */
        .team-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(233, 236, 239, 0.8));
            backdrop-filter: blur(5px);
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: 700;
            color: var(--navy);
            border: 3px solid rgba(255,255,255,0.8);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.4s ease;
            position: relative;
        }

        .team-card.center .team-avatar {
            width: 100px;
            height: 100px;
            font-size: 38px;
            border: 4px solid var(--gold);
            transform: scale(1);
            box-shadow: 0 10px 32px rgba(212, 175, 55, 0.3);
            color: var(--gold);
            background: var(--navy);
        }

        .team-card.featured .team-avatar {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            color: var(--gold);
            border-color: var(--gold);
        }

        /* Badge */
        .team-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            background: var(--gold);
            color: var(--navy);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 3px 10px rgba(212, 175, 55, 0.4);
            animation: pulse 2s infinite;
        }

        .team-card.center .team-badge {
            width: 32px;
            height: 32px;
            font-size: 16px;
            top: -4px;
            right: -4px;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* Team Info */
        .team-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 6px;
            font-family: 'Playfair Display', serif;
            transition: font-size 0.4s ease;
        }

        .team-card.center .team-name {
            font-size: 22px;
        }

        .team-card.featured .team-name {
            color: #fff;
        }

        .team-position {
            font-size: 11px;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
            margin-bottom: 12px;
            transition: font-size 0.4s ease;
        }

        .team-card.center .team-position {
            font-size: 12px;
        }

        .team-description {
            font-size: 13px;
            line-height: 1.5;
            color: var(--muted);
            margin-bottom: 12px;
            min-height: 60px;
            transition: all 0.4s ease;
            opacity: 0.7;
        }

        .team-card.center .team-description {
            font-size: 14px;
            line-height: 1.6;
            opacity: 1;
            min-height: 70px;
        }

        .team-card.featured .team-description {
            color: rgba(255, 255, 255, 0.85);
        }

        .team-contact {
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .team-card.center .team-contact {
            padding-top: 16px;
            gap: 8px;
        }

        .team-card.featured .team-contact {
            border-top-color: rgba(255, 255, 255, 0.2);
        }

        .team-contact-item {
            font-size: 11px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .team-card.center .team-contact-item {
            font-size: 12px;
        }

        .team-card.featured .team-contact-item {
            color: rgba(255, 255, 255, 0.8);
        }

        .team-contact-item a {
            color: var(--gold);
            text-decoration: none;
            transition: color 0.3s;
            word-break: break-all;
        }

        .team-contact-item a:hover {
            color: #c19b2a;
            text-decoration: underline;
        }

        /* Navigation Buttons */
        .team-swiper-nav {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .team-swiper-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(5px);
            border: 2px solid var(--gold);
            color: var(--gold);
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .team-swiper-btn:hover {
            background: var(--gold);
            color: #fff;
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
        }

        .team-swiper-btn:active {
            transform: scale(0.95);
        }

        /* Dots Indicator */
        .team-swiper-dots {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .team-swiper-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .team-swiper-dot.active {
            background: var(--gold);
            width: 30px;
            border-radius: 5px;
        }

        .site-footer {
            background: rgba(15, 28, 45, 0.95);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 60px 50px 30px;
            margin-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .footer-content { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; max-width: 1400px; margin: 0 auto; }
        .footer-title { font-family: 'Playfair Display', serif; font-size: 28px; color: var(--gold); }
        .footer-text { margin-top: 10px; color: #e5e7eb; }
        .footer-contact p { margin: 6px 0; color: #e5e7eb; }
        .footer-copy { border-top: 1px solid rgba(255,255,255,.1); margin-top: 30px; padding-top: 20px; text-align: center; color: #cbd5e1; }

        .room-title {
            position: absolute;
            left: 20px;
            bottom: 20px;
            background: rgba(15, 28, 45, 0.7);
            color: #fff;
            padding: 8px 12px;
            font-size: 14px;
            letter-spacing: 1px;
            border-radius: 4px;
        }

        .room-img {
            width: 100%;
            height: 300px;
            background: linear-gradient(135deg, rgba(255,255,255,0.4), rgba(255,255,255,0.1));
            object-fit: cover;
            display: block;
        }

        .fade-in { opacity: 0; transform: translateY(14px); transition: opacity 600ms ease, transform 600ms ease; }
        .fade-in.visible { opacity: 1; transform: none; }
        .hero-inner { text-align: center; position: relative; z-index: 2; }
        .hero-word { display: inline-block; opacity: 0; transform: translateY(18px) scale(0.98); transition: opacity 500ms ease, transform 500ms ease; }
        .hero-word.show { opacity: 1; transform: none; }
        .hero-sub { color: #fff; font-size: 20px; margin-top: 10px; text-shadow: 0 2px 6px rgba(0,0,0,0.4); }
        [data-parallax] { will-change: transform; }
        
        /* Scroll Animation Classes */
        .scroll-fade-in {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        
        .scroll-fade-in.animate {
            opacity: 1;
            transform: translateY(0);
        }
        
        .scroll-slide-left {
            opacity: 0;
            transform: translateX(-60px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        
        .scroll-slide-left.animate {
            opacity: 1;
            transform: translateX(0);
        }
        
        .scroll-slide-right {
            opacity: 0;
            transform: translateX(60px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        
        .scroll-slide-right.animate {
            opacity: 1;
            transform: translateX(0);
        }
        
        .scroll-scale-up {
            opacity: 0;
            transform: scale(0.9);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        
        .scroll-scale-up.animate {
            opacity: 1;
            transform: scale(1);
        }
        
        .scroll-zoom-in {
            opacity: 0;
            transform: scale(0.8);
            transition: opacity 1s ease-out, transform 1s ease-out;
        }
        
        .scroll-zoom-in.animate {
            opacity: 1;
            transform: scale(1);
        }
        
        .scroll-rotate-in {
            opacity: 0;
            transform: rotate(-5deg) scale(0.95);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        
        .scroll-rotate-in.animate {
            opacity: 1;
            transform: rotate(0) scale(1);
        }
        
        /* Stagger animation delays */
        .stagger-1 { transition-delay: 0.1s; }
        .stagger-2 { transition-delay: 0.2s; }
        .stagger-3 { transition-delay: 0.3s; }
        .stagger-4 { transition-delay: 0.4s; }
        .stagger-5 { transition-delay: 0.5s; }
        
        /* Disable scroll animations on mobile for performance */
        @media (max-width: 768px) {
            .scroll-fade-in,
            .scroll-slide-left,
            .scroll-slide-right,
            .scroll-scale-up,
            .scroll-zoom-in,
            .scroll-rotate-in {
                opacity: 1;
                transform: none;
            }
        }
        .video-section { padding: 80px 50px; background: transparent; }
        .video-container { max-width: 1100px; margin: 0 auto; text-align: center; }
        .video-sub { color: var(--muted); margin-top: 10px; margin-bottom: 20px; }
        .vs-actions { display: flex; justify-content: center; margin-bottom: 20px; }
        .vs-upload { position: relative; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 30px; background: var(--gold); color: #000; cursor: pointer; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .vs-upload:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4); }
        .vs-upload input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .vs-player { max-width: 900px; margin: 0 auto; background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 16px; }
        .vs-video { width: 100%; height: auto; border-radius: 12px; background: #000; }
        .vs-meta { margin-top: 10px; color: var(--muted); font-size: 14px; }
        .vs-placeholder { display: inline-block; padding: 30px 24px; border-radius: 12px; background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(10px); color: var(--muted); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
        
        /* Video Grid Styles */
        .video-grid-container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        
        .featured-video {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 48px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .featured-video:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 50px rgba(0,0,0,0.15);
        }
        
        .featured-video-label {
            font-size: 14px;
            font-weight: 700;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .featured-video-player {
            width: 100%;
            height: auto;
            max-height: 600px;
            border-radius: 16px;
            background: #000;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }
        
        .featured-video-meta {
            margin-top: 16px;
            color: var(--muted);
            font-size: 14px;
            text-align: left;
            padding: 12px 16px;
            background: #f7f9fc;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
        }
        
        .video-grid-section {
            margin-top: 24px;
        }
        
        .video-grid-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 24px;
            text-align: left;
            font-family: 'Playfair Display', serif;
        }
        
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
        
        .video-grid-item {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .video-grid-item:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.15);
        }
        
        .video-grid-thumbnail {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #000;
            display: block;
        }
        
        .video-grid-item-name {
            padding: 12px 16px;
            font-size: 13px;
            color: var(--text);
            background: rgba(255, 255, 255, 0.4);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            font-family: 'Courier New', monospace;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Tablet Devices (768px - 1024px) */
        @media (max-width: 1024px) {
            .top-bar {
                padding: 10px 30px;
            }
            
            nav {
                padding: 20px 30px;
            }
            
            .hero h1 {
                font-size: 48px;
            }
            
            .hero p {
                font-size: 18px;
            }
            
            .booking-form {
                margin: -50px 30px 0;
                padding: 20px;
                flex-wrap: wrap;
            }
            
            .form-group {
                flex: 1 0 calc(50% - 10px);
                margin-bottom: 15px;
            }
            
            .search-btn {
                flex: 1 0 100%;
                margin-top: 10px;
            }
            
            .welcome {
                padding: 80px 30px;
                gap: 40px;
            }
            
            .welcome-text {
                padding-right: 20px;
            }
            
            .welcome h2 {
                font-size: 36px;
            }
            
            .room-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .welcome-video,
            .rooms,
            .contact {
                padding: 80px 30px;
            }
            
            .contact-content {
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
            
            .site-footer {
                padding: 50px 30px 30px;
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }

        /* Small Tablet (600px - 768px) */
        @media (max-width: 768px) {
            .top-bar {
                padding: 8px 20px;
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
            
            .contact-info {
                flex-direction: column;
                gap: 8px;
            }
            
            .auth-links {
                margin-top: 8px;
            }
            
            nav { position: relative; padding: 16px 20px; }
            .menu-toggle { display: inline-block; }
            .book-btn { display: none; }
            .nav-links { 
                display: none; 
                position: absolute; 
                top: 56px; 
                left: 20px; 
                right: 20px; 
                background: rgba(0,0,0,0.9); 
                backdrop-filter: blur(10px); 
                border-radius: 12px; 
                padding: 16px; 
                flex-direction: column; 
                gap: 8px; 
                box-shadow: 0 8px 32px rgba(0,0,0,0.3);
                border: 1px solid rgba(255,255,255,0.1);
                z-index: 1000;
            }
            .nav-links a { 
                font-size: 16px; 
                color: #fff; 
                padding: 12px 16px; 
                border-radius: 8px; 
                transition: background-color 0.3s; 
            }
            .nav-links a:hover { background: rgba(255,255,255,0.1); }
            .nav-links.show { display: flex; }

            .hero { height: 80vh; background-attachment: scroll; }
            .hero h1 { font-size: 36px; }

            .booking-form { flex-direction: column; width: calc(100% - 40px); margin: -40px 20px 0; gap: 12px; align-items: stretch; }
            .search-btn { width: 100%; }

            .welcome-video .video-container { max-width: none; margin: 0; text-align: left; }
            .welcome-video .video-grid-container { width: 100%; padding: 0; }
            .welcome-video .vs-player { max-width: none; width: 100%; margin: 0; }
            .video-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
            .featured-video { padding: 16px; margin-bottom: 32px; }
            .video-grid-title { font-size: 20px; }

            .welcome { grid-template-columns: 1fr; padding: 60px 20px; gap: 30px; }
            .welcome-text { padding-right: 0; }
            .welcome-images { grid-template-columns: 1fr; }
            .welcome-img { height: 220px; }
            .welcome-images .welcome-img:first-child { grid-column: auto; grid-row: auto; height: 220px; border-top-right-radius: 20px; border-bottom-right-radius: 20px; }
            .welcome-images .welcome-img:nth-child(2),
            .welcome-images .welcome-img:nth-child(3) { border-bottom-left-radius: 20px; border-top-left-radius: 20px; }

            .welcome-video { grid-template-columns: 1fr; padding: 60px 20px; }

            .rooms { padding: 60px 20px; }
            .room-grid { grid-template-columns: 1fr 1fr; }

            .team-swiper-section { padding: 60px 20px; overflow-x: hidden; }
            .team-swiper-container { padding: 40px 0 60px; }
            .team-swiper-wrapper { min-height: 450px; }
            .team-card { min-width: 220px; max-width: 220px; padding: 20px 16px; }
            .team-card.center { min-width: 270px; max-width: 270px; padding: 28px 22px; }
            .team-avatar { width: 65px; height: 65px; font-size: 24px; }
            .team-card.center .team-avatar { width: 85px; height: 85px; font-size: 32px; }
            .team-name { font-size: 16px; }
            .team-card.center .team-name { font-size: 19px; }
            .team-description { min-height: 50px; font-size: 12px; }
            .team-card.center .team-description { min-height: 60px; font-size: 13px; }
            
            /* Adjust card positions for tablet */
            .team-card.left-2 { left: 5%; }
            .team-card.left-1 { left: 20%; }
            .team-card.right-1 { right: 20%; }
            .team-card.right-2 { right: 5%; }

            .contact { padding: 60px 20px; }
            .contact-content { grid-template-columns: 1fr; gap: 24px; }

            .site-footer { padding: 40px 20px 20px; }
            .footer-content { grid-template-columns: 1fr; }
        }

        /* Small Mobile Devices (max-width: 480px) */
        @media (max-width: 480px) {
            .top-bar {
                padding: 6px 16px;
                font-size: 11px;
            }
            
            .contact-info {
                flex-direction: column;
                gap: 6px;
            }
            
            .contact-info span {
                font-size: 10px;
                justify-content: center;
            }
            
            .auth-links {
                display: flex;
                gap: 12px;
                justify-content: center;
            }
            
            .auth-links a {
                font-size: 10px;
                margin-left: 0;
                padding: 4px 8px;
                background: rgba(255,255,255,0.1);
                border-radius: 4px;
            }
            
            nav {
                padding: 12px 16px;
            }
            
            .logo {
                font-size: 20px;
            }
            
            .logo span {
                font-size: 7px;
                letter-spacing: 1px;
            }
            
            .menu-toggle {
                font-size: 20px;
            }
            
            .nav-links {
                top: 50px;
                left: 16px;
                right: 16px;
                padding: 10px;
            }
            
            .nav-links a {
                font-size: 14px;
                padding: 8px;
            }
            
            .hero {
                height: 70vh;
                padding: 0 16px;
            }
            
            .hero h1 {
                font-size: 24px;
                margin-bottom: 15px;
            }
            
            .hero p {
                font-size: 14px;
                margin-bottom: 30px;
            }
            
            .explore-btn {
                padding: 12px 30px;
                font-size: 12px;
            }
            
            .booking-form {
                margin: -25px 12px 0;
                padding: 16px;
                gap: 10px;
                align-items: stretch;
            }
            
            .form-group {
                flex: 1 0 100%;
            }
            
            .form-group label {
                font-size: 11px;
            }
            
            .form-group input,
            .form-group select {
                padding: 8px;
                font-size: 12px;
            }
            
            .search-btn {
                padding: 10px 20px;
                font-size: 12px;
                width: 100%;
            }
            
            .welcome,
            .welcome-video,
            .rooms,
            .team-swiper-section,
            .contact {
                padding: 30px 12px;
            }

            .team-swiper-section { padding: 50px 15px; overflow-x: hidden; }
            .team-swiper-section h2 { font-size: 28px; }
            .team-swiper-section p { font-size: 14px; margin-bottom: 30px; }
            .team-swiper-container { padding: 30px 0 50px; overflow: hidden; }
            .team-swiper-wrapper { min-height: 420px; padding: 20px 5px; }
            .team-card { min-width: 180px; max-width: 180px; padding: 16px 12px; }
            .team-card.center { min-width: 240px; max-width: 240px; padding: 24px 18px; transform: translateX(-50%) scale(1.08); }
            .team-avatar { width: 55px; height: 55px; font-size: 22px; }
            .team-card.center .team-avatar { width: 75px; height: 75px; font-size: 30px; }
            .team-name { font-size: 15px; }
            .team-card.center .team-name { font-size: 18px; }
            .team-position { font-size: 10px; }
            .team-card.center .team-position { font-size: 11px; }
            .team-description { min-height: auto; font-size: 11px; line-height: 1.4; }
            .team-card.center .team-description { font-size: 12px; line-height: 1.5; }
            .team-contact { padding-top: 10px; }
            .team-contact-item { font-size: 9px; }
            .team-card.center .team-contact-item { font-size: 10px; }
            .team-swiper-btn { width: 42px; height: 42px; font-size: 16px; }
            
            /* Adjust positions for mobile - only show 3 cards */
            .team-card.left-2, .team-card.right-2 { 
                opacity: 0 !important; 
                pointer-events: none;
                display: none;
            }
            .team-card.left-1 { left: 2%; transform: scale(0.65) translateY(20px); }
            .team-card.right-1 { right: 2%; transform: scale(0.65) translateY(20px); }

            .welcome-video .video-container { max-width: none; margin: 0; }
            .welcome-video .video-grid-container { width: 100%; padding: 0; }
            .welcome-video .vs-player { max-width: none; width: 100%; margin: 0; padding: 12px; }
            .video-grid { grid-template-columns: 1fr; gap: 12px; }
            .featured-video { padding: 12px; margin-bottom: 24px; }
            .featured-video-player { max-height: 300px; }
            .video-grid-title { font-size: 18px; margin-bottom: 16px; }
            .video-grid-thumbnail { height: 150px; }
            
            .welcome h2 {
                font-size: 28px;
                margin-bottom: 20px;
            }
            
            .welcome p {
                font-size: 14px;
            }
            
            .welcome-images {
                gap: 12px;
            }
            
            .welcome-img {
                height: 150px;
            }
            .welcome-images .welcome-img:first-child { grid-column: auto; grid-row: auto; height: 150px; border-radius: 20px; }
            
            .room-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .room-img { height: 220px; }
            
            .room-card {
                padding: 20px;
            }
            
            .room-card h3 {
                font-size: 18px;
            }
            
            .room-price {
                font-size: 20px;
            }
            
            .contact-content {
                gap: 20px;
            }
            
            .contact-info-card {
                padding: 20px;
            }
            
            .contact-info-card h3 {
                font-size: 16px;
            }
            
            .site-footer {
                padding: 25px 12px 12px;
            }
            
            .footer-content {
                gap: 20px;
            }
            
            .footer-section h3 {
                font-size: 16px;
                margin-bottom: 12px;
            }
            
            .footer-section p,
            .footer-section a {
                font-size: 12px;
            }
            
            .footer-bottom {
                font-size: 10px;
                padding: 15px 0 0;
            }
        }

        /* Extra Small Devices (max-width: 375px) */
        @media (max-width: 375px) {
            .hero h1 {
                font-size: 20px;
            }
            
            .hero p {
                font-size: 12px;
            }
            
            .explore-btn {
                padding: 10px 24px;
                font-size: 11px;
            }
            
            .booking-form {
                margin: -20px 8px 0;
                padding: 12px;
            }
            
            .form-group input,
            .form-group select {
                padding: 6px;
                font-size: 11px;
            }
            
            .welcome h2 {
                font-size: 24px;
            }
            
            .welcome p {
                font-size: 13px;
            }
            
            .welcome-img {
                height: 120px;
            }
            
            .room-img {
                height: 180px;
            }
        }

        /* Touch-friendly improvements */
        @media (max-width: 768px) {
            .book-btn,
            .explore-btn,
            .search-btn,
            .nav-links a,
            .contact-form button {
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .form-group input,
            .form-group select,
            .contact-form input,
            .contact-form textarea {
                min-height: 44px;
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .menu-toggle {
                min-width: 44px;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        /* Landscape orientation adjustments */
        @media (max-height: 600px) and (orientation: landscape) {
            .team-swiper-section { padding: 40px 15px; }
            .team-swiper-container { padding: 20px 0 40px; }
            .team-swiper-wrapper { min-height: 350px; }
            .team-card { min-width: 160px; max-width: 160px; padding: 14px 10px; }
            .team-card.center { min-width: 210px; max-width: 210px; padding: 20px 16px; }
            .team-avatar { width: 50px; height: 50px; font-size: 20px; }
            .team-card.center .team-avatar { width: 65px; height: 65px; font-size: 26px; }
            .team-name { font-size: 14px; }
            .team-card.center .team-name { font-size: 16px; }
            .team-position { font-size: 9px; }
            .team-description { font-size: 10px; line-height: 1.3; min-height: auto; }
            .team-card.center .team-description { font-size: 11px; }
            .team-contact { padding-top: 8px; gap: 4px; }
            .team-contact-item { font-size: 9px; }
            .team-swiper-nav { margin-top: 15px; }
            .team-swiper-btn { width: 38px; height: 38px; font-size: 14px; }
            
            /* Tighter positioning for landscape */
            .team-card.left-2, .team-card.right-2 { display: none; }
            .team-card.left-1 { left: 5%; transform: scale(0.7) translateY(15px); }
            .team-card.right-1 { right: 5%; transform: scale(0.7) translateY(15px); }
        }

        /* Extra small landscape devices */
        @media (max-height: 450px) and (orientation: landscape) {
            .team-swiper-wrapper { min-height: 300px; }
            .team-card { min-width: 140px; max-width: 140px; padding: 12px 8px; }
            .team-card.center { min-width: 190px; max-width: 190px; padding: 18px 14px; }
            .team-avatar { width: 45px; height: 45px; font-size: 18px; }
            .team-card.center .team-avatar { width: 60px; height: 60px; font-size: 24px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="top-bar">
            <div class="contact-info">
                <span>📞 +012 3456 789</span>
                <span>✉ contact@company.com</span>
            </div>
            <div class="auth-links">
                <a href="auth/login.php" id="headerLoginLink">Login</a>
                <a href="<?php echo isset($_SESSION['registered_guest_id']) ? 'guest/dashboard.php' : '#guest'; ?>" id="headerPortalLink">Guest Portal</a>
            </div>
        </div>
        <nav>
            <div class="logo">
                Romancy
                <span>HOTEL & RESORT</span>
            </div>
            <button class="menu-toggle" id="menuToggle">☰</button>
            <ul class="nav-links">
                <li><a href="#home">HOME</a></li>
                <li><a href="#rooms">ROOMS</a></li>
                <li><a href="#about">ABOUT US</a></li>
                <li><a href="#team">OUR TEAM</a></li>
                <li><a href="#contact">CONTACT US</a></li>
                <li><a href="#pages">PAGES</a></li>
            </ul>
            <button class="book-btn">BOOK NOW</button>
        </nav>
    </header>

    <section id="home" class="hero"><div id="hero-root"></div></section>

    <div class="booking-form scroll-scale-up">
        <div class="form-group">
            <label>Check in</label>
            <input type="date" value="2024-01-01">
        </div>
        <div class="form-group">
            <label>Check out</label>
            <input type="date" value="2024-01-05">
        </div>
        <div class="form-group">
            <label>Guests</label>
            <select>
                <option>2 Persons</option>
                <option>3 Persons</option>
                <option>4 Persons</option>
            </select>
        </div>
        <div class="form-group">
            <label>Beds</label>
            <select>
                <option>1</option>
                <option>2</option>
                <option>3</option>
            </select>
        </div>
        <div class="form-group">
            <label>Baths</label>
            <select>
                <option>1</option>
                <option>2</option>
                <option>3</option>
            </select>
        </div>
        <button class="search-btn">🔍 Search</button>
    </div>

    <section id="about" class="welcome">
        <div class="welcome-text scroll-slide-left" data-parallax="0.03">
            <div class="section-label">WELCOME TO ROMANCY</div>
            <h2>Our Hotel has been present for over 20 years.</h2>
            <h3>We make the best for all our customers.</h3>
            <p>Our objective at Romancy is to bring together our visitor's societies and spirits with our own, communicating enthusiasm and liberality in the food we share. Official Chef and Owner Philippe Massoud expertly creates a menu of Lebanese, Levantine, Mediterranean motivated food that is both delightful and wholesome.</p>
            <div class="ceo-info">
                <div class="ceo-img"></div>
                <div class="ceo-details">
                    <h4>John Smith</h4>
                    <p>CEO, Romancy</p>
                </div>
                <div class="signature">John Smith</div>
            </div>
        </div>
        <div class="welcome-images scroll-slide-right">
            <img class="welcome-img stagger-1" data-parallax="0.06" src="https://images.unsplash.com/photo-1519822472724-471f13e1c8b8?auto=format&fit=crop&w=1200&q=80" alt="Resort pool">
            <img class="welcome-img stagger-2" data-parallax="0.10" src="https://images.unsplash.com/photo-1519710164239-d5cdcf0e9f9f?auto=format&fit=crop&w=1200&q=80" alt="Guest enjoying services">
            <img class="welcome-img stagger-3" data-parallax="0.08" src="https://images.unsplash.com/photo-1513267048331-561420f36b66?auto=format&fit=crop&w=1200&q=80" alt="Hotel amenities">
        </div>
    </section>

    <hr class="section-divider scroll-fade-in">
    <section id="pages" class="welcome-video">
        <div class="welcome-text scroll-slide-left" data-parallax="0.02">
            <div class="section-label">SHOWCASE</div>
            <h2>Experience Our Property In Motion</h2>
            <h3>Watch the latest uploaded video</h3>
            <p>We regularly share video highlights of our hotel and amenities. Enjoy a quick look at the latest upload right here.</p>
        </div>
        <div class="scroll-zoom-in">
            <div id="video-showcase-root"></div>
        </div>
    </section>

    <section id="rooms" class="rooms reveal">
        <div class="rooms-bg" id="roomsBg"></div>
        <div class="rooms-bg rooms-bg-new" id="roomsBgNew"></div>
        <div class="rooms-overlay"></div>
        <div class="rooms-content">
            <div class="section-label scroll-fade-in" style="color: #999;">THE BEST YOUR FAMILY</div>
            <h2 class="scroll-fade-in">Our Favorite Room</h2>
            <div class="rooms-hero">
                <div class="room-info scroll-slide-left">
                    <h3 class="room-title-large" id="roomTitleLarge">Deluxe Room</h3>
                    <p class="room-desc">Click a room card to preview it on the background and see details.</p>
                    <div class="room-actions">
                        <button class="primary" id="bookNowBtn">
                            <span class="btn-icon">▶</span>
                            <span>BOOK NOW</span>
                        </button>
                        <button class="secondary" id="viewDetailsBtn">
                            <span class="btn-icon">ⓘ</span>
                            <span>MORE INFO</span>
                        </button>
                    </div>
                </div>
                <div class="room-carousel scroll-slide-right">
                    <div class="glass-panel"></div>
                    <div class="carousel-track" id="carouselTrack">
                        <div class="room-card">
                            <img class="room-img" src="https://images.unsplash.com/photo-1559599101-f5f1b7a32f6c?auto=format&fit=crop&w=1200&q=80" alt="Deluxe Room">
                            <div class="room-title">Deluxe Room</div>
                            <div class="room-card-info">
                                <div class="room-card-info-title">Deluxe Room</div>
                                <div class="room-card-info-features">
                                    <span>🛏️ King Bed</span>
                                    <span>📺 Smart TV</span>
                                    <span>❄️ AC</span>
                                </div>
                                <div class="room-card-info-actions">
                                    <button class="room-card-info-btn" title="Play Preview">▶</button>
                                    <button class="room-card-info-btn" title="More Info">ⓘ</button>
                                </div>
                            </div>
                        </div>
                        <div class="room-card">
                            <img class="room-img" src="https://images.unsplash.com/photo-1560067174-89488f1f8579?auto=format&fit=crop&w=1200&q=80" alt="Family Suite">
                            <div class="room-title">Family Suite</div>
                            <div class="room-card-info">
                                <div class="room-card-info-title">Family Suite</div>
                                <div class="room-card-info-features">
                                    <span>🛏️ 2 Beds</span>
                                    <span>👨‍👩‍👧‍👦 Family</span>
                                    <span>🏊 Pool View</span>
                                </div>
                                <div class="room-card-info-actions">
                                    <button class="room-card-info-btn" title="Play Preview">▶</button>
                                    <button class="room-card-info-btn" title="More Info">ⓘ</button>
                                </div>
                            </div>
                        </div>
                        <div class="room-card">
                            <img class="room-img" src="https://images.unsplash.com/photo-1564540572970-df0e80ef8b3d?auto=format&fit=crop&w=1200&q=80" alt="Twin Room">
                            <div class="room-title">Twin Room</div>
                            <div class="room-card-info">
                                <div class="room-card-info-title">Twin Room</div>
                                <div class="room-card-info-features">
                                    <span>🛏️ Twin Beds</span>
                                    <span>🌅 City View</span>
                                    <span>☕ Mini Bar</span>
                                </div>
                                <div class="room-card-info-actions">
                                    <button class="room-card-info-btn" title="Play Preview">▶</button>
                                    <button class="room-card-info-btn" title="More Info">ⓘ</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="slider-btn prev" id="roomsPrev">‹</button>
                    <button class="slider-btn next" id="roomsNext">›</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Swiper Section -->
    <section id="team" class="team-swiper-section reveal">
        <div class="section-label scroll-fade-in">OUR LEADERSHIP</div>
        <h2 class="scroll-fade-in">Meet Our Team</h2>
        <p class="scroll-fade-in">Dedicated professionals committed to providing exceptional hospitality experiences</p>
        
        <div class="team-swiper-container scroll-fade-in">
            <div class="team-swiper-wrapper" id="teamSwiperWrapper">
                <!-- CEO Card -->
                <div class="team-card featured">
                    <div class="team-avatar">
                        JS
                        <div class="team-badge">★</div>
                    </div>
                    <div class="team-name">John Smith</div>
                    <div class="team-position">Chief Executive Officer</div>
                    <div class="team-description">
                        Leading Romancy with over 25 years of hospitality excellence. Passionate about creating unforgettable guest experiences and fostering a culture of innovation and service.
                    </div>
                    <div class="team-contact">
                        <div class="team-contact-item">
                            📧 <a href="mailto:john.smith@romancy.com">john.smith@romancy.com</a>
                        </div>
                        <div class="team-contact-item">
                            📞 +012 3456 789 ext. 101
                        </div>
                    </div>
                </div>

                <!-- COO Card -->
                <div class="team-card">
                    <div class="team-avatar">SD</div>
                    <div class="team-name">Sarah Davis</div>
                    <div class="team-position">Chief Operating Officer</div>
                    <div class="team-description">
                        Oversees daily operations ensuring seamless service delivery. Expert in operational efficiency with 15+ years in luxury hospitality management.
                    </div>
                    <div class="team-contact">
                        <div class="team-contact-item">
                            📧 <a href="mailto:sarah.davis@romancy.com">sarah.davis@romancy.com</a>
                        </div>
                        <div class="team-contact-item">
                            📞 +012 3456 789 ext. 102
                        </div>
                    </div>
                </div>

                <!-- CFO Card -->
                <div class="team-card">
                    <div class="team-avatar">MJ</div>
                    <div class="team-name">Michael Johnson</div>
                    <div class="team-position">Chief Financial Officer</div>
                    <div class="team-description">
                        Manages financial strategy and planning. Brings 20 years of financial expertise in the hospitality sector, ensuring sustainable growth and profitability.
                    </div>
                    <div class="team-contact">
                        <div class="team-contact-item">
                            📧 <a href="mailto:michael.johnson@romancy.com">michael.johnson@romancy.com</a>
                        </div>
                        <div class="team-contact-item">
                            📞 +012 3456 789 ext. 103
                        </div>
                    </div>
                </div>

                <!-- CMO Card -->
                <div class="team-card">
                    <div class="team-avatar">EW</div>
                    <div class="team-name">Emily Wilson</div>
                    <div class="team-position">Chief Marketing Officer</div>
                    <div class="team-description">
                        Drives brand strategy and digital innovation. Award-winning marketer with expertise in luxury brand positioning and customer engagement strategies.
                    </div>
                    <div class="team-contact">
                        <div class="team-contact-item">
                            📧 <a href="mailto:emily.wilson@romancy.com">emily.wilson@romancy.com</a>
                        </div>
                        <div class="team-contact-item">
                            📞 +012 3456 789 ext. 104
                        </div>
                    </div>
                </div>

                <!-- Guest Services Manager -->
                <div class="team-card">
                    <div class="team-avatar">RB</div>
                    <div class="team-name">Robert Brown</div>
                    <div class="team-position">Guest Services Manager</div>
                    <div class="team-description">
                        Ensures exceptional guest experiences from check-in to check-out. Dedicated to personalized service with 12 years in front desk and concierge operations.
                    </div>
                    <div class="team-contact">
                        <div class="team-contact-item">
                            📧 <a href="mailto:robert.brown@romancy.com">robert.brown@romancy.com</a>
                        </div>
                        <div class="team-contact-item">
                            📞 +012 3456 789 ext. 201
                        </div>
                    </div>
                </div>

                <!-- F&B Director -->
                <div class="team-card">
                    <div class="team-avatar">LM</div>
                    <div class="team-name">Lisa Martinez</div>
                    <div class="team-position">Food & Beverage Director</div>
                    <div class="team-description">
                        Curates exceptional dining experiences. Culinary expert with international training, specializing in Mediterranean and fusion cuisine for discerning guests.
                    </div>
                    <div class="team-contact">
                        <div class="team-contact-item">
                            📧 <a href="mailto:lisa.martinez@romancy.com">lisa.martinez@romancy.com</a>
                        </div>
                        <div class="team-contact-item">
                            📞 +012 3456 789 ext. 202
                        </div>
                    </div>
                </div>

                <!-- Facilities Manager -->
                <div class="team-card">
                    <div class="team-avatar">DT</div>
                    <div class="team-name">David Taylor</div>
                    <div class="team-position">Facilities Manager</div>
                    <div class="team-description">
                        Maintains pristine property standards. Engineering specialist ensuring all facilities operate flawlessly with proactive maintenance and sustainability focus.
                    </div>
                    <div class="team-contact">
                        <div class="team-contact-item">
                            📧 <a href="mailto:david.taylor@romancy.com">david.taylor@romancy.com</a>
                        </div>
                        <div class="team-contact-item">
                            📞 +012 3456 789 ext. 203
                        </div>
                    </div>
                </div>

                <!-- HR Director -->
                <div class="team-card">
                    <div class="team-avatar">JA</div>
                    <div class="team-name">Jennifer Anderson</div>
                    <div class="team-position">HR Director</div>
                    <div class="team-description">
                        Builds and nurtures our talented team. HR professional focused on employee development, workplace culture, and creating a supportive work environment.
                    </div>
                    <div class="team-contact">
                        <div class="team-contact-item">
                            📧 <a href="mailto:jennifer.anderson@romancy.com">jennifer.anderson@romancy.com</a>
                        </div>
                        <div class="team-contact-item">
                            📞 +012 3456 789 ext. 204
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="team-swiper-nav">
                <button class="team-swiper-btn" id="teamPrevBtn" aria-label="Previous">‹</button>
                <button class="team-swiper-btn" id="teamNextBtn" aria-label="Next">›</button>
            </div>

            <!-- Dots Indicator -->
            <div class="team-swiper-dots" id="teamSwiperDots"></div>
        </div>
    </section>
    
    <section id="contact" class="contact">
        <div class="section-label scroll-fade-in">CONTACT US</div>
        <h2 class="scroll-fade-in">Get In Touch</h2>
        <div class="contact-content">
            <div class="contact-info scroll-slide-left">
                <h3>Contact Information</h3>
                <p><strong>📞 Phone:</strong> <span id="contact-phone">+012 3456 789</span></p>
                <p><strong>✉ Email:</strong> <span id="contact-email">contact@company.com</span></p>
                <p><strong>📍 Address:</strong> <span id="contact-address">123 Hotel Street, Resort City</span></p>
            </div>
            <div class="contact-form scroll-slide-right">
                <h3>Send us a Message</h3>
                <form>
                    <input type="text" placeholder="Your Name" required>
                    <input type="email" placeholder="Your Email" required>
                    <textarea placeholder="Your Message" rows="4" required></textarea>
                    <button type="submit">Send Message</button>
                </form>
            </div>
        </div>
    </section>
    
    <footer class="site-footer reveal">
        <div class="footer-content">
            <div>
                <div class="footer-title" id="footer-title">Romancy</div>
                <p class="footer-text" id="footer-text">Luxury hotel and resort providing exceptional experiences for over 20 years.</p>
            </div>
            <div class="footer-contact">
                <p id="footer-phone">📞 +012 3456 789</p>
                <p id="footer-email">✉ contact@company.com</p>
                <p id="footer-address">📍 123 Hotel Street, Resort City</p>
            </div>
        </div>
        <div class="footer-copy" id="footer-copy">© 2025 Romancy. All rights reserved.</div>
    </footer>

<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script>
const { useEffect, useState, useRef, createElement: h } = React;

function FadeIn({ children, delay = 0 }) {
  const ref = useRef(null);
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    const obs = new IntersectionObserver(([entry]) => { if (entry.isIntersecting) setVisible(true); }, { threshold: 0.2 });
    if (ref.current) obs.observe(ref.current);
    return () => obs.disconnect();
  }, []);
  return h('div', { ref, className: "fade-in" + (visible ? " visible" : ""), style: { transitionDelay: `${delay}ms` } }, children);
}

function Hero({ words = ['Explore!','Discover!','Live!'], sub = 'The best hotel for your family', cta = 'EXPLORE ROOMS', promo = '' }) {
  const [count, setCount] = useState(0);
  useEffect(() => {
    const id = setInterval(() => setCount(c => Math.min(c + 1, words.length)), 450);
    return () => clearInterval(id);
  }, [words.length]);
  return h('div', { className: "hero-inner", 'data-parallax': "0.08" },
    h('h1', null, words.map((w, i) => h('span', { key: i, className: "hero-word" + (i < count ? " show" : "") }, w + ' '))),
    h(FadeIn, { delay: 600 }, h('p', { className: "hero-sub" }, sub)),
    h(FadeIn, { delay: 900 }, h('button', { className: "explore-btn" }, cta)),
    promo ? h(FadeIn, { delay: 1100 }, h('div', { className: 'hero-promo' }, promo)) : null
  );
}

function VideoSection() {
  const [src, setSrc] = useState(null);
  const [name, setName] = useState('');
  useEffect(() => {
    fetch('api/videos.php').then(r => r.json()).then(d => {
      if (d && d.ok && d.latest) {
        setSrc(d.latest);
        const parts = d.latest.split('/');
        setName(parts[parts.length - 1]);
      }
    }).catch(() => {});
  }, []);
  return h('div', { className: "video-container" },
    h('div', { className: "section-label", style: { color: '#999' } }, 'SHOWCASE'),
    h('h2', null, 'Latest Uploaded Video'),
    h('p', { className: "video-sub" }, 'This section displays the most recently uploaded video.'),
    src ? h('div', { className: "vs-player fade-in visible" },
      h('video', { className: "vs-video", src, controls: true, playsInline: true }),
      h('div', { className: "vs-meta" }, name)
    ) : h(FadeIn, null, h('div', { className: "vs-placeholder" }, 'No uploaded video available'))
  );
}

function VideoShowcase() {
  const [videos, setVideos] = useState([]);
  const [latest, setLatest] = useState(null);
  
  useEffect(() => {
    fetch('api/videos.php').then(r => r.json()).then(d => {
      if (d && d.ok) {
        const allVideos = (d.files || []).map(name => ({
          name,
          src: 'uploads/videos/' + name
        }));
        
        if (d.latest) {
          setLatest({ src: d.latest, name: d.latest.split('/').pop() });
          // Filter out the latest from the grid
          const others = allVideos.filter(v => v.src !== d.latest);
          setVideos(others);
        } else {
          setVideos(allVideos);
        }
      }
    }).catch(() => {});
  }, []);
  
  return h('div', { className: "video-grid-container fade-in visible" },
    latest ? h('div', { className: "featured-video" },
      h('div', { className: "featured-video-label" }, '🎬 Latest Upload'),
      h('video', { className: "featured-video-player", src: latest.src, controls: true, playsInline: true }),
      h('div', { className: "featured-video-meta" }, latest.name)
    ) : h('div', { className: "vs-placeholder" }, 'No videos available'),
    
    videos.length > 0 && h('div', { className: "video-grid-section" },
      h('h3', { className: "video-grid-title" }, 'Previous Uploads'),
      h('div', { className: "video-grid" },
        videos.map((video, i) => 
          h('div', { key: i, className: "video-grid-item" },
            h('video', { 
              className: "video-grid-thumbnail", 
              src: video.src, 
              controls: true, 
              playsInline: true,
              preload: "metadata"
            }),
            h('div', { className: "video-grid-item-name" }, video.name)
          )
        )
      )
    )
  );
}

  fetch('api/landing.php').then(r => r.json()).then(d => {
    const c = d && d.content ? d.content : {};
    const heroRoot = document.getElementById('hero-root');
    if (heroRoot) ReactDOM.createRoot(heroRoot).render(h(Hero, { words: c.hero_words || ['Explore!','Discover!','Live!'], sub: c.hero_subtext || 'The best hotel for your family', cta: c.cta_label || 'EXPLORE ROOMS', promo: c.hero_promo_text || '' }));
    const heroSection = document.querySelector('.hero'); if (heroSection && c.hero_bg_url) heroSection.style.background = 'linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.4)), url(' + c.hero_bg_url + ')';
    const bkt = document.getElementById('booking-title'); if (bkt && c.booking_title) bkt.textContent = c.booking_title;
    const bks = document.getElementById('booking-subtitle'); if (bks && c.booking_subtitle) bks.textContent = c.booking_subtitle;
    const ow = document.getElementById('offer-weekend'); if (ow && c.offer_weekend_text) { ow.textContent = c.offer_weekend_text; ow.style.display = 'inline-block'; }
    const oe = document.getElementById('offer-early'); if (oe && c.offer_early_text) { oe.textContent = c.offer_early_text; oe.style.display = 'inline-block'; }
    const wl = document.querySelector('.welcome .section-label'); if (wl && c.welcome_label) wl.textContent = c.welcome_label;
    const wt = document.querySelector('.welcome h2'); if (wt && c.welcome_title) wt.textContent = c.welcome_title;
    const ws = document.querySelector('.welcome h3'); if (ws && c.welcome_subtitle) ws.textContent = c.welcome_subtitle;
    const wp = document.querySelector('.welcome p'); if (wp && c.welcome_paragraph) wp.textContent = c.welcome_paragraph;
    const ceoN = document.querySelector('.ceo-details h4'); if (ceoN && c.ceo_name) ceoN.textContent = c.ceo_name;
  const ceoT = document.querySelector('.ceo-details p'); if (ceoT && c.ceo_title) ceoT.textContent = c.ceo_title;
  const im1 = document.querySelector('.welcome-images .welcome-img:nth-child(1)'); if (im1 && c.welcome_img_1) im1.src = c.welcome_img_1;
  const im2 = document.querySelector('.welcome-images .welcome-img:nth-child(2)'); if (im2 && c.welcome_img_2) im2.src = c.welcome_img_2;
  const im3 = document.querySelector('.welcome-images .welcome-img:nth-child(3)'); if (im3 && c.welcome_img_3) im3.src = c.welcome_img_3;
  const svl = document.querySelector('.welcome-video .section-label'); if (svl && c.showcase_label) svl.textContent = c.showcase_label;
  const svt = document.querySelector('.welcome-video h2'); if (svt && c.showcase_title) svt.textContent = c.showcase_title;
  const svs = document.querySelector('.welcome-video h3'); if (svs && c.showcase_subtitle) svs.textContent = c.showcase_subtitle;
  const svp = document.querySelector('.welcome-video p'); if (svp && c.showcase_paragraph) svp.textContent = c.showcase_paragraph;
  const showcaseRoot = document.getElementById('video-showcase-root');
  if (showcaseRoot) ReactDOM.createRoot(showcaseRoot).render(h(VideoShowcase));

  const logoEl = document.querySelector('.logo'); if (logoEl && c.footer_title) { logoEl.innerHTML = (c.footer_title) + '<span>HOTEL & RESORT</span>'; }
  if (c.footer_title) { try { document.title = c.footer_title; } catch(e){} }
  const hbPhone = document.querySelector('.top-bar .contact-info span:nth-child(1)'); if (hbPhone && c.footer_phone) hbPhone.textContent = '📞 ' + c.footer_phone;
  const hbEmail = document.querySelector('.top-bar .contact-info span:nth-child(2)'); if (hbEmail && c.footer_email) hbEmail.textContent = '✉ ' + c.footer_email;

  const ftTitle = document.getElementById('footer-title'); if (ftTitle && c.footer_title) ftTitle.textContent = c.footer_title;
  const ftText = document.getElementById('footer-text'); if (ftText && c.footer_text) ftText.textContent = c.footer_text;
  const ftPhone = document.getElementById('footer-phone'); if (ftPhone && c.footer_phone) ftPhone.textContent = '📞 ' + c.footer_phone;
  const ftEmail = document.getElementById('footer-email'); if (ftEmail && c.footer_email) ftEmail.textContent = '✉ ' + c.footer_email;
  const ftAddr = document.getElementById('footer-address'); if (ftAddr && c.footer_address) ftAddr.textContent = '📍 ' + c.footer_address;
  const ftCopy = document.getElementById('footer-copy'); if (ftCopy && c.footer_copy) ftCopy.textContent = c.footer_copy;

  const teamLabel = document.querySelector('#team .section-label'); if (teamLabel && c.team_label) teamLabel.textContent = c.team_label;
  const teamTitle = document.querySelector('#team h2'); if (teamTitle && c.team_title) teamTitle.textContent = c.team_title;
  const teamDesc = document.querySelector('#team p'); if (teamDesc && c.team_description) teamDesc.textContent = c.team_description;
  const teamWrapper = document.getElementById('teamSwiperWrapper');
  if (teamWrapper) {
    Array.from(teamWrapper.querySelectorAll('.team-card')).forEach(card => {
      card.style.display = 'none';
      const nameEl = card.querySelector('.team-name');
      const posEl = card.querySelector('.team-position');
      const bioEl = card.querySelector('.team-description');
      const emailLink = card.querySelector('.team-contact .team-contact-item a');
      const phoneItem = card.querySelectorAll('.team-contact .team-contact-item')[1];
      if (nameEl) nameEl.textContent = '';
      if (posEl) posEl.textContent = '';
      if (bioEl) bioEl.textContent = '';
      if (emailLink) { emailLink.textContent = ''; emailLink.removeAttribute('href'); }
      if (phoneItem) { phoneItem.textContent = ''; }
    });
  }
  const dynamicMembers = Array.isArray(c.team_members) ? c.team_members.filter(m => m && (m.name || m.position || m.bio || m.email || m.phone)) : [];
  if (dynamicMembers.length && teamWrapper) {
    const dots = document.getElementById('teamSwiperDots');
    if (dots) dots.innerHTML = '';
    teamWrapper.innerHTML = '';
    dynamicMembers.forEach((m) => {
      const initials = (m.initials && m.initials.trim()) || (m.name ? m.name.split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase() : '');
      const card = document.createElement('div');
      card.className = 'team-card';
      card.innerHTML = `
        <div class="team-avatar">${initials || ''}</div>
        <div class="team-name"></div>
        <div class="team-position"></div>
        <div class="team-description"></div>
        <div class="team-contact">
          <div class="team-contact-item">📧 <a href="#"></a></div>
          <div class="team-contact-item"></div>
        </div>
      `;
      const nameEl = card.querySelector('.team-name');
      const posEl = card.querySelector('.team-position');
      const bioEl = card.querySelector('.team-description');
      const emailLink = card.querySelector('.team-contact .team-contact-item a');
      const phoneItem = card.querySelectorAll('.team-contact .team-contact-item')[1];
      if (nameEl && m.name) nameEl.textContent = m.name;
      if (posEl && m.position) posEl.textContent = m.position;
      if (bioEl && m.bio) bioEl.textContent = m.bio;
      if (emailLink) {
        if (m.email) { emailLink.textContent = m.email; emailLink.setAttribute('href', 'mailto:' + m.email); }
        else { emailLink.textContent = ''; emailLink.removeAttribute('href'); }
      }
      if (phoneItem) { phoneItem.textContent = m.phone ? ('📞 ' + m.phone) : ''; }
      teamWrapper.appendChild(card);
    });
    if (window.__initTeamSwiper) {
      window.__initTeamSwiper();
    } else {
      document.addEventListener('DOMContentLoaded', function(){ window.__initTeamSwiper && window.__initTeamSwiper(); });
    }
  }

  const contactLabel = document.querySelector('#contact .section-label'); if (contactLabel && c.contact_label) contactLabel.textContent = c.contact_label;
  const contactTitle = document.querySelector('#contact h2'); if (contactTitle && c.contact_title) contactTitle.textContent = c.contact_title;
  const contactInfoH = document.querySelector('.contact-info h3'); if (contactInfoH && c.contact_title) contactInfoH.textContent = c.contact_title;
  const contactFormH = document.querySelector('.contact-form h3'); if (contactFormH && c.contact_title) contactFormH.textContent = c.contact_title;
  const ciPhone = document.getElementById('contact-phone'); if (ciPhone && c.footer_phone) ciPhone.textContent = c.footer_phone;
  const ciEmail = document.getElementById('contact-email'); if (ciEmail && c.footer_email) ciEmail.textContent = c.footer_email;
  const ciAddr = document.getElementById('contact-address'); if (ciAddr && c.footer_address) ciAddr.textContent = c.footer_address;

  const rl = document.querySelector('.rooms .section-label'); if (rl && c.rooms_label) rl.textContent = c.rooms_label;
  const rh = document.querySelector('.rooms h2'); if (rh && c.rooms_title) rh.textContent = c.rooms_title;
  const r1 = document.querySelector('#carouselTrack .room-card:nth-child(1) .room-img'); if (r1 && (c.room_img_1 || c.gallery_room_url)) { r1.src = c.room_img_1 || c.gallery_room_url; if (c.room_title_1) r1.alt = c.room_title_1; }
  const r2 = document.querySelector('#carouselTrack .room-card:nth-child(2) .room-img'); if (r2 && (c.room_img_2 || c.gallery_pool_url)) { r2.src = c.room_img_2 || c.gallery_pool_url; if (c.room_title_2) r2.alt = c.room_title_2; }
  const r3 = document.querySelector('#carouselTrack .room-card:nth-child(3) .room-img'); if (r3 && (c.room_img_3 || c.gallery_dining_url)) { r3.src = c.room_img_3 || c.gallery_dining_url; if (c.room_title_3) r3.alt = c.room_title_3; }
  const r1t = document.querySelector('#carouselTrack .room-card:nth-child(1) .room-title'); if (r1t && c.room_title_1) r1t.textContent = c.room_title_1;
  const r2t = document.querySelector('#carouselTrack .room-card:nth-child(2) .room-title'); if (r2t && c.room_title_2) r2t.textContent = c.room_title_2;
  const r3t = document.querySelector('#carouselTrack .room-card:nth-child(3) .room-title'); if (r3t && c.room_title_3) r3t.textContent = c.room_title_3;
  if (window.__roomsInit) window.__roomsInit();
}).catch(() => {
  const heroRoot = document.getElementById('hero-root');
  if (heroRoot) ReactDOM.createRoot(heroRoot).render(h(Hero));
  const showcaseRoot = document.getElementById('video-showcase-root');
  if (showcaseRoot) ReactDOM.createRoot(showcaseRoot).render(h(VideoShowcase));
});

(() => {
  const items = Array.from(document.querySelectorAll('[data-parallax]'));
  let ticking = false;
  const update = () => {
    const vh = window.innerHeight || 0;
    items.forEach(el => {
      const speed = parseFloat(el.getAttribute('data-parallax')) || 0.05;
      const rect = el.getBoundingClientRect();
      const offset = rect.top - vh / 2;
      if (window.innerWidth < 768) { el.style.transform = ''; return; }
      el.style.transform = `translate3d(0, ${offset * speed}px, 0)`;
    });
    ticking = false;
  };
  const onScroll = () => {
    if (!ticking) {
      window.requestAnimationFrame(update);
      ticking = true;
    }
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', update, { passive: true });
  update();
})();

// Scroll Animation Observer
(() => {
  // Skip on mobile for performance
  if (window.innerWidth <= 768) {
    document.querySelectorAll('.scroll-fade-in, .scroll-slide-left, .scroll-slide-right, .scroll-scale-up, .scroll-zoom-in, .scroll-rotate-in, .reveal').forEach(el => {
      el.classList.add('animate');
      el.classList.add('active');
    });
    return;
  }

  const observerOptions = {
    threshold: 0.15,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate');
        entry.target.classList.add('active');
        // Optional: unobserve after animation to improve performance
        // observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  // Observe all scroll animation elements
  const animatedElements = document.querySelectorAll(
    '.scroll-fade-in, .scroll-slide-left, .scroll-slide-right, .scroll-scale-up, .scroll-zoom-in, .scroll-rotate-in, .reveal'
  );

  animatedElements.forEach(el => {
    observer.observe(el);
  });

  // Handle window resize
  window.addEventListener('resize', () => {
    if (window.innerWidth <= 768) {
      animatedElements.forEach(el => {
        el.classList.add('animate');
        observer.unobserve(el);
      });
    }
  }, { passive: true });
})();

  document.addEventListener('DOMContentLoaded', function() {
  const navLinks = document.querySelectorAll('.nav-links a[href^="#"]');
  const menuToggle = document.getElementById('menuToggle');
  const navList = document.querySelector('.nav-links');
  
  
  const loginLink = document.getElementById('headerLoginLink');
  if (loginLink) {
    loginLink.addEventListener('click', function(e){ e.preventDefault(); window.location.href = 'auth/login.php'; });
  }
  const portalLink = document.getElementById('headerPortalLink');
  if (portalLink) {
    portalLink.addEventListener('click', function(e){
      var href = this.getAttribute('href') || '';
      if (href.indexOf('guest/dashboard.php') !== -1) { return; }
      e.preventDefault();
      if (typeof window.showRole==='function'){
        window.showRole('guest');
      } else {
        var d=document.getElementById('dashboards');
        if(d){
          d.style.display='block';
          ['adminTab','frontdeskTab','housekeepingTab','guestTab'].forEach(function(id){
            var el=document.getElementById(id);
            if(el) el.style.display=(id==='guestTab'?'block':'none');
          });
        }
      }
    });
  }

  
  const bookBtn = document.querySelector('.book-btn');
  if (bookBtn) {
    bookBtn.addEventListener('click', function(e){ e.preventDefault(); if (typeof window.showGuestRegistrationModal==='function'){ window.showGuestRegistrationModal(); } else if (typeof window.showRole==='function'){ window.showRole('guest'); } else { var d=document.getElementById('dashboards'); if(d){ d.style.display='block'; ['adminTab','frontdeskTab','housekeepingTab','guestTab'].forEach(function(id){ var el=document.getElementById(id); if(el) el.style.display=(id==='guestTab'?'block':'none'); }); } } });
  }
  // Mobile menu toggle
  if (menuToggle && navList) {
    menuToggle.addEventListener('click', function() { 
      navList.classList.toggle('show'); 
      // Change menu icon
      this.textContent = navList.classList.contains('show') ? '✕' : '☰';
    });
  }
  
  // Close mobile menu when clicking on a link
  navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      
      // Close mobile menu
      if (window.innerWidth <= 768 && navList) {
        navList.classList.remove('show');
        if (menuToggle) menuToggle.textContent = '☰';
      }
      
      const targetId = this.getAttribute('href').substring(1);
      const targetElement = document.getElementById(targetId);
      
      if (targetElement) {
        const headerOffset = window.innerWidth <= 768 ? 80 : 100;
        const elementPosition = targetElement.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
        
        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        });
      }
    });
  });
  
  // Close mobile menu when clicking outside
  document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768 && navList && navList.classList.contains('show')) {
      if (!navList.contains(e.target) && !menuToggle.contains(e.target)) {
        navList.classList.remove('show');
        menuToggle.textContent = '☰';
      }
    }
  });
  
  // Handle window resize
  window.addEventListener('resize', function() {
    if (window.innerWidth > 768 && navList) {
      navList.classList.remove('show');
      if (menuToggle) menuToggle.textContent = '☰';
    }
  });

  const roomsBg = document.getElementById('roomsBg');
  const roomsBgNew = document.getElementById('roomsBgNew');
  const setBg = (src, immediate) => {
    if (!roomsBg || !roomsBgNew) return;
    if (immediate) {
      roomsBg.style.backgroundImage = `url(${src})`;
      roomsBgNew.style.opacity = 0;
      roomsBgNew.style.transform = 'scale(1.08)';
      return;
    }
    
    // Enhanced cinematic transition
    roomsBgNew.style.transition = 'opacity 800ms cubic-bezier(0.4, 0, 0.2, 1), transform 800ms cubic-bezier(0.4, 0, 0.2, 1)';
    roomsBgNew.style.backgroundImage = `url(${src})`;
    roomsBgNew.style.transform = 'scale(1.08)';
    
    // Force reflow to guarantee transition
    void roomsBgNew.offsetHeight;
    
    // Fade in with scale animation
    roomsBgNew.style.opacity = 1;
    roomsBgNew.style.transform = 'scale(1.04)';
    
    let finished = false;
    const finalize = () => {
      if (finished) return;
      finished = true;
      roomsBg.style.backgroundImage = `url(${src})`;
      roomsBg.style.transform = 'scale(1.04)';
      roomsBgNew.style.opacity = 0;
      roomsBgNew.style.transform = 'scale(1.08)';
      roomsBgNew.removeEventListener('transitionend', finalize);
    };
    roomsBgNew.addEventListener('transitionend', finalize, { once: true });
    setTimeout(finalize, 900);
  };
  const initRoomsCards = () => {
    const cards = Array.from(document.querySelectorAll('#carouselTrack .room-card'));
    if (!cards.length) return;
    const firstImg = cards[0].querySelector('.room-img');
    if (firstImg) {
      setBg(firstImg.src, true);
      cards.forEach(c => c.classList.remove('active','dim'));
      cards.forEach((c, idx) => { if (idx !== 0) c.classList.add('dim'); });
      cards[0].classList.add('active');
      const titleLarge = document.getElementById('roomTitleLarge');
      if (titleLarge) titleLarge.textContent = firstImg.alt || cards[0].querySelector('.room-title')?.textContent || 'Selected Room';
    }
    cards.forEach(card => {
      // Handle card click (but not info button clicks)
      card.addEventListener('click', (e) => {
        // Don't trigger if clicking on info buttons
        if (e.target.closest('.room-card-info-btn')) return;
        
        const img = card.querySelector('.room-img');
        if (!img) return;
        
        // Remove active from all cards
        cards.forEach(c => c.classList.remove('active','dim'));
        cards.forEach(c => { if (c !== card) c.classList.add('dim'); });
        card.classList.add('active');
        
        // Animate title change
        const titleLarge = document.getElementById('roomTitleLarge');
        if (titleLarge) {
          titleLarge.classList.add('updating');
          setTimeout(() => {
            titleLarge.textContent = img.alt || card.querySelector('.room-title')?.textContent || 'Selected Room';
            titleLarge.classList.remove('updating');
          }, 200);
        }
        
        // Add overlay pulse effect
        const overlay = document.querySelector('.rooms-overlay');
        if (overlay) {
          overlay.classList.remove('pulse');
          void overlay.offsetHeight; // Force reflow
          overlay.classList.add('pulse');
          setTimeout(() => overlay.classList.remove('pulse'), 800);
        }
        
        // Change background with animation
        setBg(img.src);
        const track = document.getElementById('carouselTrack');
        if (track) {
          const center = track.scrollLeft + track.clientWidth / 2;
          const cardCenter = card.offsetLeft + card.offsetWidth / 2;
          track.scrollBy({ left: cardCenter - center, behavior: 'smooth' });
        }
      });
      const imgEl = card.querySelector('.room-img');
      if (imgEl) {
        imgEl.addEventListener('click', (e) => { e.stopPropagation(); card.click(); });
        imgEl.addEventListener('touchstart', (e) => { e.stopPropagation(); card.click(); }, { passive: true });
      }
      
      // Handle info overlay buttons
      const playBtn = card.querySelector('.room-card-info-btn[title="Play Preview"]');
      const infoBtn = card.querySelector('.room-card-info-btn[title="More Info"]');
      
      if (playBtn) {
        playBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          const img = card.querySelector('.room-img');
          const roomTitle = img?.alt || card.querySelector('.room-title')?.textContent || 'Room';
          
          // Select this room and trigger booking
          cards.forEach(c => c.classList.remove('active','dim'));
          cards.forEach(c => { if (c !== card) c.classList.add('dim'); });
          card.classList.add('active');
          setBg(img.src);
          
          const titleLarge = document.getElementById('roomTitleLarge');
          if (titleLarge) titleLarge.textContent = roomTitle;
          
          // Trigger book now action
          setTimeout(() => {
            document.getElementById('bookNowBtn')?.click();
          }, 300);
        });
      }
      
      if (infoBtn) {
        infoBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          const img = card.querySelector('.room-img');
          const roomTitle = img?.alt || card.querySelector('.room-title')?.textContent || 'Room';
          
          // Select this room
          cards.forEach(c => c.classList.remove('active','dim'));
          cards.forEach(c => { if (c !== card) c.classList.add('dim'); });
          card.classList.add('active');
          setBg(img.src);
          
          const titleLarge = document.getElementById('roomTitleLarge');
          if (titleLarge) titleLarge.textContent = roomTitle;
          
          // Show details modal
          setTimeout(() => {
            showRoomDetailsModal(roomTitle, img.src);
          }, 300);
        });
      }
    });
    const track = document.getElementById('carouselTrack');
    const prev = document.getElementById('roomsPrev');
    const next = document.getElementById('roomsNext');
    const getStep = () => {
      if (!track) return 220;
      const card = track.querySelector('.room-card');
      if (!card) return 220;
      const rect = card.getBoundingClientRect();
      const gap = parseFloat(getComputedStyle(track).gap || '18');
      return Math.round(rect.width + gap);
    };
    if (track && prev && next) {
      prev.addEventListener('click', () => track.scrollBy({ left: -getStep(), behavior: 'smooth' }));
      next.addEventListener('click', () => track.scrollBy({ left: getStep(), behavior: 'smooth' }));
      window.addEventListener('resize', () => {/* step recalculated via getStep */}, { passive: true });
    }
  };

  // Initialize room action buttons
  let currentSelectedRoom = null;
  
  const initRoomButtons = () => {
    const bookNowBtn = document.getElementById('bookNowBtn');
    const viewDetailsBtn = document.getElementById('viewDetailsBtn');
    
  if (bookNowBtn) {
      bookNowBtn.addEventListener('click', () => {
        const roomTitle = document.getElementById('roomTitleLarge')?.textContent || 'Room';
        bookNowBtn.style.transform = 'scale(0.95)';
        setTimeout(() => bookNowBtn.style.transform = '', 100);
        if (typeof window.showGuestRegistrationModal==='function'){
          window.showGuestRegistrationModal(roomTitle);
        } else if (typeof window.showRole==='function'){
          window.showRole('guest');
        } else {
          var d=document.getElementById('dashboards');
          if(d){
            d.style.display='block';
            ['adminTab','frontdeskTab','housekeepingTab','guestTab'].forEach(function(id){
              var el=document.getElementById(id);
              if(el) el.style.display=(id==='guestTab'?'block':'none');
            });
          }
        }
      });
  }
    
    if (viewDetailsBtn) {
      viewDetailsBtn.addEventListener('click', () => {
        const roomTitle = document.getElementById('roomTitleLarge')?.textContent || 'Room';
        const activeCard = document.querySelector('#carouselTrack .room-card.active');
        const roomImg = activeCard?.querySelector('.room-img')?.src || '';
        
        // Add button press animation
        viewDetailsBtn.style.transform = 'scale(0.95)';
        setTimeout(() => viewDetailsBtn.style.transform = '', 100);
        
        // Netflix-style modal or details view
        showRoomDetailsModal(roomTitle, roomImg);
      });
    }
  };
  const addTilt = () => {
    const cards = Array.from(document.querySelectorAll('#carouselTrack .room-card'));
    cards.forEach(card => {
      const img = card.querySelector('.room-img');
      if (!img) return;
      const enableTilt = () => {
        img.addEventListener('mousemove', onMove);
        img.addEventListener('mouseleave', onLeave);
      };
      const disableTilt = () => {
        img.removeEventListener('mousemove', onMove);
        img.removeEventListener('mouseleave', onLeave);
        img.style.transform = '';
      };
      const onMove = (e) => {
        const r = img.getBoundingClientRect();
        const rx = ((e.clientY - r.top) / r.height - 0.5) * -6;
        const ry = ((e.clientX - r.left) / r.width - 0.5) * 8;
        img.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg) scale(1.02)`;
      };
      const onLeave = () => { img.style.transform = ''; };
      const update = () => {
        if (window.innerWidth <= 768 || 'ontouchstart' in window) {
          disableTilt();
        } else {
          enableTilt();
        }
      };
      update();
      window.addEventListener('resize', update, { passive: true });
    });
  };
  
  // Netflix-style notification
  const showNotification = (message, type = 'info') => {
    const notification = document.createElement('div');
    notification.className = 'netflix-notification';
    notification.textContent = message;
    notification.style.cssText = `
      position: fixed;
      top: 100px;
      right: 30px;
      background: ${type === 'success' ? 'rgba(46, 125, 50, 0.95)' : 'rgba(33, 33, 33, 0.95)'};
      color: white;
      padding: 16px 24px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 15px;
      z-index: 10000;
      box-shadow: 0 8px 24px rgba(0,0,0,0.4);
      backdrop-filter: blur(10px);
      animation: slideInRight 400ms cubic-bezier(0.4, 0, 0.2, 1);
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 400ms cubic-bezier(0.4, 0, 0.2, 1)';
      setTimeout(() => notification.remove(), 400);
    }, 3000);
  };
  
  const showGuestRegistrationModal = (title) => {
    const modal = document.createElement('div');
    modal.className = 'netflix-modal';
    modal.innerHTML = `
      <div class="modal-backdrop"></div>
      <div class="modal-content">
        <button class="modal-close" onclick="this.closest('.netflix-modal').remove(); document.body.style.overflow='';">✕</button>
        <div class="modal-hero" style="background: linear-gradient(135deg, rgba(212,175,55,0.4), rgba(15,28,45,0.7)); background-size: cover; background-position: center;">
          <div class="modal-hero-content">
            <h2>Register as Guest</h2>
            ${title ? `<div class="modal-subtitle">Booking ${title}</div>` : ''}
          </div>
        </div>
        <div class="modal-details">
          <form method="POST" action="">
            <input type="hidden" name="action" value="register_guest">
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px">
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">First Name</div>
                <input type="text" name="first_name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">Last Name</div>
                <input type="text" name="last_name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">Username</div>
                <input type="text" name="username" autocomplete="username" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">Password</div>
                <input type="password" name="password" autocomplete="new-password" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">Email</div>
                <input type="email" name="email" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">Phone</div>
                <input type="text" name="phone" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div style="grid-column:1/-1">
                <div style="font-size:12px;color:#666;margin-bottom:6px">Address</div>
                <textarea name="address" rows="2" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px"></textarea>
              </div>
            </div>
            <div style="margin-top:12px;display:flex;justify-content:flex-end">
              <button type="submit" class="modal-btn primary"><span>✔</span> Register</button>
            </div>
          </form>
        </div>
      </div>
    `;
    modal.style.cssText = `position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; animation: fadeIn 300ms ease;`;
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    modal.querySelector('.modal-backdrop').addEventListener('click', () => { modal.remove(); document.body.style.overflow = ''; });
  };
  window.showGuestRegistrationModal = showGuestRegistrationModal;

  // Netflix-style room details modal
  const showRoomDetailsModal = (title, imgSrc) => {
    const modal = document.createElement('div');
    modal.className = 'netflix-modal';
    modal.innerHTML = `
      <div class="modal-backdrop"></div>
      <div class="modal-content">
        <button class="modal-close" onclick="this.closest('.netflix-modal').remove()">✕</button>
        <div class="modal-hero" style="background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.8) 100%), url('${imgSrc}'); background-size: cover; background-position: center;">
          <div class="modal-hero-content">
            <h2>${title}</h2>
            <div class="modal-actions">
              <button class="modal-btn primary" onclick="window.showGuestRegistrationModal ? window.showGuestRegistrationModal('${title.replace(/'/g, "\\'")}') : (document.getElementById('bookNowBtn') && document.getElementById('bookNowBtn').click()); this.closest('.netflix-modal').remove();">
                <span>▶</span> BOOK NOW
              </button>
            </div>
          </div>
        </div>
        <div class="modal-details">
          <h3>Room Features</h3>
          <div class="features-grid">
            <div class="feature">🛏️ King Size Bed</div>
            <div class="feature">📺 Smart TV</div>
            <div class="feature">❄️ Air Conditioning</div>
            <div class="feature">🚿 Private Bathroom</div>
            <div class="feature">📶 Free WiFi</div>
            <div class="feature">☕ Mini Bar</div>
          </div>
          <p class="room-description">
            Experience luxury and comfort in our ${title}. Each room is thoughtfully designed 
            with modern amenities and elegant furnishings to ensure your stay is memorable.
          </p>
        </div>
      </div>
    `;
    
    modal.style.cssText = `
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
      animation: fadeIn 300ms ease;
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    // Close on backdrop click
    modal.querySelector('.modal-backdrop').addEventListener('click', () => {
      modal.remove();
      document.body.style.overflow = '';
    });
  };

  initRoomsCards();
  initRoomButtons();
  addTilt();
  window.__roomsInit = () => {
    initRoomsCards();
    initRoomButtons();
    addTilt();
  };
  window.__roomsSetBg = setBg;

  // Team Centered Carousel Functionality
  const initTeamSwiper = () => {
    const wrapper = document.getElementById('teamSwiperWrapper');
    const prevBtn = document.getElementById('teamPrevBtn');
    const nextBtn = document.getElementById('teamNextBtn');
    const dotsContainer = document.getElementById('teamSwiperDots');
    
    if (!wrapper || !prevBtn || !nextBtn || !dotsContainer) return;

    const cards = Array.from(wrapper.querySelectorAll('.team-card')).filter(c => c.style.display !== 'none');
    let currentIndex = 0;

    // Create dots
    cards.forEach((_, index) => {
      const dot = document.createElement('div');
      dot.className = 'team-swiper-dot';
      if (index === 0) dot.classList.add('active');
      dot.addEventListener('click', () => goToSlide(index));
      dotsContainer.appendChild(dot);
    });

    const dots = dotsContainer.querySelectorAll('.team-swiper-dot');

    // Update card positions based on current index
    const updateCardPositions = () => {
      cards.forEach((card, index) => {
        // Remove all position classes
        card.classList.remove('left-2', 'left-1', 'center', 'right-1', 'right-2');
        
        const diff = index - currentIndex;
        
        if (diff === 0) {
          card.classList.add('center');
        } else if (diff === -1 || (currentIndex === 0 && index === cards.length - 1)) {
          card.classList.add('left-1');
        } else if (diff === -2 || (currentIndex === 0 && index === cards.length - 2) || (currentIndex === 1 && index === cards.length - 1)) {
          card.classList.add('left-2');
        } else if (diff === 1 || (currentIndex === cards.length - 1 && index === 0)) {
          card.classList.add('right-1');
        } else if (diff === 2 || (currentIndex === cards.length - 1 && index === 1) || (currentIndex === cards.length - 2 && index === 0)) {
          card.classList.add('right-2');
        } else {
          // Hide cards that are too far
          card.style.opacity = '0';
          card.style.pointerEvents = 'none';
          return;
        }
        
        card.style.opacity = '';
        card.style.pointerEvents = '';
      });

      // Update dots
      dots.forEach((dot, index) => {
        dot.classList.toggle('active', index === currentIndex);
      });
    };

    const goToSlide = (index) => {
      if (index < 0) index = cards.length - 1;
      if (index >= cards.length) index = 0;
      
      currentIndex = index;
      updateCardPositions();
    };

    // Button navigation
    prevBtn.addEventListener('click', () => {
      goToSlide(currentIndex - 1);
    });

    nextBtn.addEventListener('click', () => {
      goToSlide(currentIndex + 1);
    });

    // Click on side cards to navigate
    cards.forEach((card, index) => {
      card.addEventListener('click', () => {
        if (index !== currentIndex) {
          goToSlide(index);
        }
      });
    });

    // Keyboard navigation
    const handleKeydown = (e) => {
      if (e.key === 'ArrowLeft') {
        goToSlide(currentIndex - 1);
      } else if (e.key === 'ArrowRight') {
        goToSlide(currentIndex + 1);
      }
    };
    document.addEventListener('keydown', handleKeydown);

    // Touch swipe support
    let touchStartX = 0;
    let touchEndX = 0;

    wrapper.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    wrapper.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      handleSwipe();
    }, { passive: true });

    const handleSwipe = () => {
      const swipeThreshold = 50;
      if (touchStartX - touchEndX > swipeThreshold) {
        // Swipe left
        goToSlide(currentIndex + 1);
      } else if (touchEndX - touchStartX > swipeThreshold) {
        // Swipe right
        goToSlide(currentIndex - 1);
      }
    };

    // Auto-play
    let autoPlayInterval;
    const startAutoPlay = () => {
      autoPlayInterval = setInterval(() => {
        goToSlide(currentIndex + 1);
      }, 5000);
    };

    const stopAutoPlay = () => {
      clearInterval(autoPlayInterval);
    };

    // Start auto-play
    startAutoPlay();

    // Pause on hover
    wrapper.addEventListener('mouseenter', stopAutoPlay);
    wrapper.addEventListener('mouseleave', startAutoPlay);

    // Pause on touch
    wrapper.addEventListener('touchstart', stopAutoPlay, { passive: true });

    // Initialize positions
    updateCardPositions();
  };

  window.__initTeamSwiper = initTeamSwiper;
  initTeamSwiper();
});
</script>

<script>
  document.getElementById('headerLoginLink')?.addEventListener('click', function(e){ e.preventDefault(); window.location.href = 'auth/login.php'; });
</script>

<!-- Live Chat Widget -->
<style>
    .chat-widget {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
    }
    
    .chat-widget-button {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        border: none;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .chat-widget-button:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.6);
    }
    
    .chat-widget-button.open {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }
    
    .chat-widget-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #ef4444;
        color: white;
        border-radius: 12px;
        padding: 2px 6px;
        font-size: 11px;
        font-weight: 600;
        min-width: 20px;
        text-align: center;
    }
    
    .chat-widget-window {
        position: fixed;
        bottom: 100px;
        right: 24px;
        width: 380px;
        height: 550px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        display: none;
        flex-direction: column;
        overflow: hidden;
        animation: slideUp 0.3s ease;
    }
    
    .chat-widget-window.open {
        display: flex;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .chat-widget-header {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .chat-widget-header-title {
        font-weight: 600;
        font-size: 16px;
    }
    
    .chat-widget-header-subtitle {
        font-size: 12px;
        opacity: 0.9;
        margin-top: 2px;
    }
    
    .chat-widget-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        transition: background 0.2s;
    }
    
    .chat-widget-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    
    .chat-widget-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background: #f9fafb;
    }
    
    .chat-widget-message {
        margin-bottom: 16px;
        display: flex;
        gap: 8px;
    }
    
    .chat-widget-message.guest {
        flex-direction: row-reverse;
    }
    
    .chat-widget-message-bubble {
        max-width: 75%;
        padding: 10px 14px;
        border-radius: 12px;
        word-wrap: break-word;
        font-size: 14px;
    }
    
    .chat-widget-message.staff .chat-widget-message-bubble {
        background: white;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .chat-widget-message.guest .chat-widget-message-bubble {
        background: #3b82f6;
        color: white;
    }
    
    .chat-widget-message-time {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 4px;
        text-align: right;
    }
    
    .chat-widget-message.staff .chat-widget-message-time {
        text-align: left;
    }
    
    .chat-widget-form-container {
        padding: 16px;
        background: white;
        border-top: 1px solid #e5e7eb;
    }
    
    .chat-widget-name-form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .chat-widget-input {
        padding: 10px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
    }
    
    .chat-widget-input:focus {
        border-color: #3b82f6;
    }
    
    .chat-widget-start-btn {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    
    .chat-widget-start-btn:hover {
        background: #1d4ed8;
    }
    
    .chat-widget-message-form {
        display: none;
        flex-direction: row;
        gap: 8px;
    }
    
    .chat-widget-message-form.active {
        display: flex;
    }
    
    .chat-widget-message-input {
        flex: 1;
        padding: 10px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
    }
    
    .chat-widget-message-input:focus {
        border-color: #3b82f6;
    }
    
    .chat-widget-send-btn {
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s;
        font-size: 16px;
    }
    
    .chat-widget-send-btn:hover {
        background: #1d4ed8;
    }
    
    .chat-widget-typing {
        display: none;
        padding: 8px 14px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        width: fit-content;
        margin-bottom: 16px;
    }
    
    .chat-widget-typing.active {
        display: block;
    }
    
    .chat-widget-typing-dots {
        display: flex;
        gap: 4px;
    }
    
    .chat-widget-typing-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #94a3b8;
        animation: typing 1.4s infinite;
    }
    
    .chat-widget-typing-dot:nth-child(2) {
        animation-delay: 0.2s;
    }
    
    .chat-widget-typing-dot:nth-child(3) {
        animation-delay: 0.4s;
    }
    
    @keyframes typing {
        0%, 60%, 100% {
            transform: translateY(0);
        }
        30% {
            transform: translateY(-10px);
        }
    }
    
    @media (max-width: 480px) {
        .chat-widget-window {
            width: calc(100vw - 32px);
            height: calc(100vh - 120px);
            right: 16px;
            bottom: 90px;
        }
        
        .chat-widget-button {
            width: 56px;
            height: 56px;
            font-size: 24px;
        }
    }
</style>

<div class="chat-widget">
    <button class="chat-widget-button" id="chatWidgetBtn" onclick="toggleChat()">
        💬
    </button>
    
    <div class="chat-widget-window" id="chatWidgetWindow">
        <div class="chat-widget-header">
            <div>
                <div class="chat-widget-header-title">Chat with us</div>
                <div class="chat-widget-header-subtitle">We typically reply instantly</div>
            </div>
            <button class="chat-widget-close" onclick="toggleChat()">×</button>
        </div>
        
        <div class="chat-widget-messages" id="chatWidgetMessages">
            <div class="chat-widget-message staff">
                <div>
                    <div class="chat-widget-message-bubble">
                        👋 Hello! Welcome to Romancy Hotel. How can we help you today?
                    </div>
                    <div class="chat-widget-message-time">Just now</div>
                </div>
            </div>
        </div>
        
        <div class="chat-widget-form-container">
            <form class="chat-widget-name-form" id="chatNameForm" onsubmit="startChat(event)">
                <input type="text" class="chat-widget-input" id="chatName" placeholder="Your name" required>
                <input type="email" class="chat-widget-input" id="chatEmail" placeholder="Your email (optional)">
                <button type="submit" class="chat-widget-start-btn">Start Chat</button>
            </form>
            
            <form class="chat-widget-message-form" id="chatMessageForm" onsubmit="sendChatMessage(event)">
                <input type="text" class="chat-widget-message-input" id="chatMessageInput" placeholder="Type your message..." required>
                <button type="submit" class="chat-widget-send-btn">➤</button>
            </form>
        </div>
    </div>
</div>

<script>
let chatSessionId = null;
let chatUserName = '';
let chatUserEmail = '';
let lastChatMessageId = 0;
let chatPollInterval = null;

function toggleChat() {
    const window = document.getElementById('chatWidgetWindow');
    const button = document.getElementById('chatWidgetBtn');
    
    window.classList.toggle('open');
    button.classList.toggle('open');
    
    if (window.classList.contains('open')) {
        button.textContent = '×';
        if (chatSessionId) {
            pollChatMessages();
        }
    } else {
        button.textContent = '💬';
        if (chatPollInterval) {
            clearInterval(chatPollInterval);
        }
    }
}

function startChat(event) {
    event.preventDefault();
    
    chatUserName = document.getElementById('chatName').value.trim();
    chatUserEmail = document.getElementById('chatEmail').value.trim();
    
    if (!chatUserName) return;
    
    // Generate session ID
    chatSessionId = 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    // Hide name form, show message form
    document.getElementById('chatNameForm').style.display = 'none';
    document.getElementById('chatMessageForm').classList.add('active');
    
    // Start polling for messages
    pollChatMessages();
}

function sendChatMessage(event) {
    event.preventDefault();
    
    const input = document.getElementById('chatMessageInput');
    const message = input.value.trim();
    
    if (!message || !chatSessionId) return;
    
    // Add message to UI immediately
    addChatMessage(message, 'guest', chatUserName);
    input.value = '';
    
    // Send to server
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('session_id', chatSessionId);
    formData.append('name', chatUserName);
    formData.append('email', chatUserEmail);
    formData.append('message', message);
    
    fetch('api/chat.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            lastChatMessageId = data.message_id;
        }
    })
    .catch(err => console.error('Error sending message:', err));
}

function pollChatMessages() {
    if (chatPollInterval) clearInterval(chatPollInterval);
    
    chatPollInterval = setInterval(() => {
        if (!chatSessionId) return;
        
        fetch(`api/chat.php?action=get_messages&session_id=${chatSessionId}&since_id=${lastChatMessageId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        if (msg.sender_type === 'staff') {
                            addChatMessage(msg.message, 'staff', msg.sender_name);
                        }
                        lastChatMessageId = Math.max(lastChatMessageId, msg.id);
                    });
                }
            })
            .catch(err => console.error('Error polling messages:', err));
    }, 2000);
}

function addChatMessage(message, type, senderName) {
    const container = document.getElementById('chatWidgetMessages');
    const messageEl = document.createElement('div');
    messageEl.className = `chat-widget-message ${type}`;
    
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    
    messageEl.innerHTML = `
        <div>
            <div class="chat-widget-message-bubble">${escapeHtml(message)}</div>
            <div class="chat-widget-message-time">${timeStr}</div>
        </div>
    `;
    
    container.appendChild(messageEl);
    container.scrollTop = container.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

  
  <div id="authControls" style="position:fixed;right:20px;bottom:20px;z-index:9999">
    <button id="openLogin" style="background:#0f1c2d;color:#fff;border:none;padding:10px 16px;border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,0.25);cursor:pointer">Sign in</button>
    <button id="logoutBtn" style="display:none;background:#991b1b;color:#fff;border:none;padding:10px 16px;border-radius:6px;margin-left:8px;cursor:pointer">Logout</button>
    <button id="bootstrapAdmin" style="background:#6b7280;color:#fff;border:none;padding:10px 16px;border-radius:6px;margin-left:8px;cursor:pointer">Bootstrap Admin</button>
    <div id="userBadge" style="margin-top:8px;font-size:12px;color:#0f1c2d"></div>
  </div>

  <div id="loginModal" style="display:none;position:fixed;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.55),rgba(0,0,0,.75));backdrop-filter:blur(6px);z-index:9998;align-items:center;justify-content:center">
    <div id="loginCard" style="background:rgba(255,255,255,0.85);backdrop-filter:blur(15px);border:1px solid rgba(255,255,255,0.3);border-radius:16px;box-shadow:0 30px 80px rgba(0,0,0,0.25);width:420px;max-width:92%;padding:32px;transform:scale(.96);opacity:0;transition:transform 350ms cubic-bezier(0.34, 1.56, 0.64, 1),opacity 350ms ease">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div style="width:42px;height:42px;border-radius:12px;background:#0f1c2d;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700">R</div>
        <div style="font-family:'Playfair Display',serif;font-size:24px;color:#0f1c2d">Staff Login</div>
      </div>
      <div style="display:grid;gap:10px">
        <div style="position:relative">
          <input id="loginEmail" placeholder="Email" value="admin@example.com" style="width:100%;padding:12px 12px 12px 40px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;outline:none;transition:border 150ms ease" />
          <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#6b7280">✉</span>
        </div>
        <div style="position:relative">
          <input id="loginPass" type="password" placeholder="Password" value="admin123" style="width:100%;padding:12px 40px 12px 40px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;outline:none;transition:border 150ms ease" />
          <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#6b7280">🔒</span>
          <button id="togglePass" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:#374151;cursor:pointer;padding:4px 8px;border-radius:6px">Show</button>
        </div>
        <div id="loginErr" style="color:#b91c1c;font-size:12px;min-height:18px"></div>
        <div style="display:flex;gap:10px">
          <button id="loginBtn" style="flex:1;background:#0f1c2d;color:#fff;border:none;padding:12px;border-radius:10px;font-weight:700;cursor:pointer">Sign in</button>
          <button id="closeLogin" style="background:#6b7280;color:#fff;border:none;padding:12px;border-radius:10px;cursor:pointer">Close</button>
        </div>
      </div>
    </div>
  </div>

  <section id="dashboards" style="display:none;padding:40px 50px;background:#f8fafc">
    <div style="max-width:1200px;margin:0 auto">
      <div style="display:flex;gap:8px;margin-bottom:16px">
        <button data-tab="admin" class="tabBtn" style="background:#0f1c2d;color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer">Admin Dashboard</button>
        <button data-tab="frontdesk" class="tabBtn" style="background:#0f1c2d;color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer">Frontdesk Dashboard</button>
        <button data-tab="housekeeping" class="tabBtn" style="background:#0f1c2d;color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer">Housekeeping Dashboard</button>
        <button data-tab="guest" class="tabBtn" style="background:#0f1c2d;color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer">Guest Dashboard</button>
      </div>
      <div id="adminTab" class="tabPanel" style="display:none">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
          <div id="admBookings" style="background:#fff;border-radius:8px;padding:16px;box-shadow:0 6px 20px rgba(0,0,0,0.06)"></div>
          <div id="admOccupancy" style="background:#fff;border-radius:8px;padding:16px;box-shadow:0 6px 20px rgba(0,0,0,0.06)"></div>
          <div id="admRevenue" style="background:#fff;border-radius:8px;padding:16px;box-shadow:0 6px 20px rgba(0,0,0,0.06)"></div>
        </div>
        <div style="margin-top:16px;background:#fff;border-radius:8px;padding:16px;box-shadow:0 6px 20px rgba(0,0,0,0.06)">
          <div style="font-weight:600;margin-bottom:8px">Housekeeping Tasks</div>
          <div id="admHK"></div>
        </div>
      </div>
      <div id="frontdeskTab" class="tabPanel" style="display:none">
        <div style="font-weight:600;margin-bottom:8px">Today’s Check-ins</div>
        <div id="fdQueue" style="background:#fff;border-radius:8px;padding:16px;box-shadow:0 6px 20px rgba(0,0,0,0.06)"></div>
      </div>
      <div id="housekeepingTab" class="tabPanel" style="display:none">
        <div id="hkTasks" style="background:#fff;border-radius:8px;padding:16px;box-shadow:0 6px 20px rgba(0,0,0,0.06)"></div>
      </div>
      <div id="guestTab" class="tabPanel" style="display:none">
        <div style="background:#fff;border-radius:8px;padding:16px;box-shadow:0 6px 20px rgba(0,0,0,0.06)">
          <div style="font-weight:600;margin-bottom:8px">Find Booking</div>
          <div style="display:flex;gap:8px"><input id="portalCode" placeholder="Booking Code" style="flex:1;padding:10px;border:1px solid #ddd;border-radius:6px"/><button id="portalLookup" style="background:#0f1c2d;color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer">Lookup</button></div>
          <div id="portalResult" style="margin-top:8px"></div>
        </div>
        <div style="margin-top:16px;background:#fff;border-radius:8px;padding:16px;box-shadow:0 6px 20px rgba(0,0,0,0.06)">
          <div style="font-weight:600;margin-bottom:8px">Register as Guest</div>
          <form method="POST" action="">
            <input type="hidden" name="action" value="register_guest">
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px">
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">First Name</div>
                <input type="text" name="first_name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">Last Name</div>
                <input type="text" name="last_name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">Username</div>
                <input type="text" name="username" autocomplete="username" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">Password</div>
                <input type="password" name="password" autocomplete="new-password" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">Email</div>
                <input type="email" name="email" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div>
                <div style="font-size:12px;color:#666;margin-bottom:6px">Phone</div>
                <input type="text" name="phone" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px" />
              </div>
              <div style="grid-column:1/-1">
                <div style="font-size:12px;color:#666;margin-bottom:6px">Address</div>
                <textarea name="address" rows="2" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px"></textarea>
              </div>
            </div>
            <div style="margin-top:12px;display:flex;justify-content:flex-end">
              <button type="submit" style="background:#0f1c2d;color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer">Register</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>

  <script>
    (function(){
      var API = {
        base: location.origin + '/booking231/api/index.php',
        token: function(){ return localStorage.getItem('token')||'' },
        setToken: function(t){ localStorage.setItem('token', t) },
        headers: function(){ return { Authorization: 'Bearer '+API.token() } },
        login: function(email, password){ return fetch(API.base+'/auth/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email,password})}).then(r=>r.json()) },
        bootstrap: function(){ return fetch(API.base+'/auth/bootstrap-admin',{method:'POST'}) },
        dashboard: function(){ return fetch(API.base+'/dashboard',{headers:API.headers()}).then(r=>r.json()) },
        hkTasks: function(){ return fetch(API.base+'/housekeeping/tasks',{headers:API.headers()}).then(r=>r.json()) },
        hkUpdate: function(id,status){ return fetch(API.base+'/housekeeping/tasks/'+id+'/status',{method:'PUT',headers:Object.assign({'Content-Type':'application/json'},API.headers()),body:JSON.stringify({status})}).then(r=>r.json()) },
        bookings: function(params){ var q=new URLSearchParams(params||{}).toString(); return fetch(API.base+'/bookings'+(q?'?'+q:''),{headers:API.headers()}).then(r=>r.json()) },
        portal: function(code){ return fetch(API.base+'/portal/bookings/'+code).then(r=>r.json()) }
      };
      var openLogin=document.getElementById('openLogin'), logoutBtn=document.getElementById('logoutBtn'), loginModal=document.getElementById('loginModal'), loginBtn=document.getElementById('loginBtn'), closeLogin=document.getElementById('closeLogin'), loginErr=document.getElementById('loginErr'), userBadge=document.getElementById('userBadge'), bootstrapAdmin=document.getElementById('bootstrapAdmin'), togglePass=document.getElementById('togglePass');
      openLogin.onclick=function(){ loginModal.style.display='flex'; var c=document.getElementById('loginCard'); if(c){ c.style.opacity='1'; c.style.transform='scale(1)'; } };
      closeLogin.onclick=function(){ loginModal.style.display='none'; var c=document.getElementById('loginCard'); if(c){ c.style.opacity='0'; c.style.transform='scale(.96)'; } };
      bootstrapAdmin.onclick=function(){ API.bootstrap().then(()=>{ userBadge.textContent='Admin bootstrapped'; setTimeout(()=>userBadge.textContent='',2000); }) };
      logoutBtn.onclick=function(){ localStorage.removeItem('token'); window.currentUser=null; document.getElementById('dashboards').style.display='none'; logoutBtn.style.display='none'; userBadge.textContent=''; };
      loginBtn.onclick=function(){ var email=document.getElementById('loginEmail').value, pass=document.getElementById('loginPass').value; loginErr.textContent=''; var prev=loginBtn.textContent; loginBtn.textContent='Signing in…'; loginBtn.disabled=true; API.login(email,pass).then(function(j){ if(j && j.token){ API.setToken(j.token); window.currentUser=j.user; localStorage.setItem('user', JSON.stringify(j.user)); loginModal.style.display='none'; logoutBtn.style.display='inline-block'; userBadge.textContent=j.user.name+' ('+j.user.role+')'; showRole(j.user.role); } else { loginErr.textContent='Invalid credentials'; } }).catch(function(){ loginErr.textContent='Login failed'; }).finally(function(){ loginBtn.textContent=prev; loginBtn.disabled=false; }); };
      if(togglePass){ togglePass.onclick=function(){ var p=document.getElementById('loginPass'); var s=(p.getAttribute('type')==='password'); p.setAttribute('type', s?'text':'password'); togglePass.textContent=s?'Hide':'Show'; }; }
      var le=document.getElementById('loginEmail'); var lp=document.getElementById('loginPass'); ['keydown'].forEach(function(ev){ [le,lp].forEach(function(inp){ if(inp){ inp.addEventListener(ev,function(e){ if(e.key==='Enter'){ loginBtn.click(); } }); } }); });
      function showRole(role){ var d=document.getElementById('dashboards'); d.style.display='block'; ['adminTab','frontdeskTab','housekeepingTab','guestTab'].forEach(function(id){ document.getElementById(id).style.display='none' }); var map={admin:'adminTab',frontdesk:'frontdeskTab',housekeeping:'housekeepingTab',guest:'guestTab'}; var target=map[role]||'guestTab'; document.getElementById(target).style.display='block'; loadData(role); try { d.scrollIntoView({ behavior:'smooth', block:'start' }); } catch(e){} var hero=document.getElementById('home'); if(hero) hero.style.display='none'; }
      (function(){ var t=API.token(); var uStr=localStorage.getItem('user'); var roleParam=new URLSearchParams(location.search).get('role'); if(t && uStr){ try { var u=JSON.parse(uStr||'{}'); window.currentUser=u; logoutBtn.style.display='inline-block'; var ol=document.getElementById('openLogin'); if(ol) ol.style.display='none'; var hl=document.getElementById('headerLoginLink'); if(hl) hl.style.display='none'; userBadge.textContent=(u.name||'User')+' ('+(u.role||'guest')+')'; showRole(roleParam||u.role||'guest'); } catch(e){} } })();
      window.showRole = showRole;
      Array.from(document.getElementsByClassName('tabBtn')).forEach(function(btn){ btn.onclick=function(){ showRole(this.getAttribute('data-tab')) } });
      function loadData(role){ if(role==='admin'){ API.dashboard().then(function(d){ document.getElementById('admBookings').innerHTML='<div style="font-weight:600">Bookings</div><div>Today: '+d.bookings.today+'</div><div>Week: '+d.bookings.week+'</div><div>Month: '+d.bookings.month+'</div>'; document.getElementById('admOccupancy').innerHTML='<div style="font-weight:600">Occupancy</div><div>'+d.occupancy_rate+'%</div>'; document.getElementById('admRevenue').innerHTML='<div style="font-weight:600">Revenue</div><div>₱'+d.revenue+'</div>'; }); API.hkTasks().then(function(t){ var wrap=document.getElementById('admHK'); wrap.innerHTML=''; t.forEach(function(x){ var row=document.createElement('div'); row.style.display='flex'; row.style.justifyContent='space-between'; row.style.padding='6px 0'; row.innerHTML='<div>Room '+x.room_id+'</div><div>'+x.status+'</div>'; wrap.appendChild(row); }); }); }
        if(role==='frontdesk'){ var day=new Date().toISOString().slice(0,10); API.bookings({status:'confirmed',day:day}).then(function(list){ var wrap=document.getElementById('fdQueue'); wrap.innerHTML=''; list.forEach(function(b){ var row=document.createElement('div'); row.style.display='flex'; row.style.justifyContent='space-between'; row.style.padding='6px 0'; var btn='<button style="background:#0f1c2d;color:#fff;border:none;padding:6px 10px;border-radius:6px;cursor:pointer">Check-in</button>'; row.innerHTML='<div>'+b.code+' • Room '+b.room_id+' • '+b.status+'</div><div>'+btn+'</div>'; row.querySelector('button').onclick=function(){ fetch(API.base+'/bookings/'+b.id+'/check-in',{method:'POST',headers:API.headers()}).then(()=>loadData('frontdesk')); }; wrap.appendChild(row); }); }); }
        if(role==='housekeeping'){ API.hkTasks().then(function(t){ var wrap=document.getElementById('hkTasks'); wrap.innerHTML=''; t.forEach(function(x){ var row=document.createElement('div'); row.style.display='flex'; row.style.justifyContent='space-between'; row.style.padding='6px 0'; var btns='<div><button data-s="started" style="background:#2563eb;color:#fff;border:none;padding:6px 10px;border-radius:6px;margin-right:6px;cursor:pointer">Start</button><button data-s="completed" style="background:#16a34a;color:#fff;border:none;padding:6px 10px;border-radius:6px;margin-right:6px;cursor:pointer">Complete</button><button data-s="needs_maintenance" style="background:#374151;color:#fff;border:none;padding:6px 10px;border-radius:6px;cursor:pointer">Maintenance</button></div>'; row.innerHTML='<div>Room '+x.room_id+'</div><div>'+x.status+'</div>'+btns; Array.from(row.querySelectorAll('button')).forEach(function(b){ b.onclick=function(){ API.hkUpdate(x.id,this.getAttribute('data-s')).then(()=>loadData('housekeeping')); } }); wrap.appendChild(row); }); }); }
        if(role==='guest'){ var codeBtn=document.getElementById('portalLookup'), codeInput=document.getElementById('portalCode'), result=document.getElementById('portalResult'); codeBtn.onclick=function(){ var c=(codeInput.value||'').toUpperCase(); if(!c){ result.textContent='Enter booking code'; return } API.portal(c).then(function(b){ result.textContent = b && b.code ? (b.code+' • '+b.check_in+' → '+b.check_out+' • '+b.status) : 'Not found'; }); } }
      }
    })();
  </script>

</body>
</html>
