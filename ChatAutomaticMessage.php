<?php

namespace Piwik\Plugins\Chat;

use Piwik\Common;
use Piwik\Db;

class ChatAutomaticMessage {
    public static function getAll($idSite)
    {
        $rows = Db::fetchAll("SELECT id, name, message, segment AS segmentID, frequency, transmitter,
		(SELECT name FROM " . Common::prefixTable('segment') . " WHERE idsegment = cae.segment) AS segmentName
		FROM " . Common::prefixTable('chat_automatic_message') . " AS cae WHERE idsite = ?", array($idSite));

        return $rows;
    }

    public static function get($idAutoMsg)
    {
        return Db::fetchRow("SELECT * FROM " . Common::prefixTable('chat_automatic_message') . " WHERE id = ?", array($idAutoMsg));
    }

    public static function delete($idAutoMsg)
    {
        return Db::query("DELETE FROM " . Common::prefixTable('chat_automatic_message') . " WHERE id = ?", array($idAutoMsg));
    }

    public static function add($idSite, $name, $segment, $message, $frequency, $transmitter)
    {
        return Db::query("INSERT INTO " . Common::prefixTable('chat_automatic_message') . " SET idsite = ?, name = ?, segment = ?, message = ?, frequency = ?, transmitter = ?", array($idSite, $name, $segment, $message, $frequency, $transmitter));
    }

    public static function update($idAutoMsg, $idSite, $name, $segment, $message, $frequency, $transmitter)
    {
        return Db::query("UPDATE " . Common::prefixTable('chat_automatic_message') . " SET name = ?, segment = ?, message = ?, frequency = ?, transmitter = ? WHERE id = ? AND idsite = ?", array($name, $segment, $message, $frequency, $transmitter, $idAutoMsg, $idSite));
    }
}