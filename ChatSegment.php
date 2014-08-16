<?php

namespace Piwik\Plugins\Chat;

use Piwik\Common;
use Piwik\Db;

class ChatSegment {
    public static function getAll($idSite)
    {
        return Db::fetchAll("SELECT * FROM " . Common::prefixTable('segment') . " WHERE enable_only_idsite = 0 OR enable_only_idsite = ?", array($idSite));
    }

    public static function get($idSegment)
    {
        return Db::fetchRow("SELECT * FROM " . Common::prefixTable('segment') . " WHERE idsegment = ?", array($idSegment));
    }

    public static function delete($idSegment)
    {
        return Db::query("DELETE FROM " . Common::prefixTable('segment') . " WHERE id = ?", array($idSegment));
    }
}
