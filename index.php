<?php

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If necessary, modify the path in the require statement below to refer to the
// location of your Composer autoload.php file.
require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('HTTP/1.1 404 Not Found');
    exit;
  }

use Rakit\Validation\Validator;

$validator = new Validator;

//dotenv requirement for .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

//validate
$validation = $validator->make($_POST, [
    'firstname'                  => 'required|min:4|max:30',
    'lastname'                  => 'required|min:4|max:30',
    'title'                  => 'required|min:3|max:50',
    'companyname'                  => 'required|min:3|max:50',
    'email'                 => 'required|email|max:50',
    'phonenumber'                 => 'required|min:10|max:20',
    'message'                  => 'required|min:4|max:1000',
]);



//google Recaptcha
$recaptcha = $_POST['g-recaptcha-response'];
$res = reCaptcha($recaptcha);
if(!$res['success']){
  // Error
  header('HTTP/1.1 500 Internal Server Error');
  die("Unable to verify request. Please try again.");
}

$validation->validate();

if ($validation->fails()) {
    // handling errors
    header('HTTP/1.1 500 Validation Error');
    $errors = $validation->errors();
    print_r($errors->firstOfAll());
    exit;
} else {
    // validation passes
    echo "Success!";
}


function reCaptcha($recaptcha){
    $secret = $_ENV['G_SECRET'];
    $ip = $_SERVER['REMOTE_ADDR'];
  
    $postvars = array("secret"=>$secret, "response"=>$recaptcha, "remoteip"=>$ip);
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);
    $data = curl_exec($ch);
    curl_close($ch);
  
    return json_decode($data, true);
  }



// Replace sender@example.com with your "From" address.
// This address must be verified with Amazon SES.
$sender = $_ENV['SES_SENDER_EMAIL'];
$senderName = $_ENV['SES_SENDER_NAME'];

// Replace recipient@example.com with a "To" address. If your account
// is still in the sandbox, this address must be verified.
$recipient = $_ENV['SES_RECIPIENT'];
$recipient_bcc = $_ENV['SES_RECIPIENT_BCC'];

// Replace smtp_username with your Amazon SES SMTP user name.
$usernameSmtp = $_ENV['SES_SMTP'];

// Replace smtp_password with your Amazon SES SMTP password.
$passwordSmtp = $_ENV['SES_SMTP_PASSWORD'];

// Specify a configuration set. If you do not want to use a configuration
// set, comment or remove the next line.
// $configurationSet = 'ConfigSet';

// If you're using Amazon SES in a region other than US West (Oregon),
// replace email-smtp.us-west-2.amazonaws.com with the Amazon SES SMTP
// endpoint in the appropriate region.
$host = $_ENV['SES_HOST'];
$port = $_ENV['SES_PORT'];

// The subject line of the email
$subject = 'Website Contact Request from ' . $_POST['firstname'] . ' ' . $_POST['lastname'];

// The plain-text body of the email
$bodyText =  "New Contact Request\r\n" .
"Name: ". $_POST['firstname'] . " " . $_POST['lastname'] . "\r\n".
"Title: ". $_POST['title'] . "\r\n".
"Company: ". $_POST['companyname'] . "\r\n".
"Email: ". $_POST['email'] . "\r\n".
"Phone: ". $_POST['phonenumber'] . "\r\n".
"Message: ". $_POST['message'] . "\r\n"
;

// The HTML-formatted body of the email
$bodyHtml = '<h1>Contact Request</h1>' .
    '<p>Name: '. $_POST['firstname'] . ' ' . $_POST['lastname'] . '</p>' .
    '<p>Title: '. $_POST['title'] . '</p>' .
    '<p>Company: ' . $_POST['companyname'] . '</p>' .
    '<p>Email: ' . $_POST['email'] . '</p>' .
    '<p>Phone: ' . $_POST['phonenumber'] . '</p>' .
    '<p>Message: ' . $_POST['message'] . '</p>';

$mail = new PHPMailer(true);

try {
    // Specify the SMTP settings.
    $mail->isSMTP();
    $mail->addReplyTo($_POST['email'], $_POST['firstname'] . ' ' . $_POST['lastname']);
    $mail->setFrom($sender, $senderName);
    $mail->Username   = $usernameSmtp;
    $mail->Password   = $passwordSmtp;
    $mail->Host       = $host;
    $mail->Port       = $port;
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = 'tls';
    // $mail->addCustomHeader('X-SES-CONFIGURATION-SET', $configurationSet);

    // Specify the message recipients.
    $mail->addAddress($recipient);
    if(!$recipient_bcc == "null"){
     $mail->addBCC($recipient_bcc);
    }
    // You can also add CC, BCC, and additional To recipients here.

    // Specify the content of the message.
    $mail->isHTML(true);
    $mail->Subject    = $subject;
    $mail->Body       = $bodyHtml;
    $mail->AltBody    = $bodyText;
    $mail->Send();
    echo "Email sent!" , PHP_EOL;
} catch (phpmailerException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "An error occurred. {$e->errorMessage()}", PHP_EOL; //Catch errors from PHPMailer.
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Email not sent. {$mail->ErrorInfo}", PHP_EOL; //Catch errors from Amazon SES.
}

?>