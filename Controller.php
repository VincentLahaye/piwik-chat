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
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Mail;
use Piwik\Piwik;
use Piwik\Plugins\Goals\API as APIGoals;
use Piwik\Segment;
use Piwik\Tracker;
use Piwik\Tracker\Visitor;
use Piwik\Tracker\Visit\VisitProperties;
use Piwik\Tracker\Request as TrackerRequest;
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
class Controller extends \Piwik\Plugin\Controller
{
    public function index()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $idSite         = Common::getRequestVar('idSite', null, 'int');
        $conversation   = new ChatConversation($idSite);
        $messages       = $conversation->getListConversations();
        $unread         = ChatAcknowledgment::getUnreadConversations(Piwik::getCurrentUserLogin());
        $settings       = new Settings('Chat');

        return $this->renderTemplate('index', array(
            'messages' => $messages,
            'unread' => $unread,
            'displayHelp' => $settings->displayHelp->getValue()
        ));
    }

    /**
     * Echo's HTML for visitor profile popup.
     */
    public function getVisitorProfilePopup()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $idSite = Common::getRequestVar('idSite', null, 'int');
        $gotoChat = Common::getRequestVar('chat', '0', 'int');
        $idvisitor = Common::getRequestVar('visitorId', null, 'string');

        if (!$gotoChat) {
            $gotoChat = (isset($_SESSION['chatViewByDefault'])) ? $_SESSION['chatViewByDefault'] : false;
        }

        $conversation = new ChatConversation($idSite, $idvisitor);
        $messages = $conversation->getAllMessages();
        $infos = ChatPersonnalInformation::get($idvisitor);

        if (count($messages) > 0) {
            $lastMsgIndex = count($messages) - 1;
            ChatAcknowledgment::setLastViewed($idvisitor, $messages[$lastMsgIndex]['microtime'], Piwik::getCurrentUserLogin());
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
            && !empty($view->visitorData['hasLatLong'])
            && \Piwik\Plugin\Manager::getInstance()->isPluginLoaded('UserCountryMap')
        ) {
            $view->userCountryMapUrl = $this->getUserCountryMapUrlForVisitorProfile();
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
        $request = new TrackerRequest($params);

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

        if(!$request->getVisitorId())
            return "Who are you ?";

        $idSite = Common::getRequestVar('idsite', null, 'int');

        $conversation = new ChatConversation($idSite, bin2hex($request->getVisitorId()));

        /***
         * Segment recognition
         */
        foreach(ChatAutomaticMessage::getAll($idSite) as $autoMsg){
            $segment = ChatSegment::get($autoMsg['segmentID']);

            $fetchSegment = new Segment($segment['definition'], array($idSite));
            $query = $fetchSegment->getSelectQuery("idvisitor", "log_visit", "log_visit.idvisitor = ?", array($visitorInfo['idvisitor']));

            $rows = Db::fetchAll($query['sql'], $query['bind']);

            if(count($rows) == 0)
                continue;

            if($autoMsg['segmentID'] != $segment['idsegment'])
                continue;

            $getAlreadyReceivedMsg = $conversation->getAutomaticMessageReceivedById($autoMsg['id']);

            if(count($getAlreadyReceivedMsg) > 0){
                // If the AutoMsg is a "one shot"
                if($autoMsg['frequency'] == 0)
                    continue;

                if($autoMsg['frequency'] != 0){
                    // Now, we gonna try to define when the last AutoMsg received has been sent
                    list($freqTime, $freqScale) = explode('|', $autoMsg['frequency']);

                    if($freqScale == "w")
                        $dayMultiplier = 7;
                    elseif($freqScale == "m")
                        $dayMultiplier = 30;
                    else
                        $dayMultiplier = 1;

                    $secToWait = 3600 * 24 * $freqTime * $dayMultiplier;

                    // Is it older than the time range needed to wait ?
                    if(($getAlreadyReceivedMsg[0]['microtime'] + $secToWait) > microtime(true))
                        continue;
                }
            }

            $conversation->sendMessage($autoMsg['message'], $autoMsg['transmitter'], $autoMsg['id']);
        }

        $view = new View('@Chat/popout.twig');
        $view->idvisitor = bin2hex($request->getVisitorId());
        $view->idsite = $idSite;
        $view->timeLimit = time() - (2 * 60 * 60);
        $view->isStaffOnline = ChatPiwikUser::isStaffOnline();
        $view->siteUrl = ChatSite::getMainUrl($idSite);
        $view->lang = $visitorInfo['location_browser_lang'];

        return $view->render();
    }

    public function help()
    {
        Piwik::checkUserHasSomeAdminAccess();

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
        Piwik::checkUserHasSomeAdminAccess();

        $idSite = Common::getRequestVar('idSite', null, 'int');

        $jsonConfig = json_decode(file_get_contents(getcwd() . '/plugins/Chat/plugin.json'), true);

        $view = new view('@Chat/reportBug.twig');
        $view->piwikVersion = Version::VERSION;
        $view->chatVersion = $jsonConfig['version'];
        $view->email = Piwik::getCurrentUserEmail();
        $view->website = ChatSite::getMainUrl($idSite);
        $view->idSite = $idSite;
        $view->displayNotice = Common::getRequestVar('submittedBugReport', '0', 'int');

        return $view->render();
    }

    public function sendBug()
    {
        Piwik::checkUserHasSomeAdminAccess();

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

    public function automaticMessages()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $idSite = Common::getRequestVar('idSite', null, 'int');

        $view = new view('@Chat/listAutomaticMessages.twig');
        $view->messages = ChatAutomaticMessage::getAll($idSite);

        return $view->render();
    }

    public function addOrUpdateAutomaticMessage()
    {
        Piwik::checkUserHasSomeAdminAccess();

        // Get request variables
        $idSite     = Common::getRequestVar('idSite', null, 'int');
        $idAutoMsg  = Common::getRequestVar('idAutoMsg', '', 'int');
        $name       = Common::getRequestVar('name', '');
        $segment    = Common::getRequestVar('segment', '');
        $transmitter = Common::getRequestVar('transmitter', '');
        $message    = Common::getRequestVar('message', '');
        $freq       = Common::getRequestVar('frequency', '');
        $freqTime   = Common::getRequestVar('frequency-time', '1', 'int');
        $freqScale  = Common::getRequestVar('frequency-scale', 'd');

        if($idSite != '' && $name != '' && $segment != '' && $message != '' && $freq != '' && $transmitter != '')
        {
            $frequency = "0";
            if($freq != "once")
            {
                if($freqScale != "d" && $freqScale != "w" && $freqScale != "m")
                    $freqScale = "d";

                $frequency = $freqTime . "|" . $freqScale;
            }

            if($idAutoMsg){
                ChatAutomaticMessage::update($idAutoMsg, $idSite, $name, $segment, $message, $frequency, $transmitter);
            } else {
                ChatAutomaticMessage::add($idSite, $name, $segment, $message, $frequency, $transmitter);
            }

            return true;
        }

        // Display
        $view = new view('@Chat/addOrUpdateAutomaticMessage.twig');

        if($idAutoMsg){
            $autoMsg = ChatAutomaticMessage::get($idAutoMsg);

            $view->autoMsg = $autoMsg;
            $view->mode = 'update';

            if($autoMsg['frequency'] != "" && $autoMsg['frequency'] != "0")
                list($view->freqTime, $view->freqScale) = explode('|', $autoMsg['frequency']);

        } else {
            $view->mode = 'add';
        }

        $view->segments = ChatSegment::getAll($idSite);
        $view->idSite = $idSite;

        return $view->render();
    }

    public function deleteAutomaticMessage()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $idAutoMsg = Common::getRequestVar('idAutoMsg', null, 'int');

        if($idAutoMsg != null){
            ChatAutomaticMessage::delete($idAutoMsg);
            return true;
        }

        return false;
    }

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

    private function getUserCountryMapUrlForVisitorProfile()
    {
        $params = array(
            'module'             => 'UserCountryMap',
            'action'             => 'realtimeMap',
            'segment'            => self::getSegmentWithVisitorId(),
            'visitorId'          => false,
            'changeVisitAlpha'   => 0,
            'removeOldVisits'    => 0,
            'realtimeWindow'     => 'false',
            'showFooterMessage'  => 0,
            'showDateTime'       => 0,
            'doNotRefreshVisits' => 1
        );
        return Url::getCurrentQueryStringWithParametersModified($params);
    }

    private static function getSegmentWithVisitorId()
    {
        static $cached = null;
        if ($cached === null) {
            $segment = Request::getRawSegmentFromRequest();
            if (!empty($segment)) {
                $segment = urldecode($segment) . ';';
            }

            $idVisitor = Common::getRequestVar('visitorId', false);
            if ($idVisitor === false) {
                $idVisitor = Request::processRequest('Live.getMostRecentVisitorId');
            }

            $cached = urlencode($segment . 'visitorId==' . $idVisitor);
        }
        return $cached;
    }
}
