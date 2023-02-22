<?php

/**
 * Copyright 2023 Christoph M. Becker
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

use PHPUnit\Framework\TestCase;
use Realblog\Infra\Finder;
use Realblog\Infra\ScriptEvaluator;
use ApprovalTests\Approvals;
use Realblog\Infra\Request;

class FeedControllerTest extends TestCase
{
    public function testRendersFeedWithNoArticles(): void
    {
        global $su;

        $su = "";
        $conf = XH_includeVar("./config/config.php", "plugin_cf")["realblog"];
        $text = XH_includeVar("./languages/en.php", "plugin_tx")["realblog"];
        $finder = $this->createStub(Finder::class);
        $finder->method("findFeedableArticles")->willReturn([]);
        $scriptEvaluator = $this->createStub(ScriptEvaluator::class);
        $sut = new FeedController("./", "./userfiles/images/", $conf, $text, $finder, $scriptEvaluator);
        $response = $sut(new Request);
        Approvals::verifyHtml($response);
    }

    public function testRendersFeedWithFeedLogo(): void
    {
        global $su;

        $su = "";
        $conf = XH_includeVar("./config/config.php", "plugin_cf")["realblog"];
        $conf["rss_logo"] = "rss.png";
        $text = XH_includeVar("./languages/en.php", "plugin_tx")["realblog"];
        $finder = $this->createStub(Finder::class);
        $finder->method("findFeedableArticles")->willReturn([]);
        $scriptEvaluator = $this->createStub(ScriptEvaluator::class);
        $sut = new FeedController("./", "./userfiles/images/", $conf, $text, $finder, $scriptEvaluator);
        $response = $sut(new Request);
        Approvals::verifyHtml($response);
    }
}
