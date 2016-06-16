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

class ChatPiwikUser 
{
    public static function setLastPoll($lastPoll, $login)
    {
        return Db::query("UPDATE " . Common::prefixTable('user') . " SET last_poll = ? WHERE login = ?", array($lastPoll, $login));
    }

    public static function getLastPoll($login = false)
    {
        $query = ($login) ? "WHERE login = ?" : "ORDER BY last_poll DESC LIMIT 1";
        return Db::fetchOne("SELECT last_poll FROM " . Common::prefixTable('user') . " " . $query, ($login) ? array($login) : array());
    }

    public static function isStaffOnline($login = false)
    {
        $timeout = 2; // in minutes
        $lastPoll = self::getLastPoll($login);

        return !($lastPoll < (microtime(true) - ($timeout * 60)));
    }
}