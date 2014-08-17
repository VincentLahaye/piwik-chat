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

class ChatCommon {

    public static function getUsersBySite($idsite)
    {
        $getRegularUsers = Db::fetchAll("SELECT login,
        (SELECT email FROM ". Common::prefixTable('user') ." WHERE login = acc.login) AS email
        FROM " . Common::prefixTable('access') . " AS acc WHERE idsite = ?", array($idsite));

        $getSuperUsers = Db::fetchAll("SELECT login,email FROM " . Common::prefixTable('user') . " WHERE superuser_access = 1");

        $getUsers = array_merge($getRegularUsers, $getSuperUsers);

        return $getUsers;
    }

    public static function formatRows($rows)
    {
        for ($i = 0, $len = count($rows); $i < $len; $i++) {
            $rows[$i] = self::formatRow($rows[$i]);
        }

        return $rows;
    }

    public static function formatRow($row)
    {
        if (isset($row['idvisitor']))
            $row['idvisitor'] = bin2hex($row['idvisitor']);

        if (isset($row['microtime'])) {
            $row['date'] = date('d/m/Y', $row['microtime']);
            $row['time'] = date('H:i', $row['microtime']);
        }

        if (isset($row['lastsent']))
            $row['lastsent_clean'] = date('d/m/Y H:i', $row['lastsent']);

        return $row;
    }
}