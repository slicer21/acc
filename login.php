<?php
require 'db.php';

$base_url = "http://" . $_SERVER['SERVER_ADDR'] . ":8080/acc/"; // Adjusted for port and subdirectory

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                $_SESSION['current_company_id'] = 1;
                $company = $conn->query("SELECT name FROM companies WHERE id = 1")->fetch_assoc();
                $_SESSION['current_company_name'] = $company['name'] ?? 'Main Church';
                
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Management System</title>
    <link href="<?php echo $base_url; ?>assets/css/bootstrap.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            position: relative;
        }
        .background-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.9), rgba(30, 58, 138, 0.9)),
                        url('<?php echo $base_url; ?>assets/images/background.jpg') no-repeat center center/cover;
            z-index: 0;
        }
        .background-text {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.1;
            color: #FFFFFF;
            font-size: 2rem;
            font-weight: 300;
            pointer-events: none;
            z-index: 1;
            overflow: hidden;
            white-space: nowrap;
        }
        .background-text span {
            position: absolute;
            transform: rotate(-45deg);
            animation: float 20s linear infinite;
        }
        @keyframes float {
            0% { transform: translate(-100%, -100%) rotate(-45deg); }
            100% { transform: translate(100%, 100%) rotate(-45deg); }
        }
        #particles-js {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 2;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            max-width: 420px;
            width: 100%;
            z-index: 3;
            transition: transform 0.3s ease;
        }
        .login-container:hover {
            transform: translateY(-5px);
        }
        .login-header img {
            height: 70px;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }
        .login-header h2 {
            color: #FFFFFF;
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #FFFFFF;
            border-radius: 10px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.25);
            border-color: #60A5FA;
            box-shadow: 0 0 8px rgba(96, 165, 250, 0.4);
            color: #FFFFFF;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        .btn-gradient {
            background: linear-gradient(90deg, #3B82F6, #9333EA);
            border: none;
            border-radius: 12px;
            padding: 1.1rem 0;
            font-weight: 800;
            font-size: 1.25rem;
            color: #fff;
            box-shadow: 0 4px 24px 0 rgba(59,130,246,0.25), 0 1.5px 8px rgba(147,51,234,0.18);
            transition: all 0.2s cubic-bezier(.4,2,.3,1);
            letter-spacing: 0.5px;
            text-shadow: 0 2px 8px rgba(59,130,246,0.18);
        }
        .btn-gradient:hover, .btn-gradient:focus {
            background: linear-gradient(90deg, #2563EB, #7C3AED);
            transform: scale(1.04);
            box-shadow: 0 8px 32px 0 rgba(59,130,246,0.35), 0 2px 12px rgba(147,51,234,0.22);
            filter: brightness(1.08);
            outline: none;
        }
        .alert {
            background: rgba(239, 68, 68, 0.3);
            border: 1px solid #EF4444;
            color: #FFFFFF;
            border-radius: 10px;
            padding: 0.75rem;
        }
        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            font-weight: 500;
        }
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
                max-width: 90%;
            }
            .login-header img {
                height: 50px;
            }
            .login-header h2 {
                font-size: 1.4rem;
            }
            .background-text {
                font-size: 1.2rem;
            }
            .form-control {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <div id="particles-js"></div>
    <div class="login-container text-center">
        <h1 style="font-weight:500; letter-spacing:1px; margin-bottom: 1rem;">Accounting Management System</h1>
        <div class="login-header">
            <div class="logo-circle">
                <img src="<?php echo $base_url; ?>assets/images/logo.png" alt="Logo">
            </div>
            <p class="subtitle">Sign in to your account</p>
        </div>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php" class="modern-form">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                <label for="username"><i class="bi bi-person"></i> Username</label>
            </div>
            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <label for="password"><i class="bi bi-lock"></i> Password</label>
            </div>
            <button type="submit" class="btn btn-gradient w-100">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>
    </div>
    <div style="
        position: fixed;
        right: 12px;
        bottom: 12px;
        background: rgba(0,0,0,0.45);
        color: #fff;
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 0.85rem;
        z-index: 9999;
        box-shadow: 0 1px 4px rgba(0,0,0,0.12);
        letter-spacing: 0.5px;
        font-weight: 400;
        pointer-events: none;
        opacity: 0.85;
    ">
        Credit: JCB
    </div>
    <script src="<?php echo $base_url; ?>assets/js/bootstrap.js"></script>
    <script src="<?php echo $base_url; ?>assets/js/particles.js"></script>
    <script>
        particlesJS('particles-js', {
            particles: {
                number: { value: 50, density: { enable: true, value_area: 800 } },
                color: { value: '#ffffff' },
                shape: { type: 'circle' },
                opacity: { value: 0.3, random: true },
                size: { value: 3, random: true },
                line_linked: { enable: false },
                move: { enable: true, speed: 1, direction: 'none', random: true }
            },
            interactivity: {
                detect_on: 'canvas',
                events: { onhover: { enable: false }, onclick: { enable: false } },
                modes: { repulse: { distance: 100, duration: 0.4 } }
            },
            retina_detect: true
        });
    </script>
</body>
</html>