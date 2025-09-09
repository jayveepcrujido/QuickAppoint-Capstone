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
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // CHANGE THESE TO YOUR GMAIL INFO
        $mail->Username   = 'testtesttestter5@gmail.com';
        $mail->Password   = 'gfar sqlp mgly afwj'; // Google App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('arjohn818@gmail.com', 'LGU Quick Appoint');
        $mail->addAddress($recipientEmail, $recipientName);

        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Password';
        $mail->Body    = "
            <div style='font-family: Arial;'>
                <h2>LGU Quick Appoint - Password Reset</h2>
                <p>Hi <strong>{$recipientName}</strong>,</p>
                <p>Click the button below to reset your password:</p>
                <p><a href='{$resetLink}' style='padding:10px 20px;background:#2980b9;color:#fff;text-decoration:none;border-radius:5px;'>Reset Password</a></p>
                <p>If you did not request this, ignore this email.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}
