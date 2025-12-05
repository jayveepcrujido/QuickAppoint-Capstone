<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer classes
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

function sendResetEmail($recipientEmail, $recipientName, $resetLink) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'testtesttestter5@gmail.com';
        $mail->Password   = 'gfar sqlp mgly afwj'; // Google App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender and recipient
        $mail->setFrom('testtesttestter5@gmail.com', 'LGU Quick Appoint');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->addReplyTo('testtesttestter5@gmail.com', 'LGU Support');

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - LGU Quick Appoint';
        $mail->Body    = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f4f4; padding: 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>LGU Quick Appoint</h1>
                                    <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 14px;'>Password Reset Request</p>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;'>
                                        Hi <strong>{$recipientName}</strong>,
                                    </p>
                                    <p style='color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 25px 0;'>
                                        We received a request to reset your password. Click the button below to create a new password:
                                    </p>
                                    
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td align='center' style='padding: 20px 0;'>
                                                <a href='{$resetLink}' style='display: inline-block; padding: 14px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;'>Reset Password</a>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <p style='color: #555; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;'>
                                        Or copy and paste this link into your browser:
                                    </p>
                                    <p style='color: #667eea; font-size: 13px; word-break: break-all; margin: 10px 0 20px 0;'>
                                        {$resetLink}
                                    </p>
                                    
                                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;'>
                                    
                                    <p style='color: #999; font-size: 13px; line-height: 1.6; margin: 0;'>
                                        <strong>If you didn't request this password reset</strong>, please ignore this email or contact our support team if you have concerns.
                                    </p>
                                    <p style='color: #999; font-size: 13px; line-height: 1.6; margin: 10px 0 0 0;'>
                                        This link will expire in 1 hour for security reasons.
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style='background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;'>
                                    <p style='color: #888; font-size: 12px; margin: 0 0 5px 0;'>
                                        © 2025 LGU Quick Appoint. All rights reserved.
                                    </p>
                                    <p style='color: #999; font-size: 11px; margin: 0;'>
                                        This is an automated message, please do not reply.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";

        // Plain text alternative
        $mail->AltBody = "Hi {$recipientName},\n\n"
                       . "We received a request to reset your password.\n\n"
                       . "Click this link to reset your password:\n{$resetLink}\n\n"
                       . "If you didn't request this, please ignore this email.\n\n"
                       . "This link will expire in 1 hour.\n\n"
                       . "LGU Quick Appoint Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error for debugging (optional)
        error_log("Email Error: " . $mail->ErrorInfo);
        return "Failed to send email. Please try again later.";
    }
}