<?php
/**
 * Email Configuration
 * 
 * This file contains email server settings.
 * Update these values according to your email server configuration.
 */

return [
  
    'smtp' => [
        'host' => 'smtp.gmail.com',         
        'port' => 587,                        
        'secure' => 'tls',                     
        'auth' => true,                        
        'username' => 'fypassess@gmail.com', 
        'password' => 'qscnlujhdckomsmy',    
    ],
    
  
    'from' => [
        'email' => 'fypassess@gmail.com',  
        'name' => 'FYPAssess System'           
    ],
    
    //change this to false to send real emails
    'test_mode' => true,  
    
    
    'test_mode_log' => __DIR__ . '/../logs/email_log.txt',
    
    'test_email_recipient' => 'saidahtulshaharudin@gmail.com'  
];
