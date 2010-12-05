<?php
/******************************************************************************
 * Gaestebucheintraege auflisten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * start      - Angabe, ab welchem Datensatz Gaestebucheintraege angezeigt werden sollen
 * headline   - Ueberschrift, die ueber den Gaestebucheintraegen steht
 *              (Default) Gaestebuch
 * id         - Nur einen einzigen Gaestebucheintrag anzeigen lassen.
 * moderation : 1 - Moderationsmodus, Beitraege koennen freigegeben werden
 *              0 (Default) - Gaestebuchansicht
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_guestbook.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}
elseif($g_preferences['enable_guestbook_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Uebergabevariablen pruefen

if (array_key_exists('start', $_GET))
{
    if (is_numeric($_GET['start']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}
else
{
    $_GET['start'] = 0;
}

if (array_key_exists('headline', $_GET))
{
    $_GET['headline'] = strStripTags($_GET['headline']);
}
else
{
    $_GET['headline'] = $g_l10n->get('GBO_GUESTBOOK');
}

if (array_key_exists('id', $_GET))
{
    if (is_numeric($_GET['id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}
else
{
    $_GET['id'] = 0;
}

if (array_key_exists('moderation', $_GET))
{
    if (is_numeric($_GET['moderation']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    
    if($_GET['moderation'] == 1 && $g_current_user->editGuestbookRight() == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}
else
{
    $_GET['moderation'] = 0;
}

unset($_SESSION['guestbook_entry_request']);
unset($_SESSION['guestbook_comment_request']);

// Navigation faengt hier im Modul an, wenn keine Eintrag direkt aufgerufen wird
if($_GET['id'] == 0)
{
    $_SESSION['navigation']->clear();
}
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$g_layout['title']  = $_GET['headline'];
$g_layout['header'] = '';
if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] =  '<link type="application/rss+xml" rel="alternate" title="'.$g_current_organization->getValue('org_longname').' - '.$_GET['headline'].'"
        href="'.$g_root_path.'/adm_program/modules/guestbook/rss_guestbook.php" />';
};

$g_layout['header'] = $g_layout['header']. '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/moderate.js"></script>

    <script type="text/javascript">
        var gbookId = 0;

        function getComments(commentId)
        {
            gbookId = commentId;
            resObject.open("get", "'.$g_root_path.'/adm_program/modules/guestbook/get_comments.php?cid=" + gbookId, true);
            resObject.onreadystatechange = handleResponse;
            resObject.send(null);
        }

        function handleResponse()
        {
            if (resObject.readyState == 4)
            {
                var objectId = "commentSection_" + gbookId;
                document.getElementById(objectId).innerHTML = resObject.responseText;
                toggleComments(gbookId);
            }
        }

        function toggleComments(commentId)
        {
            if (document.getElementById("commentSection_" + commentId).innerHTML.length == 0)
            {
                getComments(commentId);
            }
            else
            {
                toggleDiv("commentsInvisible_" + commentId);
                toggleDiv("commentsVisible_" + commentId);
                toggleElement("commentSection_" + commentId, "");
            }
        }

        function toggleDiv(objectId)
        {
            if (document.getElementById(objectId).style.visibility == "hidden")
            {
                document.getElementById(objectId).style.visibility = "visible";
                document.getElementById(objectId).style.display    = "block";
            }
            else
            {
                document.getElementById(objectId).style.visibility = "hidden";
                document.getElementById(objectId).style.display    = "none";
            }
        }

    </script>';

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'. $_GET['headline']. '</h1>';

// Gucken wieviele Gaestebucheintraege insgesamt vorliegen...
// Das ist wichtig für die Seitengenerierung...
$sql    = 'SELECT COUNT(*) AS count FROM '. TBL_GUESTBOOK. '
           WHERE gbo_org_id = '. $g_current_organization->getValue('org_id');
$result = $g_db->query($sql);
$row = $g_db->fetch_array($result);
$num_guestbook = $row['count'];

// Anzahl Gaestebucheintraege pro Seite
if($g_preferences['guestbook_entries_per_page'] > 0)
{
    $guestbook_entries_per_page = $g_preferences['guestbook_entries_per_page'];
}
else
{
    $guestbook_entries_per_page = $num_guestbook;
}

// ------------------------------------------------------
// SQL-Statement zur Anzeige der Eintraege zusammensetzen
// ------------------------------------------------------
$conditions = '';

// falls eine id fuer einen bestimmten Gaestebucheintrag uebergeben worden ist...
if ($_GET['id'] > 0)
{
    $conditions .= ' AND gbo_id = '. $_GET['id']. ' ';
}
// pruefen ob das Modul Moderation aktiviert ist
if ($g_preferences['enable_guestbook_moderation'] == 1)
{
    if($_GET['moderation'] == 1)
    {
        $conditions .= ' AND gbo_locked = 1 ';
    }
    else
    {
        $conditions .= ' AND gbo_locked = 0 ';
    }
}

$sql    = 'SELECT * FROM '. TBL_GUESTBOOK. '
           WHERE gbo_org_id = '. $g_current_organization->getValue('org_id'). '
                 '.$conditions.'
           ORDER BY gbo_timestamp_create DESC
           LIMIT '. $_GET['start']. ', '. $guestbook_entries_per_page;
$guestbook_result = $g_db->query($sql);

// Icon-Links und Navigation anzeigen
echo '<ul class="iconTextLinkList">';

// Neuen Gaestebucheintrag anlegen
if ($_GET['id'] == 0 && $_GET['moderation'] == 0)
{
    echo '
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook_new.php?headline='. $_GET['headline']. '"><img
            src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('GBO_CREATE_ENTRY').'" /></a>
            <a href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook_new.php?headline='. $_GET['headline']. '">'.$g_l10n->get('GBO_CREATE_ENTRY').'</a>
        </span>
    </li>';
}

// Link zurueck zum Gaestebuch
if($_GET['id'] > 0 || $_GET['moderation'] == 1)
{
    echo '
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook.php?headline='. $_GET['headline']. '"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('GBO_BACK_TO_GUESTBOOK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook.php?headline='. $_GET['headline']. '">'.$g_l10n->get('GBO_BACK_TO_GUESTBOOK').'</a>
        </span>
    </li>';
}

// Link mit Anzahl der zu moderierenden Eintraege
if($_GET['moderation'] == 0 && $g_current_user->editGuestbookRight() && $g_preferences['enable_guestbook_moderation'] == 1)
{
    $sql = 'SELECT count(1) as count_locked FROM '. TBL_GUESTBOOK. '
             WHERE gbo_org_id = '. $g_current_organization->getValue('org_id'). '
               AND gbo_locked = 1';
    $g_db->query($sql);
    $row = $g_db->fetch_array();
    
    if($row['count_locked'] > 0)
    {
        echo '
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook.php?moderation=1&amp;headline='. $_GET['headline']. '"><img
                src="'. THEME_PATH. '/icons/star.png" alt="'.$g_l10n->get('GBO_PHR_MODERATE_ENTRIES', $row['count_locked']).'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook.php?moderation=1&amp;headline='. $_GET['headline']. '">'.$g_l10n->get('GBO_PHR_MODERATE_ENTRIES', $row['count_locked']).'</a>
            </span>
        </li>';    
    }
}

echo '</ul>';

if ($g_db->num_rows($guestbook_result) == 0)
{
    // Keine Gaestebucheintraege gefunden
    if ($_GET['id'] > 0)
    {
        echo '<p>'.$g_l10n->get('SYS_PHR_NO_ENTRY').'</p>';
    }
    else
    {
        echo '<p>'.$g_l10n->get('SYS_PHR_NO_ENTRIES').'</p>';
    }
}
else
{
    $guestbook = new TableGuestbook($g_db);

    // Gaestebucheintraege auflisten
    while ($row = $g_db->fetch_object($guestbook_result))
    {
        // GB-Objekt initialisieren und neuen DS uebergeben
        $guestbook->clear();
        $guestbook->setArray($row);

        echo '
        <div class="boxLayout" id="gbo_'.$guestbook->getValue('gbo_id').'">
            <div class="boxHead">
                <div class="boxHeadLeft">
                    <img src="'. THEME_PATH. '/icons/guestbook.png" alt="'.$guestbook->getValue('gbo_name').'" />'.$guestbook->getValue('gbo_name');

                    // Falls eine Homepage des Users angegeben wurde, soll der Link angezeigt werden...
                    if (strlen($guestbook->getValue('gbo_homepage')) > 0)
                    {
                        echo '
                        <a class="iconLink" href="'.$guestbook->getValue('gbo_homepage').'" target="_blank"><img src="'. THEME_PATH. '/icons/weblinks.png"
                            alt="'.$guestbook->getValue('gbo_homepage').'" title="'.$guestbook->getValue('gbo_homepage').'" /></a>';
                    }

                    // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
                    if (isValidEmailAddress($guestbook->getValue('gbo_email')))
                    {
                        echo '
                        <a class="iconLink" href="mailto:'.$guestbook->getValue('gbo_email').'"><img src="'. THEME_PATH. '/icons/email.png"
                            alt="'.$g_l10n->get('SYS_SEND_EMAIL_TO', $guestbook->getValue('gbo_email')).'" title="'.$g_l10n->get('SYS_SEND_EMAIL_TO', $guestbook->getValue('gbo_email')).'" /></a>';
                    }
                echo '</div>

                <div class="boxHeadRight">'. $guestbook->getValue('gbo_timestamp_create'). '&nbsp;';

                    // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                    if ($g_current_user->editGuestbookRight())
                    {
                            echo '
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook_new.php?id='.$guestbook->getValue('gbo_id').'&amp;headline='. $_GET['headline']. '"><img
                                src="'. THEME_PATH. '/icons/edit.png" alt="'.$g_l10n->get('SYS_EDIT').'" title="'.$g_l10n->get('SYS_EDIT').'" /></a>
                            <a class="iconLink" href="javascript:deleteObject(\'gbo\', \'gbo_'.$guestbook->getValue('gbo_id').'\','.$guestbook->getValue('gbo_id').',\''.$guestbook->getValue('gbo_name').'\')"><img
                                src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" title="'.$g_l10n->get('SYS_DELETE').'" /></a>';
                    }

                echo '</div>
            </div>

            <div class="boxBody">'.
                $guestbook->getText('HTML');
                
                if($_GET['moderation'] == 1)
                {
                    echo '
                    <ul class="iconTextLinkList">
                        <li>
                            <span class="iconTextLink">
                                <a href="javascript:moderateObject(\'gbo\', \'gbo_'.$guestbook->getValue('gbo_id').'\','.$guestbook->getValue('gbo_id').',\''.$guestbook->getValue('gbo_name').'\')"><img src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('SYS_UNLOCK').'" /></a>
                                <a href="javascript:moderateObject(\'gbo\', \'gbo_'.$guestbook->getValue('gbo_id').'\','.$guestbook->getValue('gbo_id').',\''.$guestbook->getValue('gbo_name').'\')">'.$g_l10n->get('SYS_UNLOCK').'</a>
                            </span>
                        </li>
                        <li>
                            <span class="iconTextLink">
                                <a href="javascript:deleteObject(\'gbo\', \'gbo_'.$guestbook->getValue('gbo_id').'\','.$guestbook->getValue('gbo_id').',\''.$guestbook->getValue('gbo_name').'\')"><img src="'. THEME_PATH. '/icons/no.png" alt="'.$g_l10n->get('SYS_REMOVE').'" /></a>
                                <a href="javascript:deleteObject(\'gbo\', \'gbo_'.$guestbook->getValue('gbo_id').'\','.$guestbook->getValue('gbo_id').',\''.$guestbook->getValue('gbo_name').'\')">'.$g_l10n->get('SYS_REMOVE').'</a>
                            </span>
                        </li>
                    </ul>';
                }

                // Falls der Eintrag editiert worden ist, wird dies angezeigt
                if($guestbook->getValue('gbo_usr_id_change') > 0)
                {
                    // Userdaten des Editors holen...
                    $user_change = new User($g_db, $guestbook->getValue('gbo_usr_id_change'));

                    echo '
                    <div class="editInformation">'.
                        $g_l10n->get('SYS_PHR_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $guestbook->getValue('gbo_timestamp_change')). '
                    </div>';
                }

                if($_GET['moderation'] == 0)
                {
                    // Alle Kommentare zu diesem Eintrag werden nun aus der DB geholt...
                    $sql    = 'SELECT * FROM '. TBL_GUESTBOOK_COMMENTS. '
                               WHERE gbc_gbo_id = "'.$guestbook->getValue('gbo_id').'"
                               ORDER by gbc_timestamp_create asc';
                    $comment_result = $g_db->query($sql);
    
    
                    // Falls Kommentare vorhanden sind und diese noch nicht geladen werden sollen...
                    if ($_GET['id'] == 0 && $g_db->num_rows($comment_result) > 0)
                    {
                        if($g_preferences['enable_intial_comments_loading'] == 1)
                        {
                            $visibility_show_comments = 'hidden';
                            $display_show_comments    = 'none';
                            $visibility_others        = 'visible';
                            $display_others           = 'block';
                        }
                        else
                        {
                            $visibility_show_comments = 'visible';
                            $display_show_comments    = 'block';
                            $visibility_others        = 'hidden';
                            $display_others           = 'none';
                        }
                        // Dieses div wird erst gemeinsam mit den Kommentaren ueber Javascript eingeblendet
                        echo '
                        <div id="commentsVisible_'. $guestbook->getValue('gbo_id'). '" class="commentLink" style="visibility: '. $visibility_others. '; display: '. $display_others. ';">
                            <span class="iconTextLink">
                                <a href="javascript:toggleComments('. $guestbook->getValue('gbo_id'). ')"><img src="'. THEME_PATH. '/icons/comments.png"
                                alt="'.$g_l10n->get('GBO_HIDE_COMMENTS').'" title="'.$g_l10n->get('GBO_HIDE_COMMENTS').'" /></a>
                                <a href="javascript:toggleComments('. $guestbook->getValue('gbo_id'). ')">'.$g_l10n->get('GBO_HIDE_COMMENTS').'</a>
                            </span>
                        </div>';
    
                        // Dieses div wird ausgeblendet wenn die Kommetare angezeigt werden
                        echo '
                        <div id="commentsInvisible_'. $guestbook->getValue('gbo_id'). '" class="commentLink" style="visibility: '. $visibility_show_comments. '; display: '. $display_show_comments. ';">
                            <span class="iconTextLink">
                                <a href="javascript:toggleComments('. $guestbook->getValue('gbo_id'). ')"><img src="'. THEME_PATH. '/icons/comments.png"
                                alt="'.$g_l10n->get('GBO_SHOW_COMMENTS').'" title="'.$g_l10n->get('GBO_SHOW_COMMENTS').'" /></a>
                                <a href="javascript:toggleComments('. $guestbook->getValue('gbo_id'). ')">'.$g_l10n->get('GBO_PHR_SHOW_COMMENTS', $g_db->num_rows($comment_result)).'</a>
                            </span>
                            <div id="comments_'. $guestbook->getValue('gbo_id'). '" style="visibility: '. $visibility_show_comments. '; display: '. $display_show_comments. ';"></div>
                        </div>';
    
                        // Hier ist das div, in das die Kommentare reingesetzt werden
                        echo '<div id="commentSection_'. $guestbook->getValue('gbo_id'). '" class="commentBox" style="display: '. $display_others. ';">';
                            if($g_preferences['enable_intial_comments_loading'] == 1)
                            {
                                include("get_comments.php");
                            }
                        echo '</div>';
                    }
    
                    if ($_GET['id'] == 0 && $g_db->num_rows($comment_result) == 0 && ($g_current_user->commentGuestbookRight() || $g_preferences['enable_gbook_comments4all'] == 1) )
                    {
                        // Falls keine Kommentare vorhanden sind, aber das Recht zur Kommentierung, wird der Link zur Kommentarseite angezeigt...
                        $load_url = $g_root_path.'/adm_program/modules/guestbook/guestbook_comment_new.php?id='.$guestbook->getValue('gbo_id');
                        echo '
                        <div class="editInformation">
                            <span class="iconTextLink">
                                <a href="'.$load_url.'"><img src="'. THEME_PATH. '/icons/comment_new.png"
                                alt="'.$g_l10n->get('GBO_PHR_WRITE_COMMENT').'" title="'.$g_l10n->get('GBO_PHR_WRITE_COMMENT').'" /></a>
                                <a href="'.$load_url.'">'.$g_l10n->get('GBO_PHR_WRITE_COMMENT').'</a>
                            </span>
                        </div>';
                    }
    
    
                    // Falls eine ID uebergeben wurde und der dazugehoerige Eintrag existiert,
                    // werden unter dem Eintrag die dazugehoerigen Kommentare (falls welche da sind) angezeigt.
                    if ($g_db->num_rows($guestbook_result) > 0 && $_GET['id'] > 0)
                    {
                        include('get_comments.php');
                    }
                }

            echo '</div>
        </div>';
    }  // Ende While-Schleife
}


// Navigation mit Vor- und Zurueck-Buttons
$base_url = $g_root_path.'/adm_program/modules/guestbook/guestbook.php?headline='. $_GET['headline'].'&amp;moderation='.$_GET['moderation'];
echo generatePagination($base_url, $num_guestbook, $guestbook_entries_per_page, $_GET['start'], TRUE);

require(THEME_SERVER_PATH. '/overall_footer.php');

?>