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

use Realblog\Infra\CsrfProtector;
use Realblog\Infra\DB;
use Realblog\Infra\Editor;
use Realblog\Infra\Finder;
use Realblog\Infra\Request;
use Realblog\Infra\Response;
use Realblog\Infra\Url;
use Realblog\Infra\View;
use Realblog\Value\Article;
use Realblog\Value\FullArticle;
use RuntimeException;

class MainAdminController
{
    private const STATES = ['readyforpublishing', 'published', 'archived'];

    /** @var array<string,string> */
    private $conf;

    /** @var DB */
    private $db;

    /** @var Finder */
    private $finder;

    /** @var CsrfProtector */
    private $csrfProtector;

    /** @var View */
    private $view;

    /** @var Editor */
    private $editor;

    /** @var Request */
    private $request;

    /** @var Response */
    private $response;

    /** @var int */
    private $page;

    /** @param array<string,string> $conf */
    public function __construct(
        array $conf,
        DB $db,
        Finder $finder,
        CsrfProtector $csrfProtector,
        View $view,
        Editor $editor
    ) {
        $this->conf = $conf;
        $this->db = $db;
        $this->finder = $finder;
        $this->csrfProtector = $csrfProtector;
        $this->view = $view;
        $this->editor = $editor;
    }

    public function __invoke(Request $request, string $action): Response
    {
        $this->request = $request;
        $this->response = new Response;
        if ($request->admin() && $request->edit() && $request->hasGet("realblog_page")) {
            $page = max($request->intFromGet("realblog_page"), 1);
            $this->response->addCookie('realblog_page', (string) $page);
        }
        $this->page = $this->request->realblogPage();
        switch ($action) {
            default:
                $this->defaultAction();
                break;
            case "create":
                $this->createAction();
                break;
            case "edit":
                $this->editAction();
                break;
            case "delete":
                $this->deleteAction();
                break;
            case "do_create":
                $this->doCreateAction();
                break;
            case "do_edit":
                $this->doEditAction();
                break;
            case "do_delete":
                $this->doDeleteAction();
                break;
            case "delete_selected":
                $this->deleteSelectedAction();
                break;
            case "change_status":
                $this->changeStatusAction();
                break;
            case "do_delete_selected":
                $this->doDeleteSelectedAction();
                break;
            case "do_change_status":
                $this->doChangeStatusAction();
                break;
        }
        return $this->response;
    }

    /** @return void */
    private function defaultAction()
    {
        $filters = $this->request->filtersFromGet();
        if ($filters !== null && $filters !== $this->request->filtersFromCookie()) {
            $this->response->addCookie("realblog_filter", (string) json_encode($filters));
        }
        $statuses = array_keys(array_filter($this->getFilterStatuses()));
        $total = $this->finder->countArticlesWithStatus($statuses);
        $limit = (int) $this->conf['admin_records_page'];
        $pageCount = (int) ceil($total / $limit);
        $page = max(min($this->page, $pageCount), 1);
        $offset = ($page - 1) * $limit;
        $articles = $this->finder->findArticlesWithStatus($statuses, $limit, $offset);
        $this->response->setOutput($this->renderArticles($articles, $pageCount));
    }

    /** @return list<bool> */
    private function getFilterStatuses(): array
    {
        return $this->request->filtersFromGet() ?? $this->request->filtersFromCookie() ?? [false, false, false];
    }

    /** @param list<Article> $articles */
    private function renderArticles(array $articles, int $pageCount): string
    {
        $filters = $this->getFilterStatuses();
        $page = min(max($this->page, 0), $pageCount);
        $records = [];
        foreach ($articles as $article) {
            $params = [
                "admin" => "plugin_main",
                "realblog_id" => (string) $article->id,
                 "realblog_page" => (string) $page
            ];
            $records[] = [
                "id" => $article->id,
                "date" => $this->view->date($article->date),
                "status" => $article->status,
                "categories" => $article->categories,
                "title" => $article->title,
                "feedable" => $article->feedable,
                "commentable" => $article->commentable,
                "delete_url" => $this->request->url()->withPage("realblog")
                    ->withParams(["action" => "delete"] + $params)->relative(),
                "edit_url" => $this->request->url()->withPage("realblog")
                    ->withParams(["action" => "edit"] + $params)->relative(),
            ];
        }
        $data = [
            'imageFolder' => $this->request->pluginsFolder() . "realblog/images/",
            'page' => $page,
            'prevPage' => max($page - 1, 1),
            'nextPage' => min($page + 1, $pageCount),
            'lastPage' => $pageCount,
            'articles' => $records,
            'actionUrl' => $this->request->url()->withPage("")->relative(),
            'states' => self::STATES,
            'filters' => $filters,
        ];
        return $this->view->render('articles-form', $data);
    }

    /** @return void */
    private function createAction()
    {
        $this->renderArticle('create');
    }

    /** @return void */
    private function editAction()
    {
        $this->renderArticle('edit');
    }

    /** @return void */
    private function deleteAction()
    {
        $this->renderArticle('delete');
    }

    /** @return void */
    private function renderArticle(string $action)
    {
        $this->editor->init(['realblog_headline_field', 'realblog_story_field']);
        if ($action === 'create') {
            $article = new FullArticle(
                0,
                0,
                $this->request->time(),
                2147483647,
                2147483647,
                0,
                '',
                '',
                '',
                '',
                false,
                false
            );
        } else {
            $id = max($this->request->intFromGet("realblog_id"), 1);
            $article = $this->finder->findById($id);
            if (!$article) {
                $this->response->setOutput($this->view->message("fail", "message_not_found"));
                return;
            }
        }
        $this->renderForm($article, $action);
    }

    /** @return void */
    private function renderForm(FullArticle $article, string $action)
    {
        switch ($action) {
            case 'create':
                $title = $this->view->text("tooltip_create");
                break;
            case 'edit':
                $title = $this->view->text("title_edit", $article->id);
                break;
            case 'delete':
                $title = $this->view->text("title_delete", $article->id);
                break;
            default:
                throw new RuntimeException("Unsupported action");
        }
        $bjs = '<script>REALBLOG.categories = '
            . json_encode($this->finder->findAllCategories()) . ';</script>' . "\n"
            . '<script src="' . $this->request->pluginsFolder()
            . 'realblog/realblog.js"></script>';
        $data = [
            'article' => $article,
            'title' => $title,
            'date' => (string) date('Y-m-d', $article->date),
            'publishing_date' => (string) date('Y-m-d', $article->publishingDate),
            'archiving_date' => (string) date('Y-m-d', $article->archivingDate),
            'actionUrl' => $this->request->url()->withPage("realblog")
                ->withParams(["admin" => "plugin_main"])->relative(),
            'action' => "do_{$action}",
            'csrfToken' => $this->csrfProtector->token(),
            'isAutoPublish' => $this->conf['auto_publish'],
            'isAutoArchive' => $this->conf['auto_archive'],
            'states' => self::STATES,
            'categories' => trim($article->categories, ','),
            'button' => "btn_{$action}",
        ];
        $this->response->setOutput($this->view->render('article-form', $data))
            ->setTitle($title)->setBjs($bjs);
    }

    /** @return void */
    private function doCreateAction()
    {
        $this->csrfProtector->check();
        $article = $this->request->articleFromPost();
        $res = $this->db->insertArticle($article);
        if ($res === 1) {
            $this->redirectToOverviewResponse($this->request->url());
            return;
        } else {
            $info = $this->view->message("fail", "story_added_error");
        }
        $output = $this->renderInfo($this->request->url(), "tooltip_create", $info);
        $this->response->setOutput($output)->setTitle($this->view->text("tooltip_create"));
    }

    /** @return void */
    private function doEditAction()
    {
        $this->csrfProtector->check();
        $article = $this->request->articleFromPost();
        $res = $this->db->updateArticle($article);
        if ($res === 1) {
            $this->redirectToOverviewResponse($this->request->url());
            return;
        } else {
            $info = $this->view->message("fail", "story_modified_error");
        }
        $output = $this->renderInfo($this->request->url(), "tooltip_edit", $info);
        $this->response->setOutput($output)->setTitle($this->view->text("tooltip_edit"));
    }

    /** @return void */
    private function doDeleteAction()
    {
        $this->csrfProtector->check();
        $article = $this->request->articleFromPost();
        $res = $this->db->deleteArticle($article);
        if ($res === 1) {
            $this->redirectToOverviewResponse($this->request->url());
            return;
        } else {
            $info = $this->view->message("fail", "story_deleted_error");
        }
        $output = $this->renderInfo($this->request->url(), "tooltip_delete", $info);
        $this->response->setOutput($output)->setTitle($this->view->text("tooltip_delete"));
    }

    /** @return void */
    private function deleteSelectedAction()
    {
        $this->response->setOutput($this->renderConfirmation('delete'));
    }

    /** @return void */
    private function changeStatusAction()
    {
        $this->response->setOutput($this->renderConfirmation('change-status'));
    }

    private function renderConfirmation(string $kind): string
    {
        $data = [
            'ids' => $this->request->realblogIdsFromGet(),
            'action' => $this->request->url()->withPage("realblog")
                ->withParams(["admin" => "plugin_main"])->relative(),
            'url' => $this->request->url()->withPage("realblog")
                ->withParams([
                    "admin" => "plugin_main",
                    "action" => "plugin_text",
                    "realblog_page" => (string) $this->page
                ])
                ->relative(),
            'csrfToken' => $this->csrfProtector->token(),
        ];
        if ($kind === 'change-status') {
            $data['states'] = self::STATES;
        }
        return $this->view->render("confirm-$kind", $data);
    }

    /** @return void */
    private function doDeleteSelectedAction()
    {
        $this->csrfProtector->check();
        $ids = $this->request->realblogIdsFromPost();
        $res = $this->db->deleteArticlesWithIds($ids);
        if ($res === count($ids)) {
            $this->redirectToOverviewResponse($this->request->url());
            return;
        } elseif ($res > 0) {
            $info = $this->view->message("warning", "deleteall_warning", $res, count($ids));
        } else {
            $info = $this->view->message("fail", "deleteall_error");
        }
        $output = $this->renderInfo($this->request->url(), "tooltip_delete_selected", $info);
        $this->response->setOutput($output)->setTitle($this->view->text("tooltip_delete_selected"));
    }

    /** @return void */
    private function doChangeStatusAction()
    {
        $this->csrfProtector->check();
        $ids = $this->request->realblogIdsFromPost();
        $status = $this->request->statusFromPost();
        $res = $this->db->updateStatusOfArticlesWithIds($ids, $status);
        if ($res === count($ids)) {
            $this->redirectToOverviewResponse($this->request->url());
            return;
        } elseif ($res > 0) {
            $info = $this->view->message("warning", "changestatus_warning", $res, count($ids));
        } else {
            $info = $this->view->message("fail", "changestatus_error");
        }
        $output = $this->renderInfo($this->request->url(), "tooltip_change_status", $info);
        $this->response->setOutput($output)->setTitle($this->view->text("tooltip_change_status"));
    }

    private function renderInfo(Url $url, string $title, string $message): string
    {
        $params = ["admin" => "plugin_main", "action" => "plugin_text", "realblog_page" => (string) $this->page];

        return $this->view->render("info_message", [
            "title" => $title,
            "message" => $message,
            "url" => $url->withPage("realblog")->withParams($params)->relative(),
        ]);
    }

    /** @return void */
    private function redirectToOverviewResponse(Url $url)
    {
        $params = ["admin" => "plugin_main", "action" => "plugin_text", "realblog_page" => (string) $this->page];
        $this->response->redirect($url->withPage("realblog")->withParams($params));
    }
}
