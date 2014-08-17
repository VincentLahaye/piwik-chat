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
use Piwik\Config;
use Piwik\Db;
use Piwik\Mail;
use Zend_Mime;

/**
 * @package Chat
 */
class ChatConversation
{
    private $idsite;
    private $idvisitor;

    public function __construct($idsite, $idvisitor = false)
    {
        $this->idsite = $idsite;
        $this->idvisitor = $idvisitor;
    }

    public function sendMessage($content, $fromAdmin = false, $idAutoMsg = false)
    {
        $hexVisitorId = Common::convertVisitorIdToBin($this->idvisitor);
        $sanitizeContent = Common::sanitizeInputValues($content);
        $additionnalParams = "";
        $microtime = microtime(true);

        $arguments = $initArgs = array(
            $this->idsite,
            $hexVisitorId,
            $sanitizeContent,
            $microtime
        );

        if ($idAutoMsg) {
            $additionnalParams .= ", idautomsg = ?";
            $arguments[] = $idAutoMsg;
        }

        if ($fromAdmin) {
            $additionnalParams .= ", answerfrom = ?";
            $arguments[] = $fromAdmin;
        }

        $queryResult = Db::query("INSERT INTO " . Common::prefixTable('chat') . " SET idsite = ?, idvisitor = ?, content = ?, microtime = ?$additionnalParams", $arguments);

        if (!$fromAdmin) {
            ChatAcknowledgment::setLastSent($this->idsite, $this->idvisitor, $microtime);
            ChatMail::sendNotificationToAdmin($this->idsite, $this->idvisitor, $sanitizeContent);
        }

        $insertedRow = Db::fetchRow("SELECT * FROM " . Common::prefixTable('chat') . " WHERE idsite = ? AND idvisitor = ? AND content = ? AND microtime = ?", $initArgs);

        return $insertedRow;
    }

    public function getNewMessages($microtime, $fromAdmin = false)
    {
        $idvisitor = $getidvisitor = "";
        $arguments = array();

        if ($fromAdmin) {

            $answerfrom = "answerfrom IS NULL";

            if ($this->idvisitor) {
                $idvisitor = "idvisitor = ? AND";
                $arguments[] = @Common::hex2bin($this->idvisitor);
            } else {
                $getidvisitor = ", idvisitor";
            }
        } else {
            $answerfrom = "answerfrom IS NOT NULL";
            $idvisitor = "idvisitor = ? AND";
            $arguments[] = @Common::hex2bin($this->idvisitor);
        }

        $arguments[] = $this->idsite;
        $arguments[] = $microtime;

        $rows = Db::fetchAll("SELECT idmessage, content, answerfrom, microtime$getidvisitor FROM " . Common::prefixTable('chat') . " WHERE $idvisitor idsite = ? AND $answerfrom AND microtime > ?", $arguments);

        $rows = ChatCommon::formatRows($rows);

        return $rows;
    }

    public function getAllMessages($microtime = false)
    {
        $additionnalParams = "";

        $arguments = array(
            $this->idsite,
            @Common::hex2bin($this->idvisitor)
        );

        if($microtime != false){
            $additionnalParams = " AND microtime > ?";
            $arguments[] = $microtime;
        }

        $rows = Db::fetchAll("SELECT idmessage, content, answerfrom, microtime, idautomsg FROM " . Common::prefixTable('chat') . " WHERE idsite = ? AND idvisitor = ?". $additionnalParams, $arguments);

        $rows = ChatCommon::formatRows($rows);

        return $rows;
    }

    public function getVisitorLastMessage()
    {
        $arguments = array(
            $this->idsite,
            @Common::hex2bin($this->idvisitor)
        );

        $row = Db::fetchRow("SELECT idvisitor, answerfrom, content, microtime,
		(SELECT name FROM " . Common::prefixTable('chat_personnal_informations') . " WHERE idvisitor = chat.idvisitor) AS name
		FROM " . Common::prefixTable('chat') . " AS chat WHERE idsite = ? AND idvisitor = ? ORDER BY microtime DESC LIMIT 1", $arguments);
        $row = ChatCommon::formatRow($row);

        return $row;
    }

    public function getAutomaticMessageReceivedById($idAutoMsg)
    {
        $arguments = array(
            $this->idsite,
            @Common::hex2bin($this->idvisitor),
            $idAutoMsg
        );

        $rows = Db::fetchAll("SELECT * FROM " . Common::prefixTable('chat') . " WHERE idsite = ? AND idvisitor = ? AND idautomsg = ? ORDER BY microtime DESC", $arguments);
        $rows = ChatCommon::formatRows($rows);

        return $rows;
    }

    public function getListConversations()
    {
        $rows = Db::fetchAll("SELECT
			idvisitor, 
			MAX(idmessage) AS maxid, 
			(SELECT t2.content FROM " . Common::prefixTable('chat') . " AS t2 WHERE t2.idvisitor = t1.idvisitor ORDER BY t2.idmessage DESC LIMIT 1) AS content,
			(SELECT t2.microtime FROM " . Common::prefixTable('chat') . " AS t2 WHERE t2.idvisitor = t1.idvisitor ORDER BY t2.idmessage DESC LIMIT 1) AS microtime,
			(SELECT t3.name FROM " . Common::prefixTable('chat_personnal_informations') . " AS t3 WHERE t3.idvisitor = t1.idvisitor) AS name,
			(SELECT t3.email FROM " . Common::prefixTable('chat_personnal_informations') . " AS t3 WHERE t3.idvisitor = t1.idvisitor) AS email
		FROM " . Common::prefixTable('chat') . " AS t1
		WHERE idsite = ?
		GROUP BY idvisitor
		ORDER BY microtime DESC", array($this->idsite));

        $rows = ChatCommon::formatRows($rows);

        return $rows;
    }

    public function poll($microtime = false, $admin = false)
    {
        session_write_close();

        $pollMs = 500000;
        $timeout = $counter = 30;
        $timeoutBuffer = 5;

        set_time_limit($timeout + $timeoutBuffer);

        if (microtime(true) > ($microtime + 10))
            $microtime = microtime(true);

        ChatPiwikUser::setLastPoll($microtime, $admin);

        while ($counter > 0) {

            $data = $this->getNewMessages($microtime, $admin);

            if (count($data) > 0) {
                break;
            } else {
                usleep($pollMs);
                $counter -= $pollMs / 1000000;
            }
        }

        if (count($data) > 0) {
            return $data;
        }

        return false;
    }
}