<?php
session_start();
define('INCLUDED_SETUP', true);
include 'setup_database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $query = "SELECT * FROM users WHERE LOWER(email) = ? AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if ($password === trim($user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['cluster_name'] = $user['cluster_name'];

                header("Location: " . ($user['role'] === 'admin' ? "admin_predefined_fields.php" : "financial_report_section.php"));
                exit;
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "Please fill in all fields";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Consortium Hub | Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    
    body {
      font-family: 'Inter', sans-serif;
    }
    
    .bg-animated {
      background: linear-gradient(-45deg, #1e40af, #2563eb, #374151, #1f2937);
      background-size: 400% 400%;
      animation: gradientBG 15s ease infinite;
    }
    
    @keyframes gradientBG {
      0% {background-position: 0% 50%;}
      50% {background-position: 100% 50%;}
      100% {background-position: 0% 50%;}
    }
    
    .login-card {
      backdrop-filter: blur(16px) saturate(180%);
      background-color: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 0 15px rgba(37, 99, 235, 0.1);
    }
    
    .input-field {
      transition: all 0.3s ease;
      background: rgba(255, 255, 255, 0.95);
    }
    
    .input-field:focus {
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }
    
    .floating-icon {
      color: #4b5563;
    }
    
    .login-btn {
      background: linear-gradient(to right, #2563eb, #1e40af);
      transition: all 0.3s ease;
    }
    
    .login-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 15px -3px rgba(37, 99, 235, 0.3);
    }
    
    .info-panel {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.5s ease-out;
    }
    
    .info-panel.expanded {
      max-height: 300px;
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-animated p-4">
  <div class="login-card rounded-xl p-8 w-full max-w-md">
    <!-- Logo & Title -->
    <div class="text-center mb-8">
      <div class="w-20 h-20 mx-auto mb-4 rounded-xl bg-blue-50 flex items-center justify-center shadow-sm">
        <i class="fas fa-users text-2xl text-blue-600"></i>
      </div>
      <h1 class="text-2xl font-bold text-gray-800">Consortium Hub</h1>
      <p class="text-gray-500 mt-2 text-sm">Enter your credentials to continue</p>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
      <div class="mb-6 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm flex items-center">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" class="space-y-4">
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <i class="far fa-envelope floating-icon"></i>
        </div>
        <input type="email" id="email" name="email" 
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
               class="input-field pl-10 pr-4 py-3 rounded-lg w-full border border-gray-300 focus:border-blue-500 focus:ring-0"
               placeholder="Email address"
               required>
      </div>

      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <i class="fas fa-lock floating-icon"></i>
        </div>
        <input type="password" id="password" name="password" 
               class="input-field pl-10 pr-4 py-3 rounded-lg w-full border border-gray-300 focus:border-blue-500 focus:ring-0"
               placeholder="Password"
               required>
      </div>

      <button type="submit" class="login-btn w-full py-3 text-white rounded-lg font-medium">
        Sign In
      </button>
    </form>


    <!-- Default Logins (Hidden by default) -->
   
  </div>

  <script>
    // Toggle info panel
    document.getElementById('infoToggle').addEventListener('click', function() {
      const panel = document.getElementById('infoPanel');
      const icon = this.querySelector('i');
      
      panel.classList.toggle('expanded');
      
      if (panel.classList.contains('expanded')) {
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
      } else {
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
      }
    });

    // Add subtle animation to input fields on focus
    document.querySelectorAll('input').forEach(input => {
      input.addEventListener('focus', (e) => {
        e.target.parentElement.querySelector('.floating-icon').style.color = '#2563eb';
      });
      
      input.addEventListener('blur', (e) => {
        e.target.parentElement.querySelector('.floating-icon').style.color = '#4b5563';
      });
    });
  </script>
</body>
</html>