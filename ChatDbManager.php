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

class ChatDbManager {
    private static $upToDateVersion = 2;

    public static function getDbVersion(){
        $version = Db::fetchOne("SELECT option_value FROM " . Common::prefixTable('option') . " WHERE option_name = ?", array('version_Chat_DB'));

        if(!$version)
            $version = (Db::tableExists(Common::prefixTable('chat')) === false) ? 0 : 1;

        return $version;
    }

    public static function setDbVersion($version){
        Db::query("INSERT INTO " . Common::prefixTable('option') . " SET option_name = ?, option_value = ?, autoload = ? ON DUPLICATE KEY UPDATE option_value = ?", array('version_Chat_DB', $version, 0, $version));
        return;
    }

    public static function checkUpgrades(){
        $currentVersion = self::getDbVersion();

        for($i = $currentVersion + 1; $i <= self::$upToDateVersion; $i++){
            self::upgradeTo($i);
        }

        return;
    }

    static function uninstall(){
        $currentVersion = self::getDbVersion();

        for($i = $currentVersion; $i >= 0; $i--){
            self::downgradeFrom($i);
        }
    }

    public static function upgradeTo($version){
        switch($version){
            default:
            case 1:

            /***
             * This table stores all chat messages
             */
            $sql = "CREATE TABLE " . Common::prefixTable('chat') . " (
                        `idmessage` int(10) NOT NULL AUTO_INCREMENT,
                        `idsite` int(10) NOT NULL,
                        `idvisitor` binary(8) NOT NULL,
                        `answerfrom` varchar(20) DEFAULT NULL,
                        `content` varchar(1024) NOT NULL,
                        `microtime` varchar(15) NOT NULL,
                        PRIMARY KEY (`idmessage`)
                    );";

            /***
             * This table is used to store which messages are already seen or not
             */
            $sql .= "CREATE TABLE " . Common::prefixTable('chat_history_admin') . " (
                        `login` varchar(100) NOT NULL,
                        `idvisitor` binary(8) NOT NULL,
                        `lastviewed` varchar(15) DEFAULT '0',
                        `lastsent` varchar(15) DEFAULT '0',
                        UNIQUE KEY `login_idvisitor` (`login`,`idvisitor`),
                        KEY `lastviewed_idmessage` (`lastviewed`),
                        KEY `lastsend_idmessage` (`lastsent`)
                    );";

            /***
             * This table stores personnal informations about visitors
             */
            $sql .= "CREATE TABLE " . Common::prefixTable('chat_personnal_informations') . " (
                        `idvisitor` binary(8) NOT NULL,
                        `name` varchar(50) DEFAULT NULL,
                        `email` varchar(50) DEFAULT NULL,
                        `phone` varchar(30) DEFAULT NULL,
                        `comments` text,
                        PRIMARY KEY (`idvisitor`)
                    );";

            /***
             * This column is used to store the last 'poll' time
             * It allows to display on client side if the staff is online or not
             */
            $sql .= "ALTER TABLE " . Common::prefixTable('user') . " ADD COLUMN `last_poll` varchar(15) DEFAULT NULL;";

                break;

            case 2:

                $sql = "CREATE TABLE " . Common::prefixTable('chat_automatic_message') . " (
                        `id` int(10) NOT NULL AUTO_INCREMENT,
                        `name` varchar(100) NOT NULL,
                        `segment` int(10) NOT NULL,
                        `message` text,
                        `idsite` int(10) NOT NULL,
                        `frequency` varchar(100) NOT NULL DEFAULT '0',
                        `transmitter` varchar(100) NOT NULL DEFAULT '',
                        PRIMARY KEY (`id`)
                      );";

                $sql .= "ALTER TABLE " . Common::prefixTable('chat') . " ADD COLUMN `idautomsg` int(10) DEFAULT NULL;";

                break;
        }

        try {
            Db::exec($sql);
        } catch (Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }

        self::setDbVersion($version);
        return;
    }

    static function downgradeFrom($version){
        try {
            switch($version){
                case 2:
                    Db::dropTables(Common::prefixTable('chat_automatic_message'));

                    $sql = "ALTER TABLE " . Common::prefixTable('chat') . " DROP COLUMN `idautomsg`;";
                    Db::exec($sql);
                    break;

                case 1:
                    Db::dropTables(Common::prefixTable('chat'));
                    Db::dropTables(Common::prefixTable('chat_history_admin'));
                    Db::dropTables(Common::prefixTable('chat_personnal_informations'));

                    $sql = "ALTER TABLE " . Common::prefixTable('user') . " DROP COLUMN `last_poll`;";
                    Db::exec($sql);
                    break;
            }
        } catch (Exception $e) {
            throw $e;
        }

        self::setDbVersion($version - 1);
        return;
    }
}