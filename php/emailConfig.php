<?php
/**
 * Email Configuration
 * 
 * This file contains email server settings.
 * Update these values according to your email server configuration.
 */

return [
    // SMTP Server Configuration
    'smtp' => [
        'host' => 'smtp.gmail.com',           // SMTP server (Gmail: smtp.gmail.com, Outlook: smtp-mail.outlook.com)
        'port' => 587,                         // Port (587 for TLS, 465 for SSL)
        'secure' => 'tls',                     // 'tls' or 'ssl'
        'auth' => true,                        // Enable SMTP authentication
        'username' => 'fypassess@gmail.com', // Your SMTP email address (used for authentication)
        'password' => 'qscnlujhdckomsmy',     // Your email password or app password (NO SPACES)
    ],
    
    // Default sender information (the "From" address that recipients see)
    // NOTE: This email should match or be authorized by your SMTP server
    // For Gmail: Must match 'username' or be an authorized sender
    'from' => [
        'email' => 'fypassess@gmail.com',   // Sender email address (change this)
        'name' => 'FYPAssess System'           // Sender display name (change this if needed)
    ],
    
    // For development/testing: set to true to log emails instead of sending
    'test_mode' => false,  // Set to false to send real emails (requires valid SMTP credentials)
    
    // Test mode file path (where emails will be logged if test_mode is true)
    'test_mode_log' => __DIR__ . '/../logs/email_log.txt',
    
    // Test email recipient - All emails will be sent to this address for testing
    // Set to null to use actual recipient emails
    'test_email_recipient' => '214673@student.upm.edu.my'  // Temporary: route all notifications to test inbox
];
