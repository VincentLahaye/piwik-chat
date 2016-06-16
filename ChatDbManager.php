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
use Piwik\DbHelper;

class ChatDbManager
{
    public static function uninstall()
    {
        Db::dropTables(array(
            Common::prefixTable('chat'),
            Common::prefixTable('chat_history_admin'),
            Common::prefixTable('chat_personal_informations'),
            Common::prefixTable('chat_automatic_message')
        ));

        $sql = "ALTER TABLE " . Common::prefixTable('user') . " DROP COLUMN `last_poll`;";
        Db::exec($sql);
    }

    public static function install()
    {
        /***
         * Stores all chat messages
         */
        $tableChat = "`idmessage` int(10) NOT NULL AUTO_INCREMENT,
                      `idsite` int(10) NOT NULL,
                      `idvisitor` binary(8) NOT NULL,
                      `answerfrom` varchar(20) DEFAULT NULL,
                      `content` varchar(1024) NOT NULL,
                      `microtime` varchar(15) NOT NULL,
                      `idautomsg` int(10) DEFAULT NULL,
                      PRIMARY KEY (`idmessage`)";

        DbHelper::createTable('chat', $tableChat);

        /***
         * Used to store which messages have already been seen or not
         */
        $tableHistoryAdmin = "`login` varchar(100) NOT NULL,
                              `idvisitor` binary(8) NOT NULL,
                              `lastviewed` varchar(15) DEFAULT '0',
                              `lastsent` varchar(15) DEFAULT '0',
                              UNIQUE KEY `login_idvisitor` (`login`,`idvisitor`),
                              KEY `lastviewed_idmessage` (`lastviewed`),
                              KEY `lastsend_idmessage` (`lastsent`)";

        DbHelper::createTable('chat_history_admin', $tableHistoryAdmin);

        /***
         * This table stores personnal informations about visitors
         */
        $tablePersonalInfo = "`idvisitor` binary(8) NOT NULL,
                              `name` varchar(50) DEFAULT NULL,
                              `email` varchar(50) DEFAULT NULL,
                              `phone` varchar(30) DEFAULT NULL,
                              `comments` text,
                              PRIMARY KEY (`idvisitor`)";

        DbHelper::createTable('chat_personal_informations', $tablePersonalInfo);

        /***
         * This column is used to store the last 'poll' time
         * It allows to display on client side if the staff is online or not
         */
        $sql = "ALTER TABLE " . Common::prefixTable('user') . " ADD COLUMN `last_poll` varchar(15) DEFAULT NULL;";
        Db::exec($sql);

        $tableAutoMsg = "`id` int(10) NOT NULL AUTO_INCREMENT,
                         `name` varchar(100) NOT NULL,
                         `segment` int(10) NOT NULL,
                         `message` text,
                         `idsite` int(10) NOT NULL,
                         `frequency` varchar(100) NOT NULL DEFAULT '0',
                         `transmitter` varchar(100) NOT NULL DEFAULT '',
                         PRIMARY KEY (`id`)";

        DbHelper::createTable('chat_automatic_message', $tableAutoMsg);
    }
}