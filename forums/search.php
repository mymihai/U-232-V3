<?php
/**********************************************************
New 2010 forums that don't suck for TB based sites....
search based on nothing lol did this one from a clean page.
it's possible some TB code snuck in, who knows lol

beta fri june 11th 2010 v0.1

thanks to Retro, pdq, google & php.net for suggestions and ideas :D
and thanks to fusion at stackoverflow.com  for the 
great search string highlighting code... well done!

Powered by Bunnies!!!
**********************************************************/
error_reporting(0);
if (!defined('BUNNY_FORUMS')) {
    $HTMLOUT = '';
    $HTMLOUT.= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
        <head>
        <meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
        <title>ERROR</title>
        </head><body>
        <h1 style="text-align:center;">ERROR</h1>
        <p style="text-align:center;">How did you get here? silly rabbit Trix are for kids!.</p>
        </body></html>';
    echo $HTMLOUT;
    exit();
}
$auther_error = $content = $count = $count2 = $edited_by = $row_count = $over_forum_id = $selected_forums = $auther_id = '';
//=== get all the search stuff
$search = (isset($_GET['search']) ? strip_tags(trim($_GET['search'])) : '');
$auther = (isset($_GET['auther']) ? trim(htmlsafechars($_GET['auther'])) : '');
$search_what = ((isset($_GET['search_what']) && $_GET['search_what'] === 'body') ? 'body' : ((isset($_GET['search_what']) && $_GET['search_what'] === 'title') ? 'title' : 'all'));
$search_when = (isset($_GET['search_when']) ? intval($_GET['search_when']) : 0);
$sort_by = ((isset($_GET['sort_by']) && $_GET['sort_by'] === 'date') ? 'date' : 'relevance');
$asc_desc = ((isset($_GET['asc_desc']) && $_GET['asc_desc'] === 'ASC') ? 'ASC' : 'DESC');
$show_as = ((isset($_GET['show_as']) && $_GET['show_as'] === 'posts') ? 'posts' : 'list');
//=== get links for pager... must think of a better way to do this
$pager_links = '';
$pager_links.= ($search ? '&amp;search='.$search : '');
$pager_links.= ($auther ? '&amp;auther='.$auther : '');
$pager_links.= ($search_what ? '&amp;search_what='.$search_what : '');
$pager_links.= ($search_when ? '&amp;search_when='.$search_when : '');
$pager_links.= ($sort_by ? '&amp;sort_by='.$sort_by : '');
$pager_links.= ($asc_desc ? '&amp;asc_desc='.$asc_desc : '');
$pager_links.= ($show_as ? '&amp;show_as='.$show_as : '');
if ($auther) {
    //=== get member info
    $res_member = sql_query('SELECT id FROM users WHERE username LIKE '.sqlesc($auther));
    $arr_member = mysqli_fetch_assoc($res_member);
    $auther_id = $arr_member['id'];
    //=== if no member found
    $auther_error = ($auther_id == 0 ? '<h1>Sorry no member found with that username.</h1>Please check the spelling.<br />' : '');
}
//=== if searched
if ($search) {
    $search_where = ($search_what === 'body' ? 'p.body' : ($search_what === 'title' ? 't.topic_name, p.post_title' : 'p.post_title, p.body, t.topic_name'));
    //=== get the forum id list to check if any were selected
    $res_forum_ids = sql_query('SELECT id FROM forums');
    while ($arr_forum_ids = mysqli_fetch_assoc($res_forum_ids)) {
        //$selected_forums[] = (isset($_GET["f$arr_forum_ids[id]"]) ? $arr_forum_ids['id'] : '');
        if (isset($_GET["f$arr_forum_ids[id]"])) {
            $selected_forums[] = $arr_forum_ids['id'];
        }
    }
    if (count($selected_forums) == 1) {
        $selected_forums_undone = implode(' ', $selected_forums);
        $selected_forums_undone = ' AND t.forum_id ='.$selected_forums_undone;
    } elseif (count($selected_forums) > 1) {
        $selected_forums_undone = implode(' OR t.forum_id=', $selected_forums);
        $selected_forums_undone = ' AND t.forum_id ='.$selected_forums_undone;
    }
    $AND = '';
    if ($auther_id) {
        $AND.= ' AND p.user_id = '.$auther_id;
    }
    if ($search_when) {
        $AND.= ' AND p.added >= '.(TIME_NOW - $search_when);
    }
    if ($selected_forums_undone) {
        $AND.= $selected_forums_undone;
    }
    //=== just do the minimum to get the count
    $res_count = sql_query('SELECT p.id, MATCH ('.$search_where.') AGAINST ('.sqlesc($search).' IN BOOLEAN MODE) AS relevance 
			FROM posts AS p LEFT JOIN topics AS t ON p.topic_id = t.id LEFT JOIN forums AS f ON t.forum_id = f.id 
			WHERE MATCH ('.$search_where.') AGAINST ('.sqlesc($search).'IN BOOLEAN MODE) 
			AND '.($CURUSER['class'] < UC_STAFF ? 'p.status = \'ok\' AND t.status = \'ok\' AND' : ($CURUSER['class'] < $min_delete_view_class ? 'p.status != \'deleted\' AND t.status != \'deleted\'  AND' : '')).' 
			f.min_class_read <= '.$CURUSER['class'].$AND.' HAVING relevance > 0.2');
    $count = mysqli_num_rows($res_count);
    //=== get stuff for the pager
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
    $perpage = isset($_GET['perpage']) ? (int)$_GET['perpage'] : 10;
    list($menu, $LIMIT) = pager_new($count, $perpage, $page, 'forums.php?action=search'.(isset($_GET['perpage']) ? '&amp;perpage='.$perpage : '').$pager_links);
    $order_by = ((isset($_GET['sort_by']) && $_GET['sort_by'] === 'date') ? 'p.added ' : 'relevance ');
    $ASC_DESC = ((isset($_GET['asc_desc']) && $_GET['asc_desc'] === 'ASC') ? ' ASC ' : ' DESC ');
    //=== main search... could split it up for list / post thing, but it's only a couple of things so it seems pointless...
    $res = sql_query('SELECT p.id AS post_id, p.body, p.post_title, p.added, p.icon, p.edited_by, p.edit_reason, p.edit_date, p.bbcode,
t.id AS topic_id, t.topic_name AS topic_title, t.topic_desc, t.post_count, t.views, t.locked, t.sticky, t.poll_id, t.num_ratings, t.rating_sum,
f.id AS forum_id, f.name AS forum_name, f.description AS forum_desc,
u.id, u.username, u.class, u.donor, u.suspended, u.warned, u.enabled, u.chatpost, u.leechwarn, u.pirate, u.king,  u.title, u.avatar, u.offensive_avatar,
MATCH (
'.$search_where.'
)
AGAINST (
'.sqlesc($search).'
IN BOOLEAN
MODE
) AS relevance
FROM posts AS p
LEFT JOIN topics AS t ON p.topic_id = t.id
LEFT JOIN forums AS f ON t.forum_id = f.id
LEFT JOIN users AS u ON p.user_id = u.id
WHERE MATCH (
'.$search_where.'
)
AGAINST (
'.sqlesc($search).'
IN BOOLEAN
MODE
)
AND '.($CURUSER['class'] < UC_STAFF ? 'p.status = \'ok\' AND t.status = \'ok\' AND' : ($CURUSER['class'] < $min_delete_view_class ? 'p.status != \'deleted\' AND t.status != \'deleted\'  AND' : '')).' f.min_class_read <= '.$CURUSER['class'].$AND.'
HAVING relevance > 0.2
ORDER BY '.$order_by.$ASC_DESC.$LIMIT);
    //=== top and bottom stuff
    $the_top_and_bottom = '<table border="0" cellspacing="0" cellpadding="0" width="90%">
		<tr><td class="three" align="center" valign="middle">'.(($count > $perpage) ? $menu : '').'</td>
		</tr></table>';
    $content = '';
    //=== nothing here? kill the page
    if ($count == 0) {
        $content.= '<br /><a name="results"></a><br /><table border="0" cellspacing="10" cellpadding="10" width="800px">
		<tr><td class="forum_head_dark"align="center">
		Nothing Found 
		</td></tr>
		<tr><td class="three"align="center">
		Please try again with a refined search string.<br /><br />
		</td></tr></table><br /><br />';
    } else {
        //=== if show as list:
        if ($show_as === 'list') {
            $content.= ($count > 0 ? '<div style="font-weight: bold;">Search Results '.$count.' </div>' : '').'<br />'.$the_top_and_bottom.'
	<a name="results"></a>
	<table border="0" cellspacing="10" cellpadding="10" width="90%" align="center">	
	<tr>
		<td align="center" valign="middle" class="forum_head_dark" width="10"><img src="pic/forums/topic.gif" alt="topic" title="topic" /></td>
		<td align="center" valign="middle" class="forum_head_dark" width="10"><img src="pic/forums/topic_normal.gif" alt="Thread Icon" title="Thread Icon" /></td>
		<td align="left" class="forum_head_dark">Topic / Post</td>
		<td align="left" class="forum_head_dark">in Forum</td>
		<td align="center" class="forum_head_dark">Relevance</td>
		<td class="forum_head_dark" align="center" width="10">Replies</td>
		<td class="forum_head_dark" align="center" width="10">Views</td>
		<td align="center" class="forum_head_dark" width="140">Date</td>
	</tr>';
            //=== lets do the loop
            while ($arr = mysqli_fetch_assoc($res)) {
                //=== change colors
                $count2 = (++$count2) % 2;
                $class = ($count2 == 0 ? 'one' : 'two');
                if ($search_what === 'all' || $search_what === 'title') {
                    $topic_title = highlightWords(htmlsafechars($arr['topic_title'], ENT_QUOTES) , $search);
                    $topic_desc = highlightWords(htmlsafechars($arr['topic_desc'], ENT_QUOTES) , $search);
                    $post_title = highlightWords(htmlsafechars($arr['post_title'], ENT_QUOTES) , $search);
                } else {
                    $topic_title = htmlsafechars($arr['topic_title'], ENT_QUOTES);
                    $topic_desc = htmlsafechars($arr['topic_desc'], ENT_QUOTES);
                    $post_title = htmlsafechars($arr['post_title'], ENT_QUOTES);
                }
                $body = format_comment($arr['body'], 1, 0);
                $search_post = str_replace(' ', '+', $search);
                $post_id = (int)$arr['post_id'];
                $posts = (int)$arr['post_count'];
                $post_text = tool_tip(' <img src="pic/forums/mg.gif" height="14" alt="Preview" title="Preview" />', $body, 'Post Preview');
                $rpic = ($arr['num_ratings'] != 0 ? ratingpic_forums(ROUND($arr['rating_sum'] / $arr['num_ratings'], 1)) : '');
                $content.= '<tr>
		<td class="'.$class.'" align="center"><img src="pic/forums/'.($posts < 30 ? ($arr['locked'] == 'yes' ? 'locked' : 'topic') : 'hot_topic').'.gif" alt="topic" title="topic" /></td>
		<td class="'.$class.'" align="center">'.($arr['icon'] == '' ? '<img src="pic/forums/topic_normal.gif" alt="Topic" title="Topic" />' : '<img src="pic/smilies/'.htmlsafechars($arr['icon']).'.gif" alt="'.htmlsafechars($arr['icon']).'" title="'.htmlsafechars($arr['icon']).'" />').'</td>
		<td align="left" valign="middle" class="'.$class.'">
		<table border="0" cellspacing="2" cellpadding="2">
		<tr>
		<td  class="'.$class.'" align="right"><span style="font-weight: bold;">Post: </span></td>
		<td  class="'.$class.'" align="left">
		<a class="altlink" href="forums.php?action=view_topic&amp;topic_id=15&amp;page=p'.(int)$arr['post_id'].'&amp;search='.$search_post.'#'.(int)$arr['post_id'].'" title="go to the post">'.($post_title == '' ? 'Link To Post' : $post_title).'</a></td>
		<td class="'.$class.'" align="right"></td>
		</tr>
		<tr>
		<td  class="'.$class.'" align="right"><span style="font-style: italic;">by: </span></td>
		<td  class="'.$class.'" align="left">'.print_user_stuff($arr).'</td>
		<td class="'.$class.'" align="right"></td>
		</tr>
		<tr>
		<td  class="'.$class.'" align="right"><span style="font-style: italic;">In topic: </span></td>
		<td  class="'.$class.'" align="left"> '.($arr['sticky'] == 'yes' ? '<img src="pic/forums/pinned.gif" alt="Pinned" title="Pinned" />' : '').($arr['poll_id'] > 0 ? '<img src="pic/forums/poll.gif" alt="Poll" title="Poll" />' : '').'
		<a class="altlink" href="forums.php?action=view_topic&amp;topic_id='.(int)$arr['topic_id'].'" title="go to topic">'.$topic_title.'</a>'.$post_text.'</td>
		<td class="'.$class.'" align="right"> '.$rpic.'</td>
		</tr>
		<tr>
		<td class="'.$class.'" align="right"></td>
		<td  class="'.$class.'" align="left"> '.($topic_desc != '' ? '&#9658; <span style="font-size: x-small;">'.$topic_desc.'</span>' : '').'</td>
		<td class="'.$class.'" align="right"></td>
		</tr>
		</table>
		</td>
		<td align="left" class="'.$class.'">
		<a class="altlink" href="forums.php?action=view_forum&amp;forum_id='.(int)$arr['forum_id'].'" title="go to forum">'.htmlsafechars($arr['forum_name'], ENT_QUOTES).'</a>
		'.($arr['forum_desc'] != '' ? '<br />&#9658; <span style="font-size: x-small;">'.htmlsafechars($arr['forum_desc'], ENT_QUOTES).'</span>' : '').'</td>
		<td align="center" class="'.$class.'">'.ROUND($arr['relevance'], 3).'</td>
		<td align="center" class="'.$class.'">'.number_format($posts - 1).'</td>
		<td align="center" class="'.$class.'">'.number_format($arr['views']).'</td>
		<td align="center" class="'.$class.'"><span style="white-space:nowrap;">'.get_date($arr['added'], '').'</span><br /></td>
		</tr>';
            }
            $content.= '</table>'.$the_top_and_bottom.'<br />
		<form action="#" method="get">
		<span style="font-weight: bold;">per page:</span> <select name="box" onchange="location = this.options[this.selectedIndex].value;">
		<option value=""> select </option>
		<option value="forums.php?action=search&amp;page=0&amp;perpage=5'.$pager_links.'"'.($perpage == 5 ? ' selected="selected"' : '').'>5</option>
		<option value="forums.php?action=search&amp;page=0&amp;perpage=20'.$pager_links.'"'.($perpage == 20 ? ' selected="selected"' : '').'>20</option>
		<option value="forums.php?action=search&amp;page=0&amp;perpage=50'.$pager_links.'"'.($perpage == 50 ? ' selected="selected"' : '').'>50</option>
		<option value="forums.php?action=search&amp;page=0&amp;perpage=100'.$pager_links.'"'.($perpage == 100 ? ' selected="selected"' : '').'>100</option>
		<option value="forums.php?action=search&amp;page=0&amp;perpage=200'.$pager_links.'"'.($perpage == 200 ? ' selected="selected"' : '').'>200</option>
		</select></form><br /><br />';
        }
    } //=== end if searched
    
} //=== end if show as list :D now lets show as posts \\o\o/o//
//=== if show as posts:
if ($show_as === 'posts') {
    //=== the top
    $content.= ($count > 0 ? '<div style="font-weight: bold;">Search Results '.$count.' </div>' : '').'<br />'.$the_top_and_bottom.'
		<a name="results"></a>
		<table border="0" cellspacing="10" cellpadding="10" width="90%" align="center">';
    //=== lets do the loop
    while ($arr = mysqli_fetch_assoc($res)) {
        //=== change colors
        $count2 = (++$count2) % 2;
        $class = ($count2 == 0 ? 'one' : 'two');
        $class_alt = ($count2 == 0 ? 'two' : 'one');
        $post_title = ($arr['post_title'] != '' ? ' <span style="font-weight: bold; font-size: x-small;">'.htmlsafechars($arr['post_title'], ENT_QUOTES).'</span>' : 'Link to Post');
        if ($search_what === 'all' || $search_what === 'title') {
            $topic_title = highlightWords(htmlsafechars($arr['topic_title'], ENT_QUOTES) , $search);
            $topic_desc = highlightWords(htmlsafechars($arr['topic_desc'], ENT_QUOTES) , $search);
            $post_title = highlightWords($post_title, $search);
        } else {
            $topic_title = htmlsafechars($arr['topic_title'], ENT_QUOTES);
            $topic_desc = htmlsafechars($arr['topic_desc'], ENT_QUOTES);
        }
        $post_id = (int)$arr['post_id'];
        $posts = (int)$arr['post_count'];
        $post_icon = ($arr['icon'] != '' ? '<img src="pic/smilies/'.htmlsafechars($arr['icon']).'.gif" alt="icon" title="icon" /> ' : '<img src="pic/forums/topic_normal.gif" alt="icon" alt="Normal Topic" /> ');
        $edited_by = '';
        if ($arr['edit_date'] > 0) {
            $res_edited = sql_query('SELECT username FROM users WHERE id='.sqlesc($arr['edited_by']));
            $arr_edited = mysqli_fetch_assoc($res_edited);
            $edited_by = '<br /><br /><br /><span style="font-weight: bold; font-size: x-small;">Last edited by <a class="altlink" href="member_details.php?id='.(int)$arr['edited_by'].'">'.htmlsafechars($arr_edited['username']).'</a>
				 at '.get_date($arr['edit_date'], '').' GMT '.($arr['edit_reason'] != '' ? ' </span>[ Reason: '.htmlsafechars($arr['edit_reason']).' ] <span style="font-weight: bold; font-size: x-small;">' : '');
        }
        $body = ($arr['bbcode'] == 'yes' ? highlightWords(format_comment($arr['body']) , $search) : highlightWords(format_comment_no_bbcode($arr['body']) , $search));
        $search_post = str_replace(' ', '+', $search);
        $content.= '<tr><td class="forum_head_dark" colspan="3" align="left">in: 
			<a class="altlink" href="forums.php?action=view_forum&amp;forum_id='.(int)$arr['forum_id'].'" title="Link to Forum">
			<span style="color: white;font-weight: bold;">'.htmlsafechars($arr['forum_name'], ENT_QUOTES).'</span></a> in: 
			<a class="altlink" href="forums.php?action=view_topic&amp;topic_id='.(int)$arr['topic_id'].'" title="Link to topic"><span style="color: white;font-weight: bold;">
			'.$topic_title.'</a></span></td></tr>
			<tr><td class="forum_head" align="left" width="100" valign="middle"><a name="'.$post_id.'"></a>
			<span style="font-weight: bold;">Relevance: '.ROUND($arr['relevance'], 3).'</span></td>
			<td class="forum_head" align="left" valign="middle">
			<span style="white-space:nowrap;">'.$post_icon.'
			<a class="altlink" href="forums.php?action=view_topic&amp;topic_id='.$arr['topic_id'].'&amp;page='.$page.'#'.(int)$arr['post_id'].'" title="Link to Post">'.$post_title.'</a>&nbsp;&nbsp;&nbsp;&nbsp; posted on: '.get_date($arr['added'], '').' ['.get_date($arr['added'], '', 0, 1).']</span></td>
			<td class="forum_head" align="right" valign="middle"><span style="white-space:nowrap;"> 
			 <a href="forums.php?action=view_my_posts&amp;page='.$page.'#top"><img src="pic/forums/up.gif" alt="top" title="Top"/></a> 
			 <a href="forums.php?action=view_my_posts&amp;page='.$page.'#bottom"><img src="pic/forums/down.gif" alt="bottom" title="Bottom" /></a> 
			</span></td>
			</tr>	
			
			<tr><td class="'.$class_alt.'" align="center" width="100px" valign="top">'.avatar_stuff($arr).'<br />
			'.print_user_stuff($arr).($arr['title'] == '' ? '' : '<br /><span style=" font-size: xx-small;">['.htmlsafechars($arr['title']).']</span>').'<br />
			<span style="font-weight: bold;">'.get_user_class_name($arr['class']).'</span><br />
			</td>
			<td class="'.$class.'" align="left" valign="top" colspan="2">'.$body.$edited_by.'</td></tr>
			
			<tr><td class="'.$class_alt.'" align="right" valign="middle" colspan="3"></td></tr>';
    } //=== end of while loop
    $content.= '</table>'.$the_top_and_bottom.'<br />
		<form action="#" method="get">
		<span style="font-weight: bold;">per page:</span> <select name=box ONCHANGE="location = this.options[this.selectedIndex].value;">
		<option value=""> select </option>
		<option value="forums.php?action=search&amp;page=0&amp;perpage=5'.$pager_links.'"'.($perpage == 5 ? ' selected="selected"' : '').'>5</option>
		<option value="forums.php?action=search&amp;page=0&amp;perpage=20'.$pager_links.'"'.($perpage == 20 ? ' selected="selected"' : '').'>20</option>
		<option value="forums.php?action=search&amp;page=0&amp;perpage=50'.$pager_links.'"'.($perpage == 50 ? ' selected="selected"' : '').'>50</option>
		<option value="forums.php?action=search&amp;page=0&amp;perpage=100'.$pager_links.'"'.($perpage == 100 ? ' selected="selected"' : '').'>100</option>
		<option value="forums.php?action=search&amp;page=0&amp;perpage=200'.$pager_links.'"'.($perpage == 200 ? ' selected="selected"' : '').'>200</option>
		</select></form><br /><br />';
}
//=== start page
$links = '<span style="text-align: center;"><a class="altlink" href="forums.php">Main Forums</a> |  '.$mini_menu.'<br /><br /></span>';
$search__help_boolean = '<div id="help"style="display:none"><h1>The boolean search supports the following operators:</h1>
<span style="font-weight: bold;">+</span> A leading plus sign indicates that this word must be present.<br /><br />
 <span style="font-weight: bold;">-</span> A leading minus sign indicates that this word must not be present.<br /><br />
By default (when neither + nor - is specified) the word is optional, but results that contain it are rated higher. <br /><br />
<span style="font-weight: bold;">*</span> The asterisk serves as the wildcard operator. Unlike the other operators, it should be appended to the word to be affected. Words match if they begin with the word preceding the * operator.<br /><br />
<span style="font-weight: bold;">> <</span> These two operators are used to change a word\'s contribution to the relevance value that is assigned to a word. The > operator increases the contribution and the < operator decreases it.<br /><br />
<span style="font-weight: bold;">~</span> A leading tilde acts as a negation operator, causing the word\'s contribution to the words\'s relevance to be negative. A row containing such a word is rated lower than others, but is not excluded altogether, as it would be with the - operator.<br /><br />
<span style="font-weight: bold;">" "</span> A phrase that is enclosed within double quotes return only results that contain the phrase literally, as it was typed. <br /><br />
<span style="font-weight: bold;">( )</span> Parentheses group words into subexpressions. Parenthesized groups can be nested.<br /><br /></div>';
$search_in_forums = '<table width="100%" align="center">';
$row_count = 0;
$res_forums = sql_query('SELECT o_f.name AS over_forum_name, o_f.id AS over_forum_id, 
f.id AS real_forum_id, f.name, f.description,  f.forum_id
FROM over_forums AS o_f JOIN forums AS f
WHERE o_f.min_class_view <= '.$CURUSER['class'].'
AND f.min_class_read <=  '.$CURUSER['class'].'
ORDER BY o_f.sort, f.sort ASC');
//=== well... let's do the loop and make the damned forum thingie!
while ($arr_forums = mysqli_fetch_assoc($res_forums)) {
    //=== if it's a forums section print it, if not, list the fourm sections in it \o/
    if ($arr_forums['over_forum_id'] != $over_forum_id && $row_count < 3) {
        while ($row_count < 3) {
            $search_in_forums.= '';
            ++$row_count;
        }
    }
    $search_in_forums.= ($arr_forums['over_forum_id'] != $over_forum_id ? '<tr>
			<td align="left" class="forum_head_dark" colspan="3"><span style="color: white;">'.htmlsafechars($arr_forums['over_forum_name'], ENT_QUOTES).'</span></td>
			</tr>' : '');
    if ($arr_forums['forum_id'] === $arr_forums['over_forum_id']) {
        $row_count = ($row_count == 3 ? 0 : $row_count);
        $search_in_forums.= ($row_count == 0 ? '' : '');
        ++$row_count;
        $search_in_forums.= '<tr><td class="one" align="left">
			<input name="f'.$arr_forums['real_forum_id'].'" type="checkbox" '.($selected_forums ? 'checked="checked"' : '').' value="1" />
			<a href="forums.php?action=view_forum&amp;forum_id='.$arr_forums['real_forum_id'].'" class="altlink" title="'.htmlsafechars($arr_forums['description'], ENT_QUOTES).'">'.htmlsafechars($arr_forums['name'], ENT_QUOTES).'</a></td></tr> '.($row_count == 3 ? '</td></tr>' : '');
    }
    $over_forum_id = $arr_forums['over_forum_id'];
}
for ($row_count = $row_count; $row_count < 3; $row_count++) {
    $search_in_forums.= '';
}
$search_in_forums.= '<tr>
								<td align="center" class="two" colspan="3"><span style="font-weight: bold;">If none are selected all are searched.</span></td>
								</tr></table>';
/*
		//=== well... let's do the loop and make the damned forum thingie!
		while ($arr_forums = mysqli_fetch_assoc($res_forums))
		{
	//=== if it's a forums section print it, if not, list the fourm sections in it \o/
			if ($arr_forums['over_forum_id'] != $over_forum_id && $row_count < 3)
			{
				while ($row_count < 3)
				{
				$search_in_forums .= '<td class="one"></td>';
				++$row_count;
				}
			}
			
			$search_in_forums .= ($arr_forums['over_forum_id'] != $over_forum_id ? 
			'<tr>
			<td align="left" class="forum_head_dark" colspan="3"><span style="color: white;">'.htmlsafechars($arr_forums['over_forum_name'], ENT_QUOTES).'</span></td>
			</tr>' : '');
			
		if ($arr_forums['forum_id'] == $arr_forums['over_forum_id'])
		{			
			$row_count = ($row_count == 3 ? 0 : $row_count);
			
			$search_in_forums .= ($row_count == 0 ? '<tr>' : '');
			++$row_count;
			
			$search_in_forums .= '<td class="one" align="left">
			<input name="f'.$arr_forums['real_forum_id'].'" type="checkbox" '.(in_array($arr_forums['real_forum_id'], $selected_forums) ? 'checked="checked"' : '').' value="1" />
			<a href="forums.php?action=view_forum&amp;forum_id='.$arr_forums['real_forum_id'].'" class="altlink" title="'.htmlsafechars($arr_forums['description'], ENT_QUOTES).'">'.htmlsafechars($arr_forums['name'], ENT_QUOTES).'</a> '.($row_count == 3 ? '</tr>' : '');
		}
		$over_forum_id = $arr_forums['over_forum_id'];
		}
				for ($row_count=$row_count; $row_count<3; $row_count++)
				{
				$search_in_forums .= '<td class="one"></td>';
				}
				
$search_in_forums .= '<tr>
								<td align="center" class="two" colspan="3"><span style="font-weight: bold;">If none are selected all are searched.</span></a></td>
								</tr></table>';						
*/
//print $selected_forums;
//exit();
//=== this is far to much code lol this should just be html... but it was fun to do \\0\0/0//
$search_when_drop_down = '<select name="search_when">
	<option class="body" value="0"'.($search_when === 0 ? ' selected="selected"' : '').'>No time frame</option>
	<option class="body" value="604800"'.($search_when === 604800 ? ' selected="selected"' : '').'>1 week ago</option>
	<option class="body" value="1209600"'.($search_when === 1209600 ? ' selected="selected"' : '').'>2 weeks ago</option>
	<option class="body" value="1814400"'.($search_when === 1814400 ? ' selected="selected"' : '').'>3 weeks ago</option>
	<option class="body" value="2419200"'.($search_when === 2419200 ? ' selected="selected"' : '').'>1 month ago</option>
	<option class="body" value="4838400"'.($search_when === 4838400 ? ' selected="selected"' : '').'>2 months ago</option>
	<option class="body" value="7257600"'.($search_when === 7257600 ? ' selected="selected"' : '').'>3 months ago</option>
	<option class="body" value="9676800"'.($search_when === 9676800 ? ' selected="selected"' : '').'>4 months ago</option>
	<option class="body" value="12096000"'.($search_when === 12096000 ? ' selected="selected"' : '').'>5 months ago</option>
	<option class="body" value="14515200"'.($search_when === 14515200 ? ' selected="selected"' : '').'>6 months ago</option>
	<option class="body" value="16934400"'.($search_when === 16934400 ? ' selected="selected"' : '').'>7 months ago</option>
	<option class="body" value="19353600"'.($search_when === 19353600 ? ' selected="selected"' : '').'>8 months ago</option>
	<option class="body" value="21772800"'.($search_when === 21772800 ? ' selected="selected"' : '').'>9 months ago</option>
	<option class="body" value="24192000"'.($search_when === 24192000 ? ' selected="selected"' : '').'>10 months ago</option>
	<option class="body" value="26611200"'.($search_when === 26611200 ? ' selected="selected"' : '').'>11 months ago</option>
	<option class="body" value="30800000"'.($search_when === 30800000 ? ' selected="selected"' : '').'>1 year ago</option>
	<option class="body" value="0">13.73 billion years ago</option>
	</select>';
$sort_by_drop_down = '<select name="sort_by">
	<option class="body" value="relevance"'.($sort_by === 'relevance' ? ' selected="selected"' : '').'>Relevance [default]</option>
	<option class="body" value="date"'.($sort_by === 'date' ? ' selected="selected"' : '').'>Post date</option>
	</select>';
$HTMLOUT.= '<h1>Search Forums</h1>'.$links.($count > 0 ? '<h1>'.$count.' Search Results Below</h1>' : ($search ? $content : '<br />')).'
		<form method="get" action="forums.php?">
		<input type="hidden" name="action" value="search" /> 	
	<table border="0" cellspacing="10" cellpadding="10" width="800px" align="center">
	<tr>
		<td class="forum_head_dark"align="center" colspan="2"><span style="color: white; font-weight: bold;">'.$INSTALLER09['site_name'].' Forums Search</span></td>
	</tr>
	<tr>
		<td class="three" align="right" width="30px" valign="middle">
		<span style="font-weight: bold;white-space:nowrap;">Search In:</span>
		</td>
		<td class="three" align="left" valign="middle">
		<input type="radio" name="search_what" value="title" '.($search_what === 'title' ? 'checked="checked"' : '').' /> <span style="font-weight: bold;">Title(s)</span> 
		<input type="radio" name="search_what" value="body" '.($search_what === 'body' ? 'checked="checked"' : '').' /> <span style="font-weight: bold;">Body text</span>  
		<input type="radio" name="search_what" value="all" '.($search_what === 'all' ? 'checked="checked"' : '').' /> <span style="font-weight: bold;">All</span> [ default ]</td>
	</tr>
	<tr>
		<td class="three" align="right" width="60px" valign="middle">
		<span style="font-weight: bold;white-space:nowrap;">Search Terms:</span></td>
		<td class="three" align="left">
		<input type="text" class="search" name="search" value="'.htmlsafechars($search).'" /> 	
		<span style="text-align: right;">
		<a class="altlink"  title="Open Boolean Search Help"  id="help_open" style="font-weight:bold;cursor:help;"><img src="pic/forums/more.gif" alt="+" title="+" width="18" /> Open Boolean Search Help</a> 
		<a class="altlink"  title="Close Boolean Search Help"  id="help_close" style="font-weight:bold;cursor:pointer;display:none"><img src="pic/forums/less.gif" alt="-" title="-" width="18" /> Close Boolean Search Help</a> <br />
		</span>'.$search__help_boolean.'</td>
	</tr>
	<tr>
		<td class="three" align="right" width="60px" valign="middle">
		<span style="font-weight: bold;white-space:nowrap;">By Member:</span>
		</td>
		<td class="three" align="left">'.$auther_error.'
		<input type="text" class="member" name="auther" value="'.$auther.'" /> 	Search only posts by this member</td>
	</tr>
	<tr>
		<td class="three" align="right" width="60px" valign="middle">
		<span style="font-weight: bold;white-space:nowrap;">Time Frame:</span>
		</td>
		<td class="three" align="left">'.$search_when_drop_down.' How far back to search.
		</td>
	</tr>
	<tr>
		<td class="three" align="right" width="60px" valign="middle">
		<span style="font-weight: bold;white-space:nowrap;">Sort By:</span> 
		</td>
		<td class="three" align="left">'.$sort_by_drop_down.'		
		<input type="radio" name="asc_desc" value="ASC" '.($asc_desc === 'ASC' ? 'checked="checked"' : '').' /> <span style="font-weight: bold;">Ascending</span>  
		<input type="radio" name="asc_desc" value="DESC" '.($asc_desc === 'DESC' ? 'checked="checked"' : '').' /> <span style="font-weight: bold;">Descending</span> 
		</td>
	</tr>
	<tr><td class="three" align="right" width="60px" valign="top">
		<span style="font-weight: bold;white-space:nowrap;">Search Forums:</span>
		</td>
		<td class="three" align="left">'.$search_in_forums.'		
		</td>
	</tr>
	<tr>
		<td class="three" align="center" width="30px" colspan="2">
		<input type="radio" name="show_as" value="list" '.($show_as === 'list' ? 'checked="checked"' : '').' /> <span style="font-weight: bold;">Results as list</span>  
		<input type="radio" name="show_as" value="posts" '.($show_as === 'posts' ? 'checked="checked"' : '').' /> <span style="font-weight: bold;">Results as posts</span>  
		<input type="submit" name="button" class="button" value="Search" onmouseover="this.className=\'button_hover\'" onmouseout="this.className=\'button\'" />
		</td>
	</tr>
	</table></form><br />'.$content.$links.'<br />';
$HTMLOUT.= '<script type="text/javascript" src="scripts/jquery.js"></script>
<script type="text/javascript">
<!--
		// set up the show / hide stuff
		$(document).ready(function()	{


	$("#help_open").click(function(){
	$("#help").slideToggle("slow", function() {
	});
	$("#help_open").hide();
	$("#help_close").show();
	});
	
	$("#help_close").click(function(){
	$("#help").slideToggle("slow", function() {
	});
	
	$("#help_close").hide();
	$("#help_open").show();
	});


});
-->
</script>';
?>
