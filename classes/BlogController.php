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
use Realblog\Infra\Finder;
use Realblog\Infra\Pages;
use Realblog\Infra\Request;
use Realblog\Infra\View;
use Realblog\Logic\Util;
use Realblog\Value\Article;
use Realblog\Value\FullArticle;
use Realblog\Value\Html;
use Realblog\Value\Response;
use Realblog\Value\Url;

class BlogController
{
    /** @var array<string,string> */
    private $conf;

    /** @var DB */
    private $db;

    /** @var Finder */
    private $finder;

    /** @var View */
    private $view;

    /** @var Pages */
    private $pages;

    /** @param array<string,string> $conf */
    public function __construct(
        array $conf,
        DB $db,
        Finder $finder,
        View $view,
        Pages $pages
    ) {
        $this->conf = $conf;
        $this->db = $db;
        $this->finder = $finder;
        $this->view = $view;
        $this->pages = $pages;
    }

    public function __invoke(Request $request, string $mode, bool $showSearch, string $category = ""): Response
    {
        assert(in_array($mode, ["blog", "archive"], true));
        $response = $this->dispatch($request, $mode, $showSearch, $category);
        if ($request->edit() && $request->url()->param("realblog_page") !== null) {
            $page = max($request->intFromget("realblog_page"), 1);
            $response = $response->withCookie("realblog_page", (string) $page);
        }
        return $response;
    }

    private function dispatch(Request $request, string $mode, bool $showSearch, string $category): Response
    {
        if ($request->url()->param("realblog_id") !== null) {
            return $this->oneArticle($request, max($request->intFromGet("realblog_id"), 1));
        }
        if ($mode === "blog") {
            return $this->allArticles($request, $showSearch, $category);
        }
        return $this->allArchivedArticles($request, $showSearch);
    }

    private function allArticles(Request $request, bool $showSearch, string $category): Response
    {
        $html = "";
        if ($showSearch) {
            $html .= $this->renderSearchForm($request->url());
        }
        $order = ($this->conf["entries_order"] == "desc") ? -1 : 1;
        $limit = max(1, (int) $this->conf["entries_per_page"]);
        $page = $request->realblogPage();
        $searchTerm = $request->stringFromGet("realblog_search");
        $articleCount = $this->finder->countArticlesWithStatus([Article::PUBLISHED], $category, $searchTerm);
        $pageCount = (int) ceil($articleCount / $limit);
        $page = min(max($page, 1), $pageCount);
        $articles = $this->finder->findArticles(1, $limit, ($page-1) * $limit, $order, $category, $searchTerm);
        if ($searchTerm) {
            $html .= $this->renderSearchResults($request, "blog", $articleCount);
        }
        $html .= $this->renderArticles($request, $articles, $articleCount, $page, $pageCount);
        return Response::create($html);
    }

    /** @param list<Article> $articles */
    private function renderArticles(
        Request $request,
        array $articles,
        int $articleCount,
        int $page,
        int $pageCount
    ): string {
        $searchTerm = $request->stringFromGet("realblog_search");
        $radius = (int) $this->conf["pagination_radius"];
        $url = $request->url()->with("realblog_search", $searchTerm);
        $pagination = $this->renderPagination($articleCount, $page, $pageCount, $radius, $url);
        $url = $request->url()->with("realblog_page", (string) $request->realblogPage())
            ->with("realblog_search", $searchTerm);
        return $this->view->render("articles", [
            "articles" => $this->articleRecords($request, $articles, $url),
            "heading" => $this->conf["heading_level"],
            "heading_above_meta" => $this->conf["heading_above_meta"],
            "pagination" => Html::of($pagination),
            "top_pagination" => (bool) $this->conf["pagination_top"],
            "bottom_pagination" => (bool) $this->conf["pagination_bottom"],
        ]);
    }

    /**
     * @param list<Article> $articles
     * @return list<array{title:string,url:string,categories:string,link_header:bool,date:string,teaser:html,read_more:bool,commentable:bool,comment_count:int}>
     */
    private function articleRecords(Request $request, array $articles, Url $url): array
    {
        $bridge = ucfirst($this->conf["comments_plugin"]) . "\\RealblogBridge";
        $records = [];
        foreach ($articles as $article) {
            $isCommentable = $this->conf["comments_plugin"] && class_exists($bridge) && $article->commentable;
            $records[] = [
                "title" => $article->title,
                "url" => $url->with("realblog_id", (string) $article->id)->relative(),
                "categories" => implode(", ", explode(",", trim($article->categories, ","))),
                "link_header" => $article->hasBody || $request->admin(),
                "date" => $this->view->date($article->date),
                "teaser" => Html::of($this->pages->evaluateScripting($article->teaser)),
                "read_more" => $this->conf["show_read_more_link"]  && $article->hasBody,
                "commentable" => $isCommentable,
                "comment_count" => $isCommentable ? $bridge::count("realblog{$article->id}") : null,
            ];
        }
        return $records;
    }

    /** @return string */
    private function renderPagination(int $itemCount, int $page, int $pageCount, int $radius, Url $url)
    {
        if ($pageCount <= 1) {
            return "";
        }
        return $this->view->render("pagination", [
            "itemCount" => $itemCount,
            "pages" => $this->pageRecords($page, $pageCount, $radius, $url),
        ]);
    }

    /**
     * @param int<2,max> $pageCount
     * @return list<array{num:int,url:?string}|null>
     */
    private function pageRecords(int $currentPage, int $pageCount, int $radius, Url $url): array
    {
        $pages = [];
        foreach (Util::gatherPages($currentPage, $pageCount, $radius) as $page) {
            if ($page !== null) {
                $pages[] = [
                    "num" => $page,
                    "url" => $page !== $currentPage ? $url->with("realblog_page", (string) $page)->relative() : null,
                ];
            } else {
                $pages[] = null;
            }
        }
        return $pages;
    }

    private function oneArticle(Request $request, int $id): Response
    {
        $article = $this->finder->findById($id);
        if (isset($article)) {
            if (!$request->admin() && $article->status > Article::UNPUBLISHED) {
                $this->db->recordPageView($id);
            }
            if ($request->admin() || $article->status > Article::UNPUBLISHED) {
                return $this->renderArticle($request, $article);
            }
        }
        return Response::create();
    }

    private function renderArticle(Request $request, FullArticle $article): Response
    {
        $teaser = trim(html_entity_decode(strip_tags($article->teaser), ENT_COMPAT, "UTF-8"));
        if ($article->status === Article::ARCHIVED) {
            $url = $request->url()->with("realblog_year", (string) $request->year());
        } else {
            $url = $request->url()->with("realblog_page", (string) $request->realblogPage());
        }
        $url = $url->without("realblog_id");
        $bridge = ucfirst($this->conf["comments_plugin"]) . "\\RealblogBridge";
        $backUrl = $url->without("realblog_search")->relative();
        $searchTerm = $request->stringFromGet("realblog_search");
        if ($searchTerm !== "") {
            $backToSearchUrl = $url->with("realblog_search", $searchTerm)->relative();
        }
        $editUrl = $request->url()->withPage("realblog")->with("admin", "plugin_main")
            ->with("action", "edit")->with("realblog_id", (string) $article->id)->relative();
        if ($this->conf["comments_plugin"] && class_exists($bridge)) {
            $commentsUrl = $bridge::getEditUrl("realblog{$article->id}");
        }
        if ($this->conf["show_teaser"]) {
            $story = "<div class=\"realblog_teaser\">" . $article->teaser . "</div>" . $article->body;
        } else {
            $story = ($article->body !== "") ? $article->body : $article->teaser;
        }
        return Response::create($this->view->render("article", [
            "title" => $article->title,
            "heading" => $this->conf["heading_level"],
            "heading_above_meta" => $this->conf["heading_above_meta"],
            "is_admin" => $request->admin(),
            "wants_comments" => $this->conf["comments_plugin"] && class_exists($bridge),
            "back_text" => $article->status === 2 ? "archiv_back" : "blog_back",
            "back_url" => $backUrl,
            "back_to_search_url" => $backToSearchUrl ?? null,
            "edit_url" => $editUrl,
            "edit_comments_url" => !empty($commentsUrl) ? $commentsUrl : null,
            "comment_count" => !empty($commentsUrl) ? $bridge::count("realblog{$article->id}") : null,
            "comments" => !empty($commentsUrl) ? Html::of($bridge::handle("realblog{$article->id}")) : null,
            "date" => $this->view->date($article->date),
            "categories" => implode(", ", explode(",", trim($article->categories, ","))),
            "story" => Html::of($this->pages->evaluateScripting($story)),
        ]))->withTitle($this->pages->headingOf($request->page()) . " – " . $article->title)
            ->withDescription(Util::shortenText($teaser));
    }

    private function allArchivedArticles(Request $request, bool $showSearch): Response
    {
        $html = "";
        if ($showSearch) {
            $html .= $this->renderSearchForm($request->url());
        }
        $searchTerm = $request->stringFromGet("realblog_search");
        if ($searchTerm) {
            $articles = $this->finder->findArchivedArticlesContaining($searchTerm);
            $html .= $this->renderSearchResults($request, "archive", count($articles));
        } else {
            $articles = array();
        }
        $html .= $this->renderArchive($request, $articles);
        return Response::create($html);
    }

    /** @param list<Article> $articles */
    private function renderArchive(Request $request, array $articles): string
    {
        if ($request->stringFromGet("realblog_search") === "") {
            $year = $request->year();
            $years = $this->finder->findArchiveYears();
            $key = array_search($year, $years);
            if ($key === false) {
                $key = count($years) - 1;
                $year = $years[$key];
            }
            $back = ($key > 0) ? $years[(int) $key - 1] : null;
            $next = ($key < count($years) - 1) ? $years[(int) $key + 1] : null;
            $articles = $this->finder->findArchivedArticlesInPeriod(
                (int) mktime(0, 0, 0, 1, 1, $year),
                (int) mktime(0, 0, 0, 1, 1, $year + 1)
            );
            return $this->renderArchivedArticles($request, $articles, false, $back, $next);
        }
        return $this->renderArchivedArticles($request, $articles, true, null, null);
    }

    /** @param list<Article> $articles */
    private function renderArchivedArticles(
        Request $request,
        array $articles,
        bool $isSearch,
        ?int $back,
        ?int $next
    ): string {
        $url = $request->url();
        return $this->view->render("archive", [
            "isSearch" => $isSearch,
            "articles" => $this->archivedArticleRecords($request, $articles),
            "heading" => $this->conf["heading_level"],
            "year" => $request->year(),
            "backUrl" => $back ? $url->with("realblog_year", (string) $back)->relative() : null,
            "nextUrl" => $next ? $url->with("realblog_year", (string) $next)->relative() : null,
        ]);
    }

    /**
     * @param list<Article> $articles
     * @return list<array{year:int,month:int,articles:list<array{title:string,date:string,url:string}>}>
     */
    private function archivedArticleRecords(Request $request, $articles): array
    {
        $records = [];
        foreach (Util::groupArticlesByMonth($articles) as $group) {
            $articleRecords = [];
            foreach ($group["articles"] as $article) {
                $url = $request->url()->with("realblog_id", (string) $article->id)
                    ->with("realblog_year", date("Y", $article->date))
                    ->with("realblog_search", $request->stringFromGet("realblog_search"));
                $articleRecords[] = [
                    "title" => $article->title,
                    "date" => $this->view->date($article->date),
                    "url" => $url->relative(),
                ];
            }
            $records[] = [
                "year" => $group["year"],
                "month" => $group["month"] - 1,
                "articles" => $articleRecords
            ];
        }
        return $records;
    }

    private function renderSearchForm(Url $url): string
    {
        return $this->view->render("search_form", [
            "actionUrl" => $url->withPage("")->relative(),
            "pageUrl" => $url->page(),
        ]);
    }

    private function renderSearchResults(Request $request, string $what, int $count): string
    {
        return $this->view->render("search_results", [
            "words" => $request->stringFromGet("realblog_search"),
            "count" => $count,
            "url" => $request->url()->without("realblog_search")->relative(),
            "key" => ($what == "archive") ? "back_to_archive" : "search_show_all",
        ]);
    }
}
