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

use Realblog\Infra\Finder;
use Realblog\Infra\Request;
use Realblog\Infra\ScriptEvaluator;
use Realblog\Infra\View;

class FeedController
{
    /** @var array<string,string> */
    private $config;

    /** @var array<string,string> */
    private $text;

    /** @var Finder */
    private $finder;

    /** @var ScriptEvaluator */
    private $scriptEvaluator;

    /** @var View */
    private $view;

    /**
     * @param array<string,string> $config
     * @param array<string,string> $text
     */
    public function __construct(
        array $config,
        array $text,
        Finder $finder,
        ScriptEvaluator $scriptEvaluator,
        View $view
    ) {
        $this->config = $config;
        $this->text = $text;
        $this->finder = $finder;
        $this->scriptEvaluator = $scriptEvaluator;
        $this->view = $view;
    }

    public function __invoke(Request $request): string
    {
        $count = (int) $this->config['rss_entries'];
        $articles = $this->finder->findFeedableArticles($count);
        $records = [];
        foreach ($articles as $article) {
            $records[] = [
                "title" => $article->title,
                "url" => $request->url()->withPage($this->text["rss_page"])
                    ->withParams(['realblog_id' => (string) $article->id])->absolute(),
                "teaser" => $this->scriptEvaluator->evaluate($article->teaser),
                "date" => (string) date('r', $article->date),
            ];
        }
        $data = [
            'url' => CMSIMPLE_URL . '?' . $this->text['rss_page'],
            'managingEditor' => $this->config['rss_editor'],
            'hasLogo' => (bool) $this->config['rss_logo'],
            'imageUrl' => $request->url()->withPath($request->imageFolder() . $this->config['rss_logo'])->absolute(),
            'articles' => $records,
        ];
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $this->view->render('feed', $data);
    }
}
