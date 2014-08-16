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

use Exception;
use Piwik\Access;
use Piwik\Piwik;
use Piwik\View;

/**
 * @see plugins/Chat/Conversation.php
 */
require_once PIWIK_INCLUDE_PATH . '/plugins/Chat/Conversation.php';

/**
 * API for plugin Conversation
 *
 * @package Conversation
 * @method static \Piwik\Plugins\Chat\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    public function poll($idSite, $visitorId = false, $microtime = false, $fromAdmin = false)
    {
        if ($fromAdmin) {
            $this->authenticate($idSite);
            $fromAdmin = Piwik::getCurrentUserLogin();
        } else {
            header("Access-Control-Allow-Origin: *");
        }

        $conversation = new Conversation($idSite, $visitorId);
        return $conversation->poll($microtime, $fromAdmin);
    }

    public function sendMessage($idSite, $visitorId, $message, $fromAdmin = false)
    {
        if ($fromAdmin) {
            $this->authenticate($idSite);
            $fromAdmin = Piwik::getCurrentUserLogin();
        } else {
            header("Access-Control-Allow-Origin: *");
        }

        $conversation = new Conversation($idSite, $visitorId);
        $conversation->sendMessage($message, $fromAdmin);

        return true;
    }

    public function setLastViewed($idSite, $visitorId, $messageId)
    {
        $this->authenticate($idSite);

        $conversation = new Conversation($idSite, $visitorId);
        $conversation->setLastViewed($messageId, Piwik::getCurrentUserLogin());

        return true;
    }

    public function getUnreadConversations($idSite)
    {
        $this->authenticate($idSite);

        $conversation = new Conversation($idSite);
        return $conversation->getUnreadConversations(Piwik::getCurrentUserLogin());
    }

    public function getVisitorLastMessage($idSite, $visitorId)
    {
        $this->authenticate($idSite);

        $conversation = new Conversation($idSite, $visitorId);
        return $conversation->getVisitorLastMessage();
    }

    public function updatePersonnalInformations($idSite, $visitorId, $name = false, $email = false, $phone = false, $comments = false)
    {
        if ($phone || $comments) {
            $this->authenticate($idSite);
        }

        $conversation = new Conversation($idSite, $visitorId);
        $conversation->updatePersonnalInformations($name, $email, $phone, $comments);

        return true;
    }

    public function isStaffOnline($idSite)
    {
        $conversation = new Conversation($idSite);
        return $conversation->isStaffOnline();
    }


    /** Do cookie authentication. This way, the token can remain secret. */
    private function authenticate($idSite)
    {
        /**
         * Triggered immediately before the user is authenticated.
         *
         * This event can be used by plugins that provide their own authentication mechanism
         * to make that mechanism available. Subscribers should set the `'auth'` object in
         * the {@link Piwik\Registry} to an object that implements the {@link Piwik\Auth} interface.
         *
         * **Example**
         *
         *     use Piwik\Registry;
         *
         *     public function initAuthenticationObject($allowCookieAuthentication)
         *     {
         *         Registry::set('auth', new LDAPAuth($allowCookieAuthentication));
         *     }
         *
         * @param bool $allowCookieAuthentication Whether authentication based on `$_COOKIE` values should
         *                                        be allowed.
         */
        Piwik::postEvent('Request.initAuthenticationObject', array($allowCookieAuthentication = true));

        $auth = \Piwik\Registry::get('auth');
        $success = Access::getInstance()->reloadAccess($auth);

        if (!$success) {
            throw new Exception('Authentication failed');
        }

        Piwik::checkUserHasViewAccess($idSite);
    }
}