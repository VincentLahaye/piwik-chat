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

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Tracker;
use Piwik\Tracker\Request;

class ChatTracker extends Tracker
{
	private $visitorInfo;

	/**
     * Returns the Tracker_Visit object.
     * This method can be overwritten to use a different Tracker_Visit object
     *
     * @throws Exception
     * @return \Piwik\Tracker\Visit
     */
    protected function getNewVisitObject()
    {
        $visit = null;

        /**
         * Triggered before a new **visit tracking object** is created. Subscribers to this
         * event can force the use of a custom visit tracking object that extends from
         * {@link Piwik\Tracker\VisitInterface}.
         * 
         * @param \Piwik\Tracker\VisitInterface &$visit Initialized to null, but can be set to
         *                                              a new visit object. If it isn't modified
         *                                              Piwik uses the default class.
         */
        Piwik::postEvent('Tracker.makeNewVisitObject', array(&$visit));

        if (is_null($visit)) {
            $visit = new ChatVisit();
        } elseif (!($visit instanceof VisitInterface)) {
            throw new Exception("The Visit object set in the plugin must implement VisitInterface");
        }
        return $visit;
    }
	
	/**
     * @param $params
     * @param $tokenAuth
     * @return array
     */
    protected function trackRequest($params, $tokenAuth)
    {
        if ($params instanceof Request) {
            $request = $params;
        } else {
            $request = new Request($params, $tokenAuth);
        }

        $this->init($request);

        $isAuthenticated = $request->isAuthenticated();

        try {
            if ($this->isVisitValid()) {
                $visit = $this->getNewVisitObject();
                $request->setForcedVisitorId(self::$forcedVisitorId);
                $request->setForceDateTime(self::$forcedDateTime);
                $request->setForceIp(self::$forcedIpString);

                $visit->setRequest($request);
                $visit->handle();
				
				$this->visitorInfo = $visit->getVisitorInfo();
            } else {
                Common::printDebug("The request is invalid: empty request, or maybe tracking is disabled in the config.ini.php via record_statistics=0");
            }
        } catch (DbException $e) {
            Common::printDebug("<b>" . $e->getMessage() . "</b>");
            $this->exitWithException($e, $isAuthenticated);
        } catch (Exception $e) {
            $this->exitWithException($e, $isAuthenticated);
        }
        $this->clear();

        // increment successfully logged request count. make sure to do this after try-catch,
        // since an excluded visit is considered 'successfully logged'
        //++$this->countOfLoggedRequests;
        return $isAuthenticated;
    }
	
	/**
     * Cleanup
     */
    protected function end()
    {
        switch ($this->getState()) {
            case self::STATE_LOGGING_DISABLE:
                //$this->outputTransparentGif();
                Common::printDebug("Logging disabled, display transparent logo");
                break;

            case self::STATE_EMPTY_REQUEST:
                Common::printDebug("Empty request => Piwik page");
                echo "<a href='/'>Piwik</a> is a free open source web <a href='http://piwik.org'>analytics</a> that lets you keep control of your data.";
                break;

            case self::STATE_NOSCRIPT_REQUEST:
            case self::STATE_NOTHING_TO_NOTICE:
            default:
                //$this->outputTransparentGif();
                Common::printDebug("Nothing to notice => default behaviour");
                break;
        }
        Common::printDebug("End of the page.");

        if ($GLOBALS['PIWIK_TRACKER_DEBUG'] === true) {
            if (isset(self::$db)) {
                self::$db->recordProfiling();
                Profiler::displayDbTrackerProfile(self::$db);
            }
        }

        self::disconnectDatabase();
    }
	
	public function getVisitorInfo()
	{
		return $this->visitorInfo;
	}
}