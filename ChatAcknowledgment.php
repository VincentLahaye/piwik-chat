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

class ChatAcknowledgment{
    public static function setLastViewed($idvisitor, $microtime, $fromAdmin = false)
    {
        $arguments = array(
            $fromAdmin,
            @Common::hex2bin($idvisitor),
            $microtime,
            $microtime,
        );

        Db::query("INSERT INTO " . Common::prefixTable('chat_history_admin') . " SET login = ?, idvisitor = ?, lastviewed = ? ON DUPLICATE KEY UPDATE lastviewed = ?", $arguments);

        return true;
    }

    public static function setLastSent($idsite, $idvisitor, $microtime)
    {
        foreach (ChatCommon::getUsersBySite($idsite) as $user) {

            $arguments = array(
                $user['login'],
                @Common::hex2bin($idvisitor),
                $microtime,
                $microtime,
            );

            Db::query("INSERT INTO " . Common::prefixTable('chat_history_admin') . " SET login = ?, idvisitor = ?, lastsent = ? ON DUPLICATE KEY UPDATE lastsent = ?", $arguments);

        }

        return true;
    }

    public static function getUnreadConversations($login)
    {
        $conversations = array();
        $rows = Db::fetchAll("SELECT idvisitor, lastviewed, lastsent FROM " . Common::prefixTable('chat_history_admin') . " WHERE login = ? AND lastsent > lastviewed", array($login));
        $rows = ChatCommon::formatRows($rows);

        foreach ($rows as $row) {
            $conversations[$row['idvisitor']] = $row;
        }

        return $conversations;
    }
}