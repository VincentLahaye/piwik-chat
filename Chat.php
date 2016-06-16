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
        /*\Piwik\Menu\MenuMain::getInstance()->add(
            $category = 'Chat',
            $title = 'Report a bug',
            $urlParams = array('module' => $this->getPluginName(), 'action' => 'reportBug'),
            $showOnlyIf = Piwik::hasUserSuperUserAccess(),
            $order = 14
        );*/

        /*\Piwik\Menu\MenuMain::getInstance()->add(
            $category = 'Chat',
            $title = 'Reports',
            $urlParams = array('module' => $this->getPluginName(), 'action' => 'index'),
            $showOnlyIf = Piwik::hasUserSuperUserAccess(),
            $order = 13
        );*/
    }

    public function install()
    {
        ChatDbManager::install();
        return;
    }
    
    public function uninstall()
    {
        ChatDbManager::uninstall();
        return;
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'Chat_Visitor';
    }
}
