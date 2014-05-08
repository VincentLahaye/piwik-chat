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

use Piwik\Tracker\Visit;
use Piwik\Tracker\VisitExcluded;

class ChatVisit extends Visit
{
    public function handle()
    {
        $ip = $this->request->getIp();
        $this->visitorInfo['location_ip'] = $ip;

        $excluded = new VisitExcluded($this->request, $ip);
        if ($excluded->isExcluded()) {
            return;
        }

        $this->recognizeTheVisitor();
    }

    public function getVisitorInfo()
    {
        return $this->visitorInfo;
    }
}