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

class ChatPersonnalInformation {
    public static function get($idvisitor)
    {
        $row = Db::fetchRow("SELECT * FROM " . Common::prefixTable('chat_personnal_informations') . " WHERE idvisitor = ?", array(@Common::hex2bin($idvisitor)));

        if (!$row) {
            $row = array(
                'name' => NULL,
                'email' => NULL,
                'phone' => NULL,
                'comments' => NULL
            );
        } else {
            $row = ChatCommon::formatRow($row);
        }

        return $row;
    }

    public static function update($idvisitor, $name = false, $email = false, $phone = false, $comments = false)
    {
        if ($name == false && $email == false && $phone == false && $comments == false)
            return false;

        $buildQuery = "";

        $argSet = array();
        $argOnUpdate = array();

        $argSet[] = @Common::hex2bin($idvisitor);

        if ($name) {
            $argSet[] = $name;
            $argOnUpdate[] = $name;

            $buildQuery .= " name = ?,";
        }

        if ($email) {
            $argSet[] = $email;
            $argOnUpdate[] = $email;

            $buildQuery .= " email = ?,";
        }

        if ($phone) {
            $argSet[] = $phone;
            $argOnUpdate[] = $phone;

            $buildQuery .= " phone = ?,";
        }

        if ($comments) {
            $argSet[] = $comments;
            $argOnUpdate[] = $comments;

            $buildQuery .= " comments = ?,";
        }

        $buildQuery = trim(substr_replace($buildQuery, "", -1));
        $arguments = array_merge($argSet, $argOnUpdate);

        Db::query("INSERT INTO " . Common::prefixTable('chat_personnal_informations') . " SET idvisitor = ?, $buildQuery ON DUPLICATE KEY UPDATE $buildQuery", $arguments);

        return true;
    }
}