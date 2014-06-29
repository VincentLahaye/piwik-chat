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
use Piwik\Piwik;

/**
 * @package Chat
 */
class Chat extends \Piwik\Plugin
{
    /**
     * @see Piwik\Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        return array(
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getCssFiles',
            'Menu.Reporting.addItems' => 'getReportingMenuItems',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
        );
    }

    public function getJsFiles(&$files)
    {
        $files[] = 'plugins/Chat/javascripts/backend/chat.js';
    }

    public function getCssFiles(&$files)
    {
        $files[] = 'plugins/Chat/stylesheets/backend/chat.css';
    }

    public function getReportingMenuItems()
    {
        \Piwik\Menu\MenuMain::getInstance()->add(
            $category = 'Chat',
            $title = '',
            $urlParams = array('module' => $this->getPluginName(), 'action' => 'index'),
            $showOnlyIf = Piwik::hasUserSuperUserAccess(),
            $order = 11
        );

        \Piwik\Menu\MenuMain::getInstance()->add(
            $category = 'Chat',
            $title = 'Conversations',
            $urlParams = array('module' => $this->getPluginName(), 'action' => 'index'),
            $showOnlyIf = Piwik::hasUserSuperUserAccess(),
            $order = 12
        );

        /*\Piwik\Menu\MenuMain::getInstance()->add(
            $category = 'Chat',
            $title = 'Reports',
            $urlParams = array('module' => $this->getPluginName(), 'action' => 'index'),
            $showOnlyIf = Piwik::hasUserSuperUserAccess(),
            $order = 13
        );*/

        /*\Piwik\Menu\MenuMain::getInstance()->add(
            $category = 'Chat',
            $title = 'Automatic messages',
            $urlParams = array('module' => $this->getPluginName(), 'action' => 'automaticMessages'),
            $showOnlyIf = Piwik::hasUserSuperUserAccess(),
            $order = 14
        );*/
    }

    public function install()
    {
        try {
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

            Db::exec($sql);

        } catch (Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
    }

    public function uninstall()
    {
        try {
            Db::dropTables(Common::prefixTable('chat'));
            Db::dropTables(Common::prefixTable('chat_history_admin'));
            Db::dropTables(Common::prefixTable('chat_personnal_informations'));

            $sql = "ALTER TABLE " . Common::prefixTable('user') . " DROP COLUMN `last_poll`;";
            Db::exec($sql);

        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'Chat_Visitor';
    }
}
