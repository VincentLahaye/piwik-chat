<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Chat
 */
namespace Piwik\Plugins\Chat;

use Piwik\Db;
use Piwik\Mail;

class ChatMail {
    public static function sendNotificationToAdmin($idsite, $idvisitor, $message)
    {
        $visitorInfo = ChatPersonnalInformation::get($idvisitor);

        $subject = "New message on " . ChatSite::getSiteName($idsite);

        $mail = new Mail();
        $mail->setFrom(Config::getInstance()->General['noreply_email_address'], "Piwik Chat");
        $mail->setSubject($subject);

        $mail->setBodyHtml("Name : ". $visitorInfo['name'] ."<br />
        Email : ". $visitorInfo['email'] ."<br />
        Phone : ". $visitorInfo['phone'] ."<br />
        Comments : ". $visitorInfo['comments'] ."<br />
        <br /><br />
        Message:<br />$message");

        foreach (self::getUsersBySite() as $user) {
            if (empty($user['email'])) {
                continue;
            }

            if(ChatPiwikUser::isStaffOnline($user['login'])){
                continue;
            }

            $mail->addTo($user['email']);

            try {
                $mail->send();
            } catch (Exception $e) {
                throw new Exception("An error occured while sending '$subject' " .
                    " to " . implode(', ', $mail->getRecipients()) .
                    ". Error was '" . $e->getMessage() . "'");
            }

            $mail->clearRecipients();
        }
    }
}