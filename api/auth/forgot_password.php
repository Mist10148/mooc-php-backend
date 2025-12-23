<?php
/**
 * forgot_password.php
 * Handles password reset requests - sends reset email with token
 * 
 * Place this in: /api/auth/forgot_password.php
 */

// Load your existing config (adjust path as needed)
require_once __DIR__ . '/../config.php';

// Override CORS for more specific control (optional - your config already handles this)
header("Access-Control-Allow-Origin: https://mooc-frontend-myqa.onrender.com");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit();
}

// =============================================================================
// Email Configuration - Set these in your environment variables on Render
// =============================================================================
$SMTP_HOST = $_ENV['SMTP_SERVER'] ?? getenv('SMTP_SERVER') ?: 'smtp.gmail.com';
$SMTP_PORT = $_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587;
$SMTP_USERNAME = $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?: '';
$SMTP_PASSWORD = str_replace(' ', '', $_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?: '');
$SENDER_EMAIL = $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?: '';
$SENDER_NAME = 'SilayLearn';
$FRONTEND_URL = $_ENV['FRONTEND_URL'] ?? getenv('FRONTEND_URL') ?: 'https://mooc-frontend-myqa.onrender.com';

// =============================================================================
// PHPMailer - Already loaded via your config.php's vendor/autoload.php
// =============================================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// =============================================================================
// Helper Functions
// =============================================================================

/**
 * Generate UUID v4 for reset token
 */
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Create HTML email body for password reset
 */
function createResetPasswordHtmlBody($resetLink) {
    $START_COLOR = "#1D4ED8";
    $END_COLOR = "#0D9488";
    $ACCENT_COLOR = "#0D9488";
    $BG_COLOR = "#f7f7f7";
    $CARD_BG = "#ffffff";
    $TEXT_COLOR = "#333333";
    $currentYear = date("Y");

    $GRADIENT_STYLE = "background-color: {$START_COLOR}; background-image: linear-gradient(to right, {$START_COLOR}, {$END_COLOR}); color: white; padding: 24px 20px; text-align: center;";

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: {$BG_COLOR}; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: {$CARD_BG}; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden;">
        
        <div style="{$GRADIENT_STYLE}">
            <h1 style="margin: 0; font-size: 24px; font-weight: bold;">
                Silay<span style="color: {$CARD_BG};">Learn</span>
            </h1>
        </div>

        <div style="padding: 30px 40px; color: {$TEXT_COLOR};">
            <h2 style="font-size: 20px; color: #1f2937; margin-top: 0; margin-bottom: 20px;">
                Reset Your Password
            </h2>
            <p style="margin-bottom: 25px; line-height: 1.6;">
                Click the button below to be taken to a secure page to set a new password.
            </p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{$resetLink}" 
                   target="_blank" 
                   style="display: inline-block; padding: 12px 25px; background-color: {$ACCENT_COLOR}; 
                          color: {$CARD_BG}; text-decoration: none; border-radius: 8px; 
                          font-weight: bold; font-size: 16px; box-shadow: 0 4px 8px rgba(13, 148, 136, 0.3);">
                    Set New Password
                </a>
            </div>

            <p style="font-size: 14px; margin-top: 30px; border-top: 1px solid #eeeeee; padding-top: 15px; color: #6b7280;">
                If you did not request a password reset, please ignore this email.
            </p>
        </div>

        <div style="background-color: {$BG_COLOR}; padding: 15px; text-align: center; font-size: 12px; color: #9ca3af;">
            &copy; {$currentYear} SilayLearn. All rights reserved.
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Send reset email using PHPMailer
 */
function sendResetEmail($toEmail, $resetToken) {
    global $SMTP_HOST, $SMTP_PORT, $SMTP_USERNAME, $SMTP_PASSWORD, $SENDER_EMAIL, $SENDER_NAME, $FRONTEND_URL;

    $resetLink = $FRONTEND_URL . "/reset-password?token=" . $resetToken;
    $subject = "Action Required: Reset Your SilayLearn Password";
    $htmlBody = createResetPasswordHtmlBody($resetLink);
    $plainTextBody = "Hello,\nYou requested a password reset. Please click the link below:\n{$resetLink}";

    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer not loaded - cannot send email");
        return [false, "Email service not configured. Please install PHPMailer via Composer."];
    }

    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USERNAME;
        $mail->Password   = $SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $SMTP_PORT;

        // Recipients
        $mail->setFrom($SENDER_EMAIL, $SENDER_NAME);
        $mail->addAddress($toEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainTextBody;

        $mail->send();
        return [true, "Email sent successfully"];

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        return [false, "Failed to send email: " . $mail->ErrorInfo];
    }
}

// =============================================================================
// Main Logic
// =============================================================================

try {
    // $pdo is already available from config.php
    global $pdo;

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $email = isset($data['email']) ? trim($data['email']) : '';

    // Validate email
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(["message" => "Email is required"]);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid email format"]);
        exit();
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Security: Don't reveal if email exists or not
        http_response_code(200);
        echo json_encode(["message" => "If an account exists, a password reset link has been sent."]);
        exit();
    }

    $userId = $user['id'];

    // Generate reset token
    $resetToken = generateUUID();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Send email first (before saving token)
    list($emailSent, $emailMessage) = sendResetEmail($email, $resetToken);

    if (!$emailSent) {
        error_log("Failed to send reset email to {$email}: {$emailMessage}");
        http_response_code(500);
        echo json_encode(["message" => "Failed to send email.", "error" => $emailMessage]);
        exit();
    }

    // Save token to database
    $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $resetToken, $expiresAt]);

    // Success
    http_response_code(200);
    echo json_encode(["message" => "Password reset link sent. Check your inbox."]);

} catch (PDOException $e) {
    error_log("Database error in forgot_password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Database error occurred."]);
} catch (Exception $e) {
    error_log("Exception in forgot_password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Server error: " . $e->getMessage()]);
}
?>