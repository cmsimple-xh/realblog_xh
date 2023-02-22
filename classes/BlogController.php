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

use Realblog\Infra\Pagination;
use Realblog\Infra\Request;
use Realblog\Infra\Url;
use Realblog\Value\Article;

class BlogController extends MainController
{
    public function __invoke(Request $request, bool $showSeach, string $category): string
    {
        if (isset($_GET["realblog_id"])) {
            return (string) $this->showArticleAction($request->url(), max((int) ($_GET["realblog_id"] ?? 1), 1));
        } else {
            return $this->defaultAction($request, $showSeach, $category);
        }
    }

    /**
     * @return string
     */
    private function defaultAction(Request $request, bool $showSearch, string $category)
    {
        $html = '';
        if ($showSearch) {
            $html .= $this->renderSearchForm($request->url());
        }
        $order = ($this->config['entries_order'] == 'desc')
            ? -1 : 1;
        $limit = max(1, (int) $this->config['entries_per_page']);
        $page = Plugin::getPage();
        $articleCount = $this->finder->countArticlesWithStatus(array(1), $category, $this->searchTerm);
        $pageCount = (int) ceil($articleCount / $limit);
        $page = min(max($page, 1), $pageCount);
        $articles = $this->finder->findArticles(
            1,
            $limit,
            ($page-1) * $limit,
            $order,
            $category,
            $this->searchTerm
        );
        if ($this->searchTerm) {
            $html .= $this->renderSearchResults($request->url(), 'blog', $articleCount);
        }
        $html .= $this->renderArticles($request->url(), $articles, $articleCount, $page, $pageCount);
        return $html;
    }

    /**
     * @param list<Article> $articles
     * @param int $articleCount
     * @param int $page
     * @param int $pageCount
     * @return string
     */
    private function renderArticles(Url $url, array $articles, $articleCount, $page, $pageCount)
    {
        $search = $this->searchTerm;
        $bridge = ucfirst($this->config["comments_plugin"]) . "\\RealblogBridge";
        $params = ["realblog_page" => (string) Plugin::getPage(), "realblog_search" => $search];
        $records = [];
        foreach ($articles as $article) {
            $isCommentable = $this->config["comments_plugin"] && class_exists($bridge) && $article->commentable;
            $records[] = [
                "title" => $article->title,
                "url" => $url->withParams(["realblog_id" => (string) $article->id] + $params)->relative(),
                "categories" => implode(", ", explode(",", trim($article->categories, ","))),
                "link_header" => $article->hasBody || (defined("XH_ADM") && XH_ADM),
                "date" => (string) date($this->text["date_format"], $article->date),
                "teaser" => $this->scriptEvaluator->evaluate($article->teaser),
                "read_more" => $this->config["show_read_more_link"]  && $article->hasBody,
                "commentable" => $isCommentable,
                "comment_count" => $isCommentable ? $bridge::count("realblog{$article->id}") : null,
            ];
        }
        $pagination = new Pagination(
            $articleCount,
            $page,
            $pageCount,
            (int) $this->config['pagination_radius'],
            $url->withParams(["realblog_search" => $search]),
            $this->view
        );
        return $this->view->render("articles", [
            "articles" => $records,
            "heading" => $this->config["heading_level"],
            "heading_above_meta" => $this->config["heading_above_meta"],
            "pagination" => $pagination->render(),
            "top_pagination" => (bool) $this->config["pagination_top"],
            "bottom_pagination" => (bool) $this->config["pagination_bottom"],
        ]);
    }

    /**
     * @param int $id
     * @return string
     */
    private function showArticleAction(Url $url, $id)
    {
        return $this->renderArticle($url, $id);
    }
}
