<?php

/**
 * The articles views.
 *
 * PHP version 5
 *
 * @category  CMSimple_XH
 * @package   Realblog
 * @author    Jan Kanters <jan.kanters@telenet.be>
 * @author    Gert Ebersbach <mail@ge-webdesign.de>
 * @author    Christoph M. Becker <cmbecker69@gmx.de>
 * @copyright 2006-2010 Jan Kanters
 * @copyright 2010-2014 Gert Ebersbach <http://ge-webdesign.de/>
 * @copyright 2014 Christoph M. Becker <http://3-magi.net/>
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html GNU GPLv3
 * @version   SVN: $Id$
 * @link      http://3-magi.net/?CMSimple_XH/Realblog_XH
 */

/**
 * The articles views.
 *
 * @category CMSimple_XH
 * @package  Realblog
 * @author   Christoph M. Becker <cmbecker69@gmx.de>
 * @license  http://www.gnu.org/licenses/gpl-3.0.en.html GNU GPLv3
 * @link     http://3-magi.net/?CMSimple_XH/Realblog_XH
 */
class Realblog_ArticlesView
{
    /**
     * The articles.
     *
     * @var array
     */
    private $_articles;

    /**
     * The categories.
     *
     * @var string
     */
    private $_categories;

    /**
     * The number of articles per page.
     *
     * @var int
     */
    private $_articlesPerPage;

    /**
     * Initializes a new instance.
     *
     * @param array  $articles        An array of articles.
     * @param string $categories      FIXME
     * @param int    $articlesPerPage The number of articles per page.
     *
     * @return void
     */
    public function __construct($articles, $categories, $articlesPerPage)
    {
        $this->_articles = $articles;
        $this->_categories = (string) $categories;
        $this->_articlesPerPage = (int) $articlesPerPage;
    }

    /**
     * Renders the view.
     *
     * @return string (X)HTML.
     *
     * @global array  The configuration of the plugins.
     */
    public function render()
    {
        global $plugin_cf;

        $articleCount = count($this->_articles);
        $pageCount = (int) ceil($articleCount / $this->_articlesPerPage);
        $page = Realblog_getPage();
        if ($page > $pageCount) {
            $page = 1;
        }
        if ($page <= 1) {
            $start_index = 0;
            $page = 1;
        } else {
            $start_index = ($page - 1) * $this->_articlesPerPage;
        }
        $end_index = min($page * $this->_articlesPerPage - 1, $articleCount);

        if ($articleCount > 0 && $pageCount > 1) {
            if ($pageCount > $page) {
                $next = $page + 1;
                $back = ($page > 1) ? $next - 2 : "1";
            } else {
                $next = $pageCount;
                $back = $pageCount - 1;
            }
        }

        $t = "\n" . '<div class="realblog_show_box">' . "\n";
        $t .= $this->_renderPagination(
            'top', $page, $pageCount, @$back, @$next
        );
        $t .= "\n" . '<div style="clear:both;"></div>';
        $t .= $this->_renderArticlePreviews($start_index, $end_index);
        $t .= $this->_renderPagination(
            'bottom', $page, $pageCount, @$back, @$next
        );
        $t .= '<div style="clear: both"></div></div>';
        return $t;
    }

    /**
     * Renders the article previews.
     *
     * @param int $start The first article to render.
     * @param int $end   The last article to render.
     *
     * @return string (X)HTML.
     */
    private function _renderArticlePreviews($start, $end)
    {
        $articleCount = count($this->_articles);
        $t = '<div id="realblog_entries_preview" class="realblog_entries_preview">';
        for ($i = $start; $i <= $end; $i++) {
            if ($i > $articleCount - 1) {
                $t .= '';
            } else {
                $field = $this->_articles[$i];
                $t .= $this->_renderArticlePreview($field);
            }
        }
        $t .= '<div style="clear: both;"></div>' . '</div>';
        return $t;
    }

    /**
     * Renders an article preview.
     *
     * @param array $field An article record.
     *
     * @return string (X)HTML.
     *
     * @global array The configuration of the plugins.
     */
    private function _renderArticlePreview($field)
    {
        global $plugin_cf;

        $t = '';
        if (strstr($field[REALBLOG_HEADLINE], '|' . $this->_categories . '|')
            || strstr($field[REALBLOG_STORY], '|' . $this->_categories . '|')
            || $this->_categories == 'all'
            || (Realblog_getPgParameter('realblog_search')
            && strstr($field[REALBLOG_H], '|' . $this->_categories . '|'))
        ) {
            if ($plugin_cf['realblog']['teaser_multicolumns']) {
                $t .= '<div class="realblog_single_entry_preview">'
                    . '<div class="realblog_single_entry_preview_in">';
            }
            $t .= $this->_renderArticleHeading($field);
            $t .= $this->_renderArticleDate($field);
            $t .= "\n" . '<div class="realblog_show_story">' . "\n";
            $t .= evaluate_scripting($field[REALBLOG_HEADLINE]);
            if ($plugin_cf['realblog']['show_read_more_link']
                && $field[REALBLOG_STORY] != ''
            ) {
                $t .= $this->_renderArticleFooter($field);
            }
            $t .= '<div style="clear: both;"></div>' . "\n"
                . '</div>' . "\n";
            if ($plugin_cf['realblog']['teaser_multicolumns']) {
                $t .= '</div>' . "\n" . '</div>' . "\n";
            }
        }
        return $t;
    }

    /**
     * Renders an article heading.
     *
     * @param array $field An article record.
     *
     * @return string (X)HTML.
     *
     * @global string The URL of the current page.
     * @global array  The localization of the plugins.
     */
    private function _renderArticleHeading($field)
    {
        global $su, $plugin_tx;

        $t = '<h4>';
        $url = Realblog_url(
            $su, $field[REALBLOG_TITLE], array(
                'realblogID' => $field[REALBLOG_ID]
            )
        );
        if ($field[REALBLOG_STORY] != '' || XH_ADM) {
            $t .= '<a href="' . XH_hsc($url) . '" title="'
                . $plugin_tx['realblog']["tooltip_view"] . '">';
        }
        $t .= $field[REALBLOG_TITLE];
        if ($field[REALBLOG_STORY] != '' || XH_ADM) {
            $t .= '</a>';
        }
        $t .= '</h4>' . "\n";
        return $t;
    }

    /**
     * Renders an article date.
     *
     * @param array $field An article record.
     *
     * @return string (X)HTML.
     *
     * @global array The localization of the plugins.
     */
    private function _renderArticleDate($field)
    {
        global $plugin_tx;

        return '<div class="realblog_show_date">'
            . date($plugin_tx['realblog']['date_format'], $field[REALBLOG_DATE])
            . '</div>';
    }

    /**
     * Renders an article footer.
     *
     * @param array $field An article record.
     *
     * @return string (X)HTML.
     *
     * @global string The URL of the current page.
     * @global array  The configuration of the plugins.
     * @global array  The localization of the plugins.
     */
    private function _renderArticleFooter($field)
    {
        global $su, $plugin_cf, $plugin_tx;

        $t = '<div class="realblog_entry_footer">';

        $pcf = $plugin_cf['realblog'];
        if ($pcf['comments_plugin']
            && class_exists($pcf['comments_plugin'] . '_RealblogBridge')
            && $field[REALBLOG_COMMENTS]
        ) {
            $t .= $this->_renderCommentCount($field);
        }
        $url = Realblog_url(
            $su, $field[REALBLOG_TITLE], array(
                'realblogID' => $field[REALBLOG_ID]
            )
        );
        $t .= '<p class="realblog_read_more">'
            . '<a href="' . XH_hsc($url) . '" title="'
            . $plugin_tx['realblog']["tooltip_view"] . '">'
            . $plugin_tx['realblog']['read_more'] . '</a></p>'
            . '</div>';
        return $t;
    }

    /**
     * Renders a comment count.
     *
     * @param array $field An article record.
     *
     * @return string (X)HTML.
     *
     * @global array The configuration of the plugins.
     * @global array The localization of the plugins.
     */
    private function _renderCommentCount($field)
    {
        global $plugin_cf, $plugin_tx;

        $bridge = $plugin_cf['realblog']['comments_plugin'] . '_RealblogBridge';
        $commentsId = 'comments' . $field[REALBLOG_ID];
        $count = call_user_func(array($bridge, count), $commentsId);
        $key = 'message_comments' . XH_numberSuffix($count);
        return '<p class="realblog_number_of_comments">'
            . sprintf($plugin_tx['realblog'][$key], $count) . '</p>';
    }

    /**
     * Renders the pagination.
     *
     * @param string $place     A place to render ('top' or 'bottom').
     * @param string $page      A page number.
     * @param int    $pageCount A page count.
     * @param int    $back      The number of the previous page.
     * @param int    $next      The number of the next page.
     *
     * @return string (X)HTML.
     */
    private function _renderPagination($place, $page, $pageCount, $back, $next)
    {
        $articleCount = count($this->_articles);
        $t = '';
        if ($articleCount > 0 && $pageCount > 1) {
            $t .= $this->_renderPageLinks($pageCount);
        }
        if ($this->_wantsNumberOfArticles($place)) {
            $t .= $this->_renderNumberOfArticles();
        }
        if ($articleCount > 0 && $pageCount > 1) {
            $t .= $this->_renderPageOfPages(
                $page, $pageCount, $back, $next
            );
        }
        return $t;
    }

    /**
     * Whether the number of articles ought to be displayed.
     *
     * @param string $place A place ('top' or 'bottom').
     *
     * @return bool
     *
     * @global array The configuration of the plugins.
     */
    private function _wantsNumberOfArticles($place)
    {
        global $plugin_cf;

        return is_null(Realblog_getPgParameter('realblog_story'))
            && $plugin_cf['realblog']['show_numberof_entries_' . $place];
    }

    /**
     * Renders the page links.
     *
     * @param int $pageCount A page count.
     *
     * @return string (X)HTML.
     *
     * @global string The URL of the current page.
     * @global array  The localization of the plugins.
     */
    private function _renderPageLinks($pageCount)
    {
        global $su, $plugin_tx;

        $t = '<div class="realblog_table_paging">';
        for ($i = 1; $i <= $pageCount; $i++) {
            $separator = ($i < $pageCount) ? ' ' : '';
            $url = Realblog_url(
                $su, null, array('realblog_page' => $i)
            );
            $t .= '<a href="' . XH_hsc($url) . '" title="'
                . $plugin_tx['realblog']['page_label'] . ' ' . $i . '">['
                . $i . ']</a>' . $separator;
        }
        $t .= '</div>';
        return $t;
    }

    /**
     * Renders the page of pages.
     *
     * @param string $page      The number of the current page.
     * @param int    $pageCount A page count.
     * @param int    $back      The number of the previous page.
     * @param int    $next      The number of the next page.
     *
     * @return string (X)HTML.
     *
     * @global string The URL of the current page.
     * @global array  The localization of the plugins.
     */
    private function _renderPageOfPages($page, $pageCount, $back, $next)
    {
        global $su, $plugin_tx;

        $backUrl = Realblog_url(
            $su, null, array('realblog_page' => $back)
        );
        $nextUrl = Realblog_url(
            $su, null, array('realblog_page' => $next)
        );
        return '<div class="realblog_page_info">'
            . $plugin_tx['realblog']['page_label'] . ' : '
            . '<a href="' . XH_hsc($backUrl) . '" title="'
            . $plugin_tx['realblog']['tooltip_previous'] . '">'
            . '&#9664;</a>&nbsp;' . $page . '/' . $pageCount
            . '&nbsp;' . '<a href="' . XH_hsc($nextUrl) . '" title="'
            . $plugin_tx['realblog']['tooltip_next'] . '">'
            . '&#9654;</a></div>';
    }

    /**
     * Renders the number of articles.
     *
     * @return string (X)HTML.
     *
     * @global array The localization of the plugins.
     */
    private function _renderNumberOfArticles()
    {
        global $plugin_tx;

        return '<div class="realblog_db_info">'
            . $plugin_tx['realblog']['record_count'] . ' : '
            . count($this->_articles) . '</div>';
    }
}

?>
