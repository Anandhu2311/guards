<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GuardSphere - Your Personal Safety Guardian</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: #f5f5f5;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 5%;
            background: linear-gradient(135deg, #2E0854, #4A1B7A, #2E0854);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            height: 45px;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .nav-links {
            display: flex;
            gap: 2.5rem;
        }

        .nav-links a {
            text-decoration: none;
            color: white;
            font-weight: 600;
            position: relative;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .nav-links a::after {
            display: none;
        }

        .hero {
            background-color: rgb(33, 29, 105);
            padding: 4rem 5%;
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }

        .hero-content {
            flex: 1;
            color: white;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .join-button {
            display: inline-block;
            padding: 1rem 2rem;
            background: #9932CC;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .join-button:hover {
            transform: scale(1.05);
        }

        .stats {
            display: flex;
            gap: 2rem;
            margin-top: 3rem;
        }

        .stat-item {
            color: white;
            text-align: center;
        }

        .stat-item h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        footer {
            background: linear-gradient(135deg, #2E0854, #4A1B7A, #2E0854);
            color: white;
            padding: 2rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }

        footer p {
            font-size: 1.1rem;
            line-height: 1.6;
            font-weight: 500;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        .quick-links {
            display: flex;
            gap: 2.5rem;
        }

        .quick-links a {
            text-decoration: none;
            color: white;
            font-weight: 600;
            position: relative;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.15);
        }

        .quick-links a:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .quick-links a::after {
            display: none;
        }

        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                text-align: center;
            }
            
            .stats {
                justify-content: center;
            }

            footer {
                flex-direction: column;
                text-align: center;
            }
            
            .quick-links {
                gap: 1.5rem;
                justify-content: center;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <nav>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 120" class="logo">
            <g transform="translate(30, 10)">
                <path d="M50 35
                         C45 25, 30 25, 25 35
                         C20 45, 25 55, 50 75
                         C75 55, 80 45, 75 35
                         C70 25, 55 25, 50 35" 
                      fill="#FF1493"/>
                <path d="M15 55
                         C12 55, 5 58, 5 75
                         C5 82, 8 87, 15 90
                         L25 92
                         C20 85, 18 80, 20 75
                         C22 70, 25 68, 30 70
                         C28 65, 25 62, 20 62
                         C15 62, 15 65, 15 55" 
                      fill="#9932CC"/>
                <path d="M85 55
                         C88 55, 95 58, 95 75
                         C95 82, 92 87, 85 90
                         L75 92
                         C80 85, 82 80, 80 75
                         C78 70, 75 68, 70 70
                         C72 65, 75 62, 80 62
                         C85 62, 85 65, 85 55" 
                      fill="#9932CC"/>
                <path d="M45 40
                         Q50 45, 55 40
                         Q52 35, 45 40" 
                      fill="#FF69B4" 
                      opacity="0.5"/>
            </g>
            <text x="150" y="80" font-family="Arial Black, sans-serif" font-weight="900" font-size="60" fill="white">GUARDSPHERE</text>
            <text x="150" y="105" font-family="Arial, sans-serif" font-size="20" fill="white">GUARDED BY GUARDSPHERE.</text>
        </svg>
        <div class="nav-links">
            <a href="#about">About Us</a>
            <a href="#services">Services</a>
            <a href="#contact">Contact</a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1>Your Personal Safety Guardian</h1>
            <p>Your ultimate safety companion, designed to protect and empower women every step of the way. With real-time alerts, GPS tracking, and discreet emergency tools, GuardSphere keeps you safe and confident, anytime, anywhere.</p>
            <a href="signup.php" class="join-button">Join Now</a>
            
            <div class="stats">
                <div class="stat-item">
                    <h3>24/7</h3>
                    <p>Support</p>
                </div>
                <div class="stat-item">
                    <h3>1M+</h3>
                    <p>Users</p>
                </div>
                <div class="stat-item">
                    <h3>100%</h3>
                    <p>Secure</p>
                </div>
            </div>
        </div>
        <img src="/api/placeholder/500/400" alt="Women Empowerment Illustration" />
    </section>

    <footer>
        <div>
            <p>Empowering women with safety and security solutions worldwide. Join our mission to create a safer tomorrow.</p>
        </div>
        <div class="quick-links">
            <a href="#about">About Us</a>
            <a href="#courses">Safety Courses</a>
            <a href="#products">Products</a>
            <a href="#help">Emergency Help</a>
            <a href="#plans">Subscription Plans</a>
        </div>
    </footer>
</body>
</html>