<?php
/**
 * Email Sending Utility
 * 
 * This file provides a simple function to send emails using PHPMailer.
 * 
 * Installation:
 * 1. Install PHPMailer via Composer:
 *    composer require phpmailer/phpmailer
 * 
 * 2. Or download PHPMailer manually and include it:
 *    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
 *    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
 *    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
 */

// For Composer autoload (recommended)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    // Manual include if PHPMailer is installed manually in php/PHPMailer/
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
} else {
    // If PHPMailer is not found, create a fallback that uses PHP's mail() function
    // This allows the system to work even without PHPMailer (though less reliable)
    if (!function_exists('sendEmail')) {
        function sendEmailFallback($to, $subject, $body, $bodyType = 'html', $options = []) {
            global $emailConfig;
            
            $testRecipient = $emailConfig['test_email_recipient'] ?? null;
            $useTestEmail = !empty($testRecipient) && $emailConfig['test_mode'];
            
            // If test mode with test recipient, send to test email
            if ($useTestEmail) {
                $to = $testRecipient;
            }
            
            $headers = "From: " . ($options['from_email'] ?? $emailConfig['from']['email']) . "\r\n";
            $headers .= "Content-Type: text/" . ($bodyType === 'html' ? 'html' : 'plain') . "; charset=UTF-8\r\n";
            
            if (is_array($to)) {
                $to = implode(', ', $to);
            }
            
            $result = mail($to, $subject, $body, $headers);
            
            return [
                'success' => $result,
                'message' => $result ? 'Email sent successfully' . ($useTestEmail ? ' (to test email: ' . $testRecipient . ')' : '') : 'Failed to send email'
            ];
        }
        
        // Temporarily define these functions for fallback
        if (!function_exists('sendTextEmail')) {
            function sendTextEmail($to, $subject, $message, $options = []) {
                return sendEmailFallback($to, $subject, $message, 'text', $options);
            }
        }
        
        if (!function_exists('sendHtmlEmail')) {
            function sendHtmlEmail($to, $subject, $htmlBody, $options = []) {
                return sendEmailFallback($to, $subject, $htmlBody, 'html', $options);
            }
        }
        
        // Alias for sendEmail
        if (!function_exists('sendEmail')) {
            function sendEmail($to, $subject, $body, $bodyType = 'html', $options = []) {
                return sendEmailFallback($to, $subject, $body, $bodyType, $options);
            }
        }
        
        // Skip PHPMailer usage
        return;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load email configuration
$emailConfig = require __DIR__ . '/emailConfig.php';

/**
 * Send an email
 * 
 * @param string|array $to          Recipient email address(es) - string or array
 * @param string       $subject     Email subject
 * @param string       $body        Email body (HTML or plain text)
 * @param string       $bodyType    'html' or 'text' (default: 'html')
 * @param array        $options     Additional options:
 *                                  - 'from_email': Custom sender email
 *                                  - 'from_name': Custom sender name
 *                                  - 'reply_to': Reply-to email address
 *                                  - 'cc': CC email addresses (array)
 *                                  - 'bcc': BCC email addresses (array)
 *                                  - 'attachments': Array of file paths to attach
 * 
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmail($to, $subject, $body, $bodyType = 'html', $options = []) {
    global $emailConfig;
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $emailConfig['smtp']['host'];
        $mail->SMTPAuth   = $emailConfig['smtp']['auth'];
        $mail->Username   = $emailConfig['smtp']['username'];
        $mail->Password   = $emailConfig['smtp']['password'];
        $mail->SMTPSecure = $emailConfig['smtp']['secure'];
        $mail->Port       = $emailConfig['smtp']['port'];
        $mail->CharSet    = 'UTF-8';
        
        // Recipients
        $fromEmail = $options['from_email'] ?? $emailConfig['from']['email'];
        $fromName  = $options['from_name'] ?? $emailConfig['from']['name'];
        $mail->setFrom($fromEmail, $fromName);
        
        // Handle test email recipient override (for testing purposes)
        $actualRecipients = is_array($to) ? $to : [$to];
        $testRecipient = $emailConfig['test_email_recipient'] ?? null;
        $useTestEmail = !empty($testRecipient) && $emailConfig['test_mode'];
        
        if ($useTestEmail) {
            // In test mode, send to test email instead of actual recipients
            $mail->addAddress($testRecipient);
        } else {
            // Normal mode: use actual recipients
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    $mail->addAddress($recipient);
                }
            } else {
                $mail->addAddress($to);
            }
        }
        
        // Reply-to
        if (isset($options['reply_to'])) {
            $mail->addReplyTo($options['reply_to']);
        }
        
        // CC
        if (isset($options['cc']) && is_array($options['cc'])) {
            foreach ($options['cc'] as $cc) {
                $mail->addCC($cc);
            }
        }
        
        // BCC
        if (isset($options['bcc']) && is_array($options['bcc'])) {
            foreach ($options['bcc'] as $bcc) {
                $mail->addBCC($bcc);
            }
        }
        
        // Attachments
        if (isset($options['attachments']) && is_array($options['attachments'])) {
            foreach ($options['attachments'] as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }
        
        // Content
        $mail->isHTML($bodyType === 'html');
        
        // Modify subject if using test email recipient
        $finalSubject = $subject;
        if ($useTestEmail) {
            $originalRecipients = is_array($to) ? implode(', ', $to) : $to;
            $finalSubject = " $subject ";
        }
        
        $mail->Subject = $finalSubject;
        $mail->Body    = $body;
        
        // Plain text version for HTML emails
        if ($bodyType === 'html') {
            $mail->AltBody = strip_tags($body);
        }
        
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Email sent successfully'
        ];
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return [
            'success' => false,
            'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"
        ];
    }
}

/**
 * Log email for testing purposes (when test_mode is enabled)
 */
function logEmailForTesting($to, $subject, $body, $bodyType, $options) {
    global $emailConfig;
    
    $logFile = $emailConfig['test_mode_log'];
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = "========================================\n";
    $logEntry .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $logEntry .= "To: " . (is_array($to) ? implode(', ', $to) : $to) . "\n";
    $logEntry .= "Subject: " . $subject . "\n";
    $logEntry .= "Body Type: " . $bodyType . "\n";
    if (isset($options['from_email'])) {
        $logEntry .= "From: " . $options['from_email'] . "\n";
    }
    $logEntry .= "---\n";
    $logEntry .= $body . "\n";
    $logEntry .= "========================================\n\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    return [
        'success' => true,
        'message' => 'Email logged to ' . $logFile . ' (test mode)'
    ];
}

/**
 * Send a simple text email (wrapper function)
 */
function sendTextEmail($to, $subject, $message, $options = []) {
    return sendEmail($to, $subject, $message, 'text', $options);
}

/**
 * Send an HTML email (wrapper function)
 */
function sendHtmlEmail($to, $subject, $htmlBody, $options = []) {
    return sendEmail($to, $subject, $htmlBody, 'html', $options);
}
