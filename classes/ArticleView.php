<?php

/**
 * The article views.
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
 * The article views.
 *
 * @category CMSimple_XH
 * @package  Realblog
 * @author   Christoph M. Becker <cmbecker69@gmx.de>
 * @license  http://www.gnu.org/licenses/gpl-3.0.en.html GNU GPLv3
 * @link     http://3-magi.net/?CMSimple_XH/Realblog_XH
 */
class Realblog_ArticleView
{
    /**
     * The article ID.
     *
     * @var int
     */
    private $_id;

    /**
     * The article record.
     *
     * @var array
     */
    private $_article;

    /**
     * The article page. Most likely this is always 1.
     *
     * @var int
     */
    private $_page;

    /**
     * Initializes a new instance.
     *
     * @param int    $id      An article ID.
     * @param string $article An article record.
     * @param int    $page    An article page.
     *
     * @return void
     */
    public function __construct($id, $article, $page)
    {
        $this->_id = (int) $id;
        $this->_article = (array) $article;
        $this->_page = (int) $page;
    }

    /**
     * Renders the article.
     *
     * @return string (X)HTML.
     *
     * @global array The configuration of the plugins.
     */
    public function render()
    {
        global $plugin_cf;

        $html = '<div class="realblog_show_box">'
            . $this->_renderLinks() . $this->_renderHeading()
            . $this->_renderDate() . $this->_renderStory()
            . $this->_renderLinks() . '</div>';
        // output comments in RealBlog
        if ($this->_wantsComments() && $this->_article[REALBLOG_COMMENTS] == 'on') {
            $realblog_comments_id = 'comments' . $this->_id;
            $bridge = $plugin_cf['realblog']['comments_plugin'] . '_RealblogBridge';
            $html .= call_user_func(array($bridge, handle), $realblog_comments_id);
        }
        return $html;
    }

    /**
     * Renders the links.
     *
     * @return string (X)HTML.
     */
    private function _renderLinks()
    {
        $html = '<div class="realblog_buttons">'
            . $this->_renderOverviewLink();
        if (XH_ADM) {
            if ($this->_wantsComments()) {
                $html .= $this->_renderEditCommentsLink();
            }
            $html .= $this->_renderEditEntryLink();
        }
        $html .= '<div style="clear: both;"></div>'
            . '</div>';
        return $html;
    }

    /**
     * Renders the overview link.
     *
     * @return string (X)HTML.
     *
     * @global string The URL of the current page.
     * @global array  The localization of the plugins.
     */
    private function _renderOverviewLink()
    {
        global $su, $plugin_tx;

        if ($this->_article[REALBLOG_STATUS] == 2) {
            $url = Realblog_url(
                $su, null, array('realblog_year' => Realblog_getYear())
            );
            $text = $plugin_tx['realblog']['archiv_back'];
        } else {
            $url = Realblog_url(
                $su, null, array('realblog_page' => $this->_page)
            );
            $text = $plugin_tx['realblog']['blog_back'];
        }
        return '<span class="realblog_button">'
            . '<a href="' . XH_hsc($url) . '">' . $text . '</a></span>';
    }

    /**
     * Renders the edit entry link.
     *
     * @return string (X)HTML.
     *
     * @global string The script name.
     * @global array  The localization of the plugins.
     */
    private function _renderEditEntryLink()
    {
        global $sn, $plugin_tx;

        return '<span class="realblog_button">'
            . '<a href="' . $sn . '?&amp;realblog&amp;admin=plugin_main'
            . '&amp;action=modify_realblog&amp;realblogID='
            . $this->_id . '">'
            . $plugin_tx['realblog']['entry_edit'] . '</a></span>';
    }

    /**
     * Renders the edit comments link.
     *
     * @return string (X)HTML.
     *
     * @global string The script name.
     * @global array  The configuration of the plugins.
     * @global array  The localization of the plugins.
     */
    private function _renderEditCommentsLink()
    {
        global $sn, $plugin_cf, $plugin_tx;

        $bridge = $plugin_cf['realblog']['comments_plugin'] . '_RealblogBridge';
        $url = call_user_func(array($bridge, getEditUrl), 'realblog' . $this->_id);
        if ($url) {
            return '<span class="realblog_button"><a href="' . XH_hsc($url) . '">'
                . $plugin_tx['realblog']['comment_edit'] . '</a></span>';
        } else {
            return '';
        }
    }

    /**
     * Renders the article heading.
     *
     * @return string (X)HTML.
     *
     * @todo Heed $cf[menu][levels].
     */
    private function _renderHeading()
    {
        return '<h4>' . $this->_article[REALBLOG_TITLE] . '</h4>';
    }

    /**
     * Renders the article date.
     *
     * @return string (X)HTML.
     *
     * @global array The localization of the plugins.
     */
    private function _renderDate()
    {
        global $plugin_tx;

        $date = date(
            $plugin_tx['realblog']['date_format'],
            $this->_article[REALBLOG_DATE]
        );
        return '<div class="realblog_show_date">' . $date . '</div>';
    }

    /**
     * Renders the article story.
     *
     * @return string (X)HTML.
     */
    private function _renderStory()
    {
        $story = $this->_article[REALBLOG_STORY] != ''
            ? $this->_article[REALBLOG_STORY]
            : $this->_article[REALBLOG_HEADLINE];
        return '<div class="realblog_show_story_entry">'
            . evaluate_scripting($story)
            . '</div>';
    }

    /**
     * Returns whether comments are enabled.
     *
     * @return bool
     *
     * @global array The configuration of the plugins.
     */
    private function _wantsComments()
    {
        global $plugin_cf;

        $pcf = $plugin_cf['realblog'];
        return $pcf['comments_plugin']
            && class_exists($pcf['comments_plugin'] . '_RealblogBridge');
    }
}

?>
