<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Password Reset Feature</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #4e73df;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border: 1px solid #bee5eb;
        }
        button {
            background-color: #4e73df;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 15px;
        }
        button:hover {
            background-color: #2e59d9;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .link {
            color: #4e73df;
            text-decoration: none;
            font-weight: bold;
        }
        .link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Password Reset Feature Setup</h1>
        
        <?php
        require_once __DIR__ . '/includes/db.php';
        
        $setup_complete = false;
        $errors = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
            // Check if table already exists
            $check = $conn->query("SHOW TABLES LIKE 'password_resets'");
            
            if ($check->num_rows > 0) {
                echo '<div class="info">ℹ️ Password reset table already exists. No action needed.</div>';
                $setup_complete = true;
            } else {
                // Create password_resets table
                $sql = "CREATE TABLE IF NOT EXISTS `password_resets` (
                  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
                  `email` varchar(100) NOT NULL,
                  `token` varchar(64) NOT NULL,
                  `expires_at` datetime NOT NULL,
                  `used` tinyint(1) DEFAULT 0,
                  `created_at` datetime DEFAULT current_timestamp(),
                  PRIMARY KEY (`reset_id`),
                  KEY `email` (`email`),
                  KEY `token` (`token`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
                
                if ($conn->query($sql) === TRUE) {
                    echo '<div class="success">✅ Password reset table created successfully!</div>';
                    $setup_complete = true;
                } else {
                    $errors[] = "Error creating table: " . $conn->error;
                }
            }
        }
        
        // Check current status
        $table_exists = false;
        $check = $conn->query("SHOW TABLES LIKE 'password_resets'");
        if ($check && $check->num_rows > 0) {
            $table_exists = true;
        }
        ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <strong>❌ Errors:</strong><br>
                <?php foreach ($errors as $error): ?>
                    • <?php echo htmlspecialchars($error); ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($table_exists): ?>
            <div class="success">
                <strong>✅ Setup Status: COMPLETE</strong><br>
                Password reset feature is ready to use!
            </div>
            
            <div class="info">
                <strong>📝 Next Steps:</strong><br>
                1. Configure email settings (see <a href="EMAIL_SETUP.md" class="link">EMAIL_SETUP.md</a>)<br>
                2. Test the forgot password feature at <a href="public/forgot_password.php" class="link">forgot_password.php</a><br>
                3. You can delete this setup file (setup_password_reset.php) once done
            </div>
            
            <div style="margin-top: 25px;">
                <h3>📊 Database Information</h3>
                <?php
                $count = $conn->query("SELECT COUNT(*) as count FROM password_resets")->fetch_assoc();
                echo "<p>Total password reset requests: <strong>" . $count['count'] . "</strong></p>";
                
                // Show recent requests (without showing sensitive token data)
                $recent = $conn->query("SELECT email, created_at, used, expires_at FROM password_resets ORDER BY created_at DESC LIMIT 5");
                if ($recent && $recent->num_rows > 0) {
                    echo "<h4>Recent Requests:</h4>";
                    echo "<table style='width: 100%; border-collapse: collapse;'>";
                    echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>Email</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>Created</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>Status</td>";
                    echo "</tr>";
                    
                    while ($row = $recent->fetch_assoc()) {
                        $status = $row['used'] ? '✅ Used' : (strtotime($row['expires_at']) < time() ? '⏰ Expired' : '🔄 Active');
                        echo "<tr>";
                        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['email']) . "</td>";
                        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['created_at'] . "</td>";
                        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $status . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                ?>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="public/login.php?role=resident" class="link">← Back to Login Page</a>
            </div>
            
        <?php else: ?>
            <div class="info">
                <strong>📋 Setup Instructions</strong><br>
                This will create the necessary database table for password reset functionality.
            </div>
            
            <form method="post">
                <button type="submit" name="setup">Run Setup</button>
            </form>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e3e6f0;">
            <h3>📚 Feature Overview</h3>
            <ul>
                <li><strong>Forgot Password:</strong> <a href="public/forgot_password.php" class="link">public/forgot_password.php</a></li>
                <li><strong>Reset Password:</strong> public/reset_password.php?token=...</li>
                <li><strong>Email Template:</strong> Responsive HTML email with reset link</li>
                <li><strong>Security:</strong> Token expires in 1 hour, one-time use only</li>
                <li><strong>Validation:</strong> Email must exist in database (resident only)</li>
            </ul>
        </div>
    </div>
</body>
</html>
