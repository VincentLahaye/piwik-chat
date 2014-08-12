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

/**
 * @package Chat
 */
class Conversation
{
    private $idsite;
    private $idvisitor;

    public function __construct($idsite, $idvisitor = false)
    {
        $this->idsite = $idsite;
        $this->idvisitor = $idvisitor;
    }

    public function sendMessage($content, $fromAdmin = false)
    {
        $hexVisitorId = Common::convertVisitorIdToBin($this->idvisitor);
        $sanitizeContent = Common::sanitizeInputValues($content);
        $answerfrom = "";
        $microtime = microtime(true);

        $arguments = array(
            $this->idsite,
            $hexVisitorId,
            $sanitizeContent,
            $microtime
        );

        if ($fromAdmin) {
            $answerfrom = ", answerfrom = ?";
            $arguments[] = $fromAdmin;
        }

        $queryResult = Db::query("INSERT INTO " . Common::prefixTable('chat') . " SET idsite = ?, idvisitor = ?, content = ?, microtime = ?$answerfrom", $arguments);

        if (!$fromAdmin) {
            $this->setIsNew($microtime);
        }

        return $queryResult;
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

        $rows = $this->formatRows($rows);

        return $rows;
    }

    public function getAllMessages()
    {
        $arguments = array(
            $this->idsite,
            @Common::hex2bin($this->idvisitor)
        );

        $rows = Db::fetchAll("SELECT idmessage, content, answerfrom, microtime FROM " . Common::prefixTable('chat') . " WHERE idsite = ? AND idvisitor = ?", $arguments);
        $rows = $this->formatRows($rows);

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
        $row = $this->formatRow($row);

        return $row;
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

        $rows = $this->formatRows($rows);

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

        $this->setLastPoll($microtime, $admin);

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

    /**************************************************************************
     * History Admin
     **************************************************************************/
    public function setLastViewed($microtime, $fromAdmin = false)
    {
        $arguments = array(
            $fromAdmin,
            @Common::hex2bin($this->idvisitor),
            $microtime,
            $microtime,
        );

        Db::query("INSERT INTO " . Common::prefixTable('chat_history_admin') . " SET login = ?, idvisitor = ?, lastviewed = ? ON DUPLICATE KEY UPDATE lastviewed = ?", $arguments);

        return true;
    }

    public function setIsNew($microtime)
    {
        $getRegularUsers = Db::fetchAll("SELECT login FROM " . Common::prefixTable('access') . " WHERE idsite = ?", array($this->idsite));
        $getSuperUsers = Db::fetchAll("SELECT login FROM " . Common::prefixTable('user') . " WHERE superuser_access = 1");

        $getUsers = array_merge($getRegularUsers, $getSuperUsers);

        foreach ($getUsers as $user) {

            $arguments = array(
                $user['login'],
                @Common::hex2bin($this->idvisitor),
                $microtime,
                $microtime,
            );

            Db::query("INSERT INTO " . Common::prefixTable('chat_history_admin') . " SET login = ?, idvisitor = ?, lastsent = ? ON DUPLICATE KEY UPDATE lastsent = ?", $arguments);
        }

        return true;
    }

    public function getUnreadConversations($login)
    {
        $conversations = array();
        $rows = Db::fetchAll("SELECT idvisitor, lastviewed, lastsent FROM " . Common::prefixTable('chat_history_admin') . " WHERE login = ? AND lastsent > lastviewed", array($login));
        $rows = $this->formatRows($rows);

        foreach ($rows as $row) {
            $conversations[$row['idvisitor']] = $row;
        }

        return $conversations;
    }

    /**************************************************************************
     * Personnal Informations
     **************************************************************************/
    public function updatePersonnalInformations($name = false, $email = false, $phone = false, $comments = false)
    {
        if ($name == false && $email == false && $phone == false && $comments == false)
            return false;

        $buildQuery = "";

        $argSet = array();
        $argOnUpdate = array();

        $argSet[] = @Common::hex2bin($this->idvisitor);

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

    public function getPersonnalInformations()
    {
        $row = Db::fetchRow("SELECT * FROM " . Common::prefixTable('chat_personnal_informations') . " WHERE idvisitor = ?", array(@Common::hex2bin($this->idvisitor)));

        if (!$row) {
            $row = array(
                'name' => NULL,
                'email' => NULL,
                'phone' => NULL,
                'comments' => NULL
            );
        } else {
            $row = $this->formatRow($row);
        }

        return $row;
    }

    /**************************************************************************
     * Automatic Messages
     **************************************************************************/
    public function getAllAutomaticMessage()
    {
        $rows = Db::fetchAll("SELECT id, name, message,
		(SELECT name FROM " . Common::prefixTable('segment') . " WHERE idsegment = cae.segment) AS segment
		FROM " . Common::prefixTable('chat_automatic_message') . " AS cae WHERE idsite = ?", array($this->idsite));

        return $rows;
    }

    /**************************************************************************
     * Users
     **************************************************************************/
    public function setLastPoll($lastPoll, $login)
    {
        return Db::query("UPDATE " . Common::prefixTable('user') . " SET last_poll = ? WHERE login = ?", array($lastPoll, $login));
    }

    public function getLastPoll($login = false)
    {
        $query = ($login) ? "WHERE login = ?" : "ORDER BY last_poll DESC LIMIT 1";
        return Db::fetchOne("SELECT last_poll FROM " . Common::prefixTable('user') . " " . $query, ($login) ? array($login) : array());
    }

    public function isStaffOnline()
    {
        $timeout = 2; // in minutes
        $lastPoll = $this->getLastPoll();

        return !($lastPoll < (microtime(true) - ($timeout * 60)));
    }

    /**************************************************************************
     * Site
     **************************************************************************/
    public function getSiteMainUrl()
    {
        return Db::fetchOne("SELECT main_url FROM " . Common::prefixTable('site') . " WHERE idsite = ?", array($this->idsite));
    }

    /**************************************************************************
     * Private helpers
     **************************************************************************/
    private function formatRows($rows)
    {
        for ($i = 0, $len = count($rows); $i < $len; $i++) {
            $rows[$i] = $this->formatRow($rows[$i]);
        }

        return $rows;
    }

    private function formatRow($row)
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