<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Chat;

use Piwik\Settings\UserSetting;

/**
 * Defines Settings for Chat.
 *
 * Usage like this:
 * $settings = new Settings('Chat');
 * $settings->displayHelp->getValue();
 *
 */
class Settings extends \Piwik\Plugin\Settings
{
    /** @var UserSetting */
    public $displayHelp;

    protected function init()
    {
        $this->setIntroduction('Here you can specify the settings for the Chat plugin.');
        $this->createDisplayHelpSetting();
    }

    private function createDisplayHelpSetting()
    {
        $this->displayHelp = new UserSetting('displayHelp', 'Display Help');
        $this->displayHelp->type = static::TYPE_BOOL;
        $this->displayHelp->uiControlType = static::CONTROL_CHECKBOX;
        $this->displayHelp->description = 'If enabled, it will display a help popup.';
        $this->displayHelp->defaultValue = true;

        $this->addSetting($this->displayHelp);
    }
}
