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

use Realblog\Infra\Request;
use Realblog\Infra\View;

class FeedLinkController
{
    /** @var View */
    private $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * @param string $target
     * @return string
     */
    public function __invoke(Request $request, $target)
    {
        return $this->view->render("feed_link", [
            "url" => $request->url()->withPage("")->withParams(["realblog_feed" => "rss"])->relative(),
            "target" => $target,
            "image" => $request->pluginsFolder() . "realblog/images/rss.png",
        ]);
    }
}
