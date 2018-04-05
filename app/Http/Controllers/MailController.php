<?php
namespace App\Http\Controllers;

use Swift_Mailer;
use Swift_SmtpTransport;
use Swift_Message;
use App\Http\Core\BaseController;

class MailController extends BaseController
{
    public function mail()
    {
        // Create the Transport
        $transport = (new Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
          ->setUsername('zipidy.sp@gmail.com')
          ->setPassword('1234567890)(*&');

          // Create the Mailer using your created Transport
            $mailer = new Swift_Mailer($transport);

            // Create a message
            $message = new Swift_Message('Zipidy Alert');
            $message->setFrom(['zipidy.sp@gmail.com' => 'Zipidy Alert'])
              ->setTo(['nguyenhoangvu29@gmail.com'])
              ->setBody('Here is the message itself');

            // Send the message
            $result = $mailer->send($message);
    }
}
