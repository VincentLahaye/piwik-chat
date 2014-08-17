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
use Piwik\Db;

class ChatSite {
    public static function getMainUrl($idsite)
    {
        return Db::fetchOne("SELECT main_url FROM " . Common::prefixTable('site') . " WHERE idsite = ?", array($idsite));
    }

    public static function getSiteName($idsite)
    {
        return Db::fetchOne("SELECT name FROM " . Common::prefixTable('site') . " WHERE idsite = ?", array($idsite));
    }
}