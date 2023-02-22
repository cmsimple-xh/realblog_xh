<?php

/**
 * Copyright 2006-2010 Jan Kanters
 * Copyright 2010-2014 Gert Ebersbach
 * Copyright 2014-2023 Christoph M. Becker
 *
 * This file is part of Realblog_XH.
 *
 * Realblog_XH is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Realblog_XH is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Realblog_XH.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Realblog;

use Realblog\Infra\DB;
use Realblog\Infra\Editor;
use Realblog\Infra\Finder;
use Realblog\Infra\ScriptEvaluator;
use Realblog\Infra\SystemChecker;
use Realblog\Infra\View;

class Dic
{
    public static function makeBlogController(): BlogController
    {
        global $pth, $plugin_cf, $plugin_tx;

        return new BlogController(
            $plugin_cf['realblog'],
            $plugin_tx['realblog'],
            self::makeDb(),
            new Finder(self::makeDb()),
            new View("{$pth['folder']['plugins']}realblog/views/", $plugin_tx['realblog']),
            new ScriptEvaluator
        );
    }

    public static function makeArchiveController(): ArchiveController
    {
        global $pth, $plugin_cf, $plugin_tx;

        return new ArchiveController(
            $plugin_cf['realblog'],
            $plugin_tx['realblog'],
            self::makeDb(),
            new Finder(self::makeDb()),
            new View("{$pth['folder']['plugins']}realblog/views/", $plugin_tx['realblog']),
            new ScriptEvaluator
        );
    }

    public static function makeLinkController(): LinkController
    {
        global $pth, $plugin_cf, $plugin_tx, $u;

        return new LinkController(
            $plugin_cf['realblog'],
            $plugin_tx['realblog'],
            $u,
            new Finder(Dic::makeDb()),
            new View("{$pth['folder']['plugins']}realblog/views/", $plugin_tx['realblog']),
            new ScriptEvaluator()
        );
    }

    public static function makeFeedLinkController(): FeedLinkController
    {
        global $plugin_tx;

        return new FeedLinkController(
            $plugin_tx['realblog']
        );
    }

    public static function makeMostPopularController(): MostPopularController
    {
        global $pth, $plugin_cf, $plugin_tx, $u;

        return new MostPopularController(
            $plugin_cf['realblog'],
            $u,
            new Finder(Dic::makeDb()),
            new View("{$pth['folder']['plugins']}realblog/views/", $plugin_tx['realblog'])
        );
    }

    public static function makeFeedController(): FeedController
    {
        global $pth, $plugin_cf, $plugin_tx;

        return new FeedController(
            $plugin_cf['realblog'],
            $plugin_tx['realblog'],
            new Finder(Dic::makeDb()),
            new ScriptEvaluator(),
            new View("{$pth['folder']['plugins']}realblog/views/", $plugin_tx['realblog'])
        );
    }

    public static function makeInfoController(): InfoController
    {
        global $pth, $plugin_cf, $plugin_tx;

        return new InfoController(
            $plugin_cf["realblog"],
            $plugin_tx["realblog"],
            new SystemChecker,
            new View("{$pth['folder']['plugins']}realblog/views/", $plugin_tx['realblog'])
        );
    }

    public static function makeMainAdminController(): MainAdminController
    {
        global $pth, $plugin_cf, $plugin_tx, $_XH_csrfProtection;

        return new MainAdminController(
            $plugin_cf['realblog'],
            $plugin_tx['realblog'],
            Dic::makeDb(),
            new Finder(Dic::makeDb()),
            $_XH_csrfProtection,
            new View("{$pth['folder']['plugins']}realblog/views/", $plugin_tx['realblog']),
            new Editor()
        );
    }

    public static function makeDataExchangeController(): DataExchangeController
    {
        global $pth, $plugin_tx, $_XH_csrfProtection;

        return new DataExchangeController(
            $plugin_tx['realblog'],
            Dic::makeDb(),
            new Finder(Dic::makeDb()),
            $_XH_csrfProtection,
            new View("{$pth['folder']['plugins']}realblog/views/", $plugin_tx['realblog'])
        );
    }

    public static function makeDb(): DB
    {
        global $pth;
        static $instance = null;

        if ($instance === null) {
            $instance = new DB($pth['folder']['content'] . "realblog/realblog.db");
        }
        return $instance;
    }
}
