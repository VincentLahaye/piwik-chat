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
use Piwik\IP;
use Piwik\Piwik;
use Piwik\Plugins\Goals\API as APIGoals;
use Piwik\Segment;
use Piwik\Site;
use Piwik\Tracker;
use Piwik\View;
use Piwik\Url;
use UserAgentParser;

//require_once PIWIK_INCLUDE_PATH . '/plugins/Chat/Visit.php';

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
		
		if(!$gotoChat){
			$gotoChat = (isset($_SESSION['chatViewByDefault'])) ? $_SESSION['chatViewByDefault'] : false;
		}
		
		$conversation = new Conversation($idSite, $idvisitor);
		$messages = $conversation->getAllMessages();
		$infos = $conversation->getPersonnalInformations();
		
		if(count($messages) > 0){
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

	public function popout(){
		header("Access-Control-Allow-Origin: pm.fr");
		
		$process = new ChatTracker();

		try {
			$process->main();
			$visitorInfo = $process->getVisitorInfo();
		} catch (Exception $e) {
			echo "Error:" . $e->getMessage();
		}
		
		if(!isset($visitorInfo['idvisitor']))
			return;


		//$segment = new Segment("browserCode==FF;visitorId==498e0cdb23c8b0f7", 1);
		//$query = $segment->getSelectQuery("idvisitor", "log_visit");
		
		//$rows = Db::fetchAll($query['sql'], $query['bind']);
		
		//print_r($rows);

		$idSite = Common::getRequestVar('idsite', null, 'int');

		$conversation = new Conversation($idSite, bin2hex($visitorInfo['idvisitor']));
		$messages = $conversation->getAllMessages();
		
		if(count($messages) == 0){
			$_SESSION['popoutState'] = 2;
		} elseif(!isset($_SESSION['popoutState']) || $_SESSION['popoutState'] != 1) {
			$_SESSION['popoutState'] = 4;
		}
		
		$view = new View('@Chat/popout.twig');
		$view->messages = $messages;
		$view->state = $_SESSION['popoutState'];
		$view->idvisitor = bin2hex($visitorInfo['idvisitor']);
		$view->timeLimit = time() - (2 * 60 * 60);
        $view->isStaffOnline = $conversation->isStaffOnline();
        $view->siteUrl = $conversation->getSiteMainUrl();

        return $view->render();
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
			 'module'   => 'API',
			 'action'   => 'index',
			 'method'   => 'Live.getVisitorProfile',
			 'format'   => 'XML',
			 'expanded' => 1
		));
    }
	
	private function setWidgetizedVisitorProfileUrl($view)
    {
        if (\Piwik\Plugin\Manager::getInstance()->isPluginLoaded('Widgetize')) {
            $view->widgetizedLink = Url::getCurrentQueryStringWithParametersModified(array(
				  'module'            => 'Widgetize',
				  'action'            => 'iframe',
				  'moduleToWidgetize' => 'Live',
				  'actionToWidgetize' => 'getVisitorProfilePopup'
			 ));
        }
    }
}
