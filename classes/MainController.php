<?php

/**
 * @copyright 2006-2010 Jan Kanters
 * @copyright 2010-2014 Gert Ebersbach <http://ge-webdesign.de/>
 * @copyright 2014-2017 Christoph M. Becker <http://3-magi.net/>
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html GNU GPLv3
 */

namespace Realblog;

use stdClass;

abstract class MainController extends AbstractController
{
    protected $showSearch;

    protected $searchTerm;

    protected $year;

    public function __construct($showSearch)
    {
        parent::__construct();
        $this->showSearch = $showSearch;
        $input = filter_input_array(
            INPUT_GET,
            array(
                'realblog_search' => FILTER_DEFAULT,
                'realblog_year' => array(
                    'filter' => FILTER_VALIDATE_INT,
                    'options' => array('default' => (int) date('Y'))
                )
            )
        );
        $this->searchTerm = $input['realblog_search'];
        $this->year = $input['realblog_year'];
    }

    protected function renderSearchForm()
    {
        global $su, $sn;

        $view = new View('search-form');
        $view->actionUrl = $sn;
        $view->pageUrl = $su;
        return $view->render();
    }

    protected function renderSearchResults($what, $count)
    {
        global $su;

        $view = new View('search-results');
        $view->words = $this->searchTerm;
        $view->count = $count;
        $view->url = Realblog::url($su);
        $view->key = ($what == 'archive') ? 'back_to_archive' : 'search_show_all';
        return $view->render();
    }

    protected function renderArticle($id)
    {
        global $sn, $su, $h, $s, $title, $description;

        $article = DB::findById($id);
        if (isset($article) && !XH_ADM && $article->status > 0) {
            DB::recordPageView($id);
        }
        if (isset($article) && ((defined('XH_ADM') && XH_ADM) || $article->status > 0)) {
            $title .= $h[$s] . " \xE2\x80\x93 " . $article->title;
            $description = $this->getDescription($article);
            $view = new View('article');
            $view->article = $article;
            $view->heading = $this->config['heading_level'];
            $view->isAdmin = defined('XH_ADM') && XH_ADM;
            $view->wantsComments = $this->wantsComments();
            if ($article->status === 2) {
                $params = array('realblog_year' => $this->year);
                $view->backText = $this->text['archiv_back'];
            } else {
                $params = array('realblog_page' => Realblog::getPage());
                $view->backText = $this->text['blog_back'];
            }
            $view->backUrl = Realblog::url($su, $params);
            if ($this->searchTerm) {
                $params['realblog_search'] = $this->searchTerm;
                $view->backToSearchUrl = Realblog::url($su, $params);
            }
            $view->editUrl = "$sn?&realblog&admin=plugin_main"
                . "&action=edit&realblog_id={$article->id}";
            if ($this->wantsComments()) {
                $bridge = "{$this->config['comments_plugin']}_RealblogBridge";
                $commentsUrl = call_user_func(array($bridge, 'getEditUrl'), 'realblog' . $article->id);
                if ($commentsUrl !== false) {
                    $view->editCommentsUrl = $commentsUrl;
                }
            }
            $view->date = date($this->text['date_format'], $article->date);
            if ($this->config['show_teaser']) {
                $story = '<div class="realblog_teaser">' . $article->teaser . '</div>' . $article->body;
            } else {
                $story = ($article->body != '') ? $article->body : $article->teaser;
            }
            $view->story = new HtmlString(evaluate_scripting($story));
            $view->renderComments = function ($article) {
                global $plugin_cf;

                if ($article->commentable) {
                    $commentId = 'comments' . $article->id;
                    $bridge = $plugin_cf['realblog']['comments_plugin'] . '_RealblogBridge';
                    return new HtmlString(call_user_func(array($bridge, 'handle'), $commentId));
                }
            };
            return $view->render();
        }
    }

    /**
     * @return string
     */
    private function getDescription(stdClass $article)
    {
        $teaser = trim(html_entity_decode(strip_tags($article->teaser), ENT_COMPAT, 'UTF-8'));
        if (utf8_strlen($teaser) <= 150) {
            return $teaser;
        } elseif (preg_match('/.{0,150}\b/su', $teaser, $matches)) {
            return $matches[0] . '…';
        } else {
            return utf8_substr($teaser, 0, 150) . '…';
        }
    }

    private function wantsComments()
    {
        return $this->config['comments_plugin']
            && class_exists($this->config['comments_plugin'] . '_RealblogBridge');
    }
}
