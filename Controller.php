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

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Db;
use Piwik\Mail;
use Piwik\Piwik;
use Piwik\Plugins\Goals\API as APIGoals;
use Piwik\Tracker;
use Piwik\Tracker\Visitor;
use Piwik\Url;
use Piwik\UrlHelper;
use Piwik\Version;
use Piwik\View;

define('PIWIK_ENABLE_TRACKING', true);
$GLOBALS['PIWIK_TRACKER_MODE'] = false;
$GLOBALS['PIWIK_TRACKER_DEBUG'] = false;
$GLOBALS['PIWIK_TRACKER_DEBUG_FORCE_SCHEDULED_TASKS'] = false;

/**
 *
 * @package Chat
 */
class Controller extends \Piwik\Plugin\ControllerAdmin
{
    public function index()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $idSite = Common::getRequestVar('idSite', null, 'int');

        $conversation = new Conversation($idSite);
        $messages = $conversation->getListConversations();
        $unread = $conversation->getUnreadConversations(Piwik::getCurrentUserLogin());

        $view = new View('@Chat/listConversations.twig');
        $view->messages = $messages;
        $view->unread = $unread;

        $settings = new Settings('Chat');
        $view->displayHelp = $settings->displayHelp->getValue();

        return $view->render();
    }

    /**
     * Echo's HTML for visitor profile popup.
     */
    public function getVisitorProfilePopup()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $idSite = Common::getRequestVar('idSite', null, 'int');
        $gotoChat = Common::getRequestVar('chat', false);
        $idvisitor = Common::getRequestVar('visitorId', null, 'string');

        if (!$gotoChat) {
            $gotoChat = (isset($_SESSION['chatViewByDefault'])) ? $_SESSION['chatViewByDefault'] : false;
        }

        $conversation = new Conversation($idSite, $idvisitor);
        $messages = $conversation->getAllMessages();
        $infos = $conversation->getPersonnalInformations();

        if (count($messages) > 0) {
            $lastMsgIndex = count($messages) - 1;
            $conversation->setLastViewed($messages[$lastMsgIndex]['microtime'], Piwik::getCurrentUserLogin());
        }

        $view = new View('@Chat/getVisitorProfilePopup.twig');
        $view->idSite = $idSite;
        $view->chat = $gotoChat;
        $view->goals = APIGoals::getInstance()->getGoals($idSite);
        $view->visitorData = Request::processRequest('Live.getVisitorProfile', array('checkForLatLong' => true));
        $view->exportLink = $this->getVisitorProfileExportLink();
        $view->messages = $messages;
        $view->infos = $infos;

        if (Common::getRequestVar('showMap', 1) == 1
            && isset($view->visitorData['hasLatLong'])
            && \Piwik\Plugin\Manager::getInstance()->isPluginLoaded('UserCountryMap')
        ) {
            //$view->userCountryMapUrl = $this->getUserCountryMapUrlForVisitorProfile();
        }

        $this->setWidgetizedVisitorProfileUrl($view);

        return $view->render();
    }

    public function setConversationViewByDefault()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $_SESSION['chatViewByDefault'] = (Common::getRequestVar('chat', false) == true) ? true : false;
    }

    public function popout()
    {
        header("Access-Control-Allow-Origin: *");

        $params = UrlHelper::getArrayFromQueryString ($_SERVER['QUERY_STRING']);
        $request = new Tracker\Request($params);

        // the IP is needed by isExcluded() and GoalManager->recordGoals()
        $ip = $request->getIp();
        $visitorInfo['location_ip'] = $ip;

        /**
         * Triggered after visits are tested for exclusion so plugins can modify the IP address
         * persisted with a visit.
         *
         * This event is primarily used by the **PrivacyManager** plugin to anonymize IP addresses.
         *
         * @param string &$ip The visitor's IP address.
         */
        Piwik::postEvent('Tracker.setVisitorIp', array(&$visitorInfo['location_ip']));

        /***
         * Visitor recognition
         */
        $settings = new Tracker\Settings($request, $visitorInfo['location_ip']);

        $visitor = new Visitor($request, $settings, $visitorInfo);
        $visitor->recognize();

        $visitorInfo = $visitor->getVisitorInfo();

        /***
         * Segment recognition
         */
        /*$segment = new Segment("visitorConfigId==" . bin2hex($config['config_id']), 1);
        $query = $segment->getSelectQuery("idvisitor", "log_visit");

        $rows = Db::fetchAll($query['sql'], $query['bind']);

        print_r($query);
        print_r($rows);*/

        $idSite = Common::getRequestVar('idsite', null, 'int');

        $conversation = new Conversation($idSite, bin2hex($visitorInfo['idvisitor']));
        $messages = $conversation->getAllMessages();

        if (count($messages) == 0) {
            $_SESSION['popoutState'] = 2;
        } elseif (!isset($_SESSION['popoutState']) || $_SESSION['popoutState'] != 1) {
            $_SESSION['popoutState'] = 4;
        }

        $view = new View('@Chat/popout.twig');
        $view->messages = $messages;
        $view->state = $_SESSION['popoutState'];
        $view->idvisitor = bin2hex($visitorInfo['idvisitor']);
        $view->timeLimit = time() - (2 * 60 * 60);
        $view->isStaffOnline = $conversation->isStaffOnline();
        $view->siteUrl = Conversation::getSiteMainUrl($idSite);

        return $view->render();
    }

    public function help()
    {
        $view = new View('@Chat/help.twig');
        return $view->render();
    }

    public function toggleHelpPopup()
    {
        $settings = new Settings('Chat');
        $state = !$settings->displayHelp->getValue();
        $settings->displayHelp->setValue($state);
        $settings->save();
    }

    public function reportBug()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');

        $jsonConfig = json_decode(file_get_contents(getcwd() . '/plugins/Chat/plugin.json'), true);

        $view = new view('@Chat/reportBug.twig');
        $view->piwikVersion = Version::VERSION;
        $view->chatVersion = $jsonConfig['version'];
        $view->email = Piwik::getCurrentUserEmail();
        $view->website = Conversation::getSiteMainUrl($idSite);
        $view->idSite = $idSite;
        $view->displayNotice = Common::getRequestVar('submittedBugReport', '0', 'int');

        return $view->render();
    }

    public function sendBug()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        $email = Common::getRequestVar('email', null);
        $name = Common::getRequestVar('name', null);
        $website = Common::getRequestVar('website', null);
        $message = Common::getRequestVar('message', null);
        $jsonConfig = json_decode(file_get_contents(getcwd() . '/plugins/Chat/plugin.json'), true);

        if($idSite != null && $email != null && $name != null && $website != null && $message != null){
            $mail = new Mail();
            $mail->setFrom(($email != null) ? $email : Piwik::getCurrentUserEmail(), ($name != null) ? $name : Piwik::getCurrentUserLogin());
            $mail->setSubject("Bug report");

            $mail->setBodyHtml("Piwik Version : ". Version::VERSION ."<br />
            Chat Version : ". $jsonConfig['version'] ."<br />
            Website : ". $website ."<br /><br /><br />
            Message:<br />" . $message);

            $mail->addTo($jsonConfig['authors'][0]['email']);

            try {
                $mail->send();
            } catch (Exception $e) {
                throw new Exception("An error occured while sending 'Bug Report' to ". implode(', ', $mail->getRecipients()) ." Error was '" . $e->getMessage() . "'");
            }

            return true;
        }
    }

    /*public function automaticmessages()
    {
        $idsite = common::getrequestvar('idsite', null, 'int');

        $conversation = new conversation($idsite);
        $messages = $conversation->getallautomaticmessage();

        $view = new view('@chat/listautomaticmessages.twig');
        $view->messages = $messages;

        return $view->render();
    }

    public function addautomaticmessages()
    {
        $view = new view('@chat/addorupdateautomaticmessages.twig');

        return $view->render();
    }*/

    private function getVisitorProfileExportLink()
    {
        return Url::getCurrentQueryStringWithParametersModified(array(
            'module' => 'API',
            'action' => 'index',
            'method' => 'Live.getVisitorProfile',
            'format' => 'XML',
            'expanded' => 1
        ));
    }

    private function setWidgetizedVisitorProfileUrl($view)
    {
        if (\Piwik\Plugin\Manager::getInstance()->isPluginLoaded('Widgetize')) {
            $view->widgetizedLink = Url::getCurrentQueryStringWithParametersModified(array(
                'module' => 'Widgetize',
                'action' => 'iframe',
                'moduleToWidgetize' => 'Live',
                'actionToWidgetize' => 'getVisitorProfilePopup'
            ));
        }
    }
}
