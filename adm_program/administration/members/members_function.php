<?php
/******************************************************************************
 * User-Funktionen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * mode: 1 - MsgBox, in der erklaert wird, welche Auswirkungen das Loeschen hat
 *       2 - User NUR aus der Gliedgemeinschaft entfernen
 *       3 - User aus der Datenbank loeschen
 *       4 - User E-Mail mit neuen Zugangsdaten schicken
 *       5 - Frage, ob Zugangsdaten geschickt werden soll
 *       6 - Frage, ob Mitglied geloescht werden soll
 * user_id - Id des Benutzers, der bearbeitet werden soll
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/email_class.php");

$err_code = "";
$err_text = "";

// nur berechtigte User duerfen Funktionen aufrufen
if(!$g_current_user->editUser())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 6)
{
    $g_message->show("invalid");
}

if(isset($_GET["user_id"]) && is_numeric($_GET["user_id"]) == false)
{
    $g_message->show("invalid");
}

// nun erst einmal allgemein pruefen, ob der User zur aktuellen Orga gehoert
if(isMember($_GET["user_id"]) == true)
{
    $this_orga = true;
}
else
{
    $this_orga = false;
}

// pruefen, ob der User noch in anderen Organisationen aktiv ist
$sql    = "SELECT rol_id
             FROM ". TBL_ROLES. ", ". TBL_MEMBERS. "
            WHERE rol_org_shortname <> '$g_organization'
              AND rol_valid          = 1
              AND mem_rol_id         = rol_id
              AND mem_valid          = 1
              AND mem_usr_id         = {0} ";
$sql    = prepareSQL($sql, array($_GET['user_id']));
$result = mysql_query($sql, $g_adm_con);
db_error($result);
$other_orga = mysql_num_rows($result);


if($_GET["mode"] == 1)
{
    echo "
    <!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->\n
    <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
    <html>
    <head>
        <title>$g_current_organization->longname - Messagebox</title>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

        <!--[if lt IE 7]>
        <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
        <![endif]-->";

        require("../../../adm_config/header.php");
    echo "</head>";

    require("../../../adm_config/body_top.php");
        echo "<div align=\"center\"><br /><br /><br />
            <div class=\"formHead\" style=\"width: 400px\">Mitglied l&ouml;schen</div>

            <div class=\"formBody\" style=\"width: 400px\">
                <p align=\"left\">
                    <img src=\"$g_root_path/adm_program/images/user.png\" style=\"vertical-align: bottom;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Ehemaliger\">
                    Du kannst den Benutzer zu einem <b>Ehemaligen</b> machen. Dies hat den Vorteil, dass die Daten
                    erhalten bleiben und du sp&auml;ter immer wieder sehen kannst, welchen Rollen diese Person
                    zugeordnet war.
                </p>
                <p align=\"left\">
                    <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"vertical-align: bottom;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Benutzer l&ouml;schen\">
                    Wenn du <b>L&ouml;schen</b> ausw&auml;hlst, wird der Datensatz entg&uuml;ltig aus der Datenbank
                    entfernt und es ist sp&auml;ter nicht mehr m&ouml;glich Daten dieser Person einzusehen.
                </p>
                <button name=\"back\" type=\"button\" value=\"back\"
                    onclick=\"history.back()\">
                    <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                    &nbsp;Zur&uuml;ck</button>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <button name=\"delete\" type=\"button\" value=\"delete\"
                    onclick=\"self.location.href='$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET['user_id']. "&mode=3'\">
                    <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Benutzer l&ouml;schen\">
                    &nbsp;L&ouml;schen</button>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <button name=\"former\" type=\"button\" value=\"former\"
                    onclick=\"self.location.href='$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET['user_id']. "&mode=2'\">
                    <img src=\"$g_root_path/adm_program/images/user.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Ehemaliger\">
                    &nbsp;Ehemaliger</button>
            </div>
        </div>";

        require("../../../adm_config/body_bottom.php");
    echo "</body></html>";
    exit();
}
elseif($_GET["mode"] == 2)
{
    // User NUR aus der aktuellen Organisation entfernen
    $user = new User($g_adm_con, $_GET['user_id']);

    // Es duerfen keine Webmaster entfernt werden
    if($g_current_user->isWebmaster() == false
    && $user->isWebmaster()           == true)
    {
        $g_message->show("norights");
    }

    // User muss zur aktuellen Orga dazugehoeren
    // kein Suizid ermoeglichen
    if($this_orga == false
    || $g_current_user->id == $_GET['user_id'])
    {
        $g_message->show("norights");
    }

    $sql = "SELECT mem_id
              FROM ". TBL_ROLES. ", ". TBL_MEMBERS. "
             WHERE rol_org_shortname = '$g_organization'
               AND rol_valid         = 1
               AND mem_rol_id        = rol_id
               AND mem_valid         = 1
               AND mem_usr_id        = {0}";
    $sql        = prepareSQL($sql, array($_GET['user_id']));
    $result_mgl = mysql_query($sql, $g_adm_con);
    db_error($result_mgl);

    while($row = mysql_fetch_object($result_mgl))
    {
        // alle Rollen der aktuellen Gliedgemeinschaft auf ungueltig setzen
        $sql    = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 0
                    WHERE mem_id = $row->mem_id ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
    }

    $err_code = "remove_member_ok";
    $err_text = utf8_encode($g_current_organization->longname);
}
elseif($_GET["mode"] == 3)
{
    // User aus der Datenbank loeschen
    $user = new User($g_adm_con, $_GET['user_id']);
    
    // Es duerfen keine Webmaster entfernt werden
    if($g_current_user->isWebmaster() == false
    && $user->isWebmaster()           == true)
    {
        $g_message->show("norights");
    }
    
    // User darf in keiner anderen Orga aktiv sein
    // kein Suizid ermoeglichen
    if($other_orga > 0
    || $g_current_user->id == $_GET['user_id'])
    {
        $g_message->show("norights");
    }

    // Paralell im Forum loeschen, wenn g_forum gesetzt ist
    if($g_forum_integriert)
    {
        $g_forum->userDelete($user->login_name);
        
        $err_code = "delete_forum_user";
    }
    else
    {
        $err_code = "delete";
    }
    
    // User aus der Admidio Datenbank loeschen
    $user->delete();
}
elseif($_GET["mode"] == 4)
{
    // nur Webmaster duerfen User neue Zugangsdaten zuschicken
    // nur ausfuehren, wenn E-Mails vom Server unterstuetzt werden
    // nur an Mitglieder der eigenen Organisation schicken
    if($g_current_user->isWebmaster() == false
    || $g_preferences['enable_system_mails'] != 1
    || $this_orga == false)
    {
        $g_message->show("norights");
    }

    $user = new User($g_adm_con);
    $user->GetUser($_GET['user_id']);

    if($g_preferences['enable_system_mails'] == 1)
    {
        // neues Passwort generieren
        $password = substr(md5(time()), 0, 8);
        $password_md5 = md5($password);

        // Passwort des Users updaten
        $sql    = "UPDATE ". TBL_USERS. " SET usr_password = '$password_md5'
                    WHERE usr_id = $user->id ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        // Mail an den User mit den Loginaten schicken
        $email = new Email();
        $email->setSender($g_preferences['email_administrator']);
        $email->addRecipient($user->email, "$user->first_name $user->last_name");
        $email->setSubject(utf8_decode("Logindaten für "). $g_current_organization->homepage);
        $email->setText(utf8_decode("Hallo "). $user->first_name. utf8_decode(",\n\ndu erhälst deine Logindaten für ").
            $g_current_organization->homepage. utf8_decode(".\n\nBenutzername: ").
            $user->login_name. utf8_decode("\nPasswort: $password\n\n".
            "Das Passwort wurde automatisch generiert.\n".
            "Du solltest es nach dem Login in deinem Profil ändern.\n\n" .
            "Viele Grüße\nDie Webmaster"));
        if($email->sendEmail() == true)
        {
            $err_code = "mail_send";
            $err_text = $user->email;
        }
        else
        {
            $err_code = "mail_not_send";
            $err_text = $user->email;
        }
    }
}
elseif($_GET["mode"] == 5)
{
    // Fragen, ob Zugangsdaten verschickt werden sollen
    $user = new User($g_adm_con);
    $user->GetUser($_GET['user_id']);
    $g_message->setForwardYesNo("$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET["user_id"]. "&mode=4");
    $g_message->show("send_new_login", utf8_encode("$user->first_name $user->last_name"));
}
elseif($_GET["mode"] == 6)
{
    if($this_orga == true && $other_orga == 0)
    {
        // User ist NUR Mitglied der aktuellen Orga -> dann fragen, ob Ehemaliger oder ganz loeschen
        header("Location: $g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET["user_id"]. "&mode=1");
        exit();
    }
    elseif($this_orga == true && $other_orga > 0)
    {
        // User ist AUCH noch in anderen Orgas Mitglied -> User kann nur aus dieser Orga entfernt werden
        $user = new User($g_adm_con);
        $user->GetUser($_GET['user_id']);
        $g_message->setForwardYesNo("$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET["user_id"]. "&mode=2");
        $g_message->addVariableContent(utf8_encode("$user->first_name $user->last_name"));
        $g_message->addVariableContent(utf8_encode($g_current_organization->longname));
        $g_message->show("remove_member", "", "Entfernen");
    }
    elseif($this_orga == false && $other_orga == 0)
    {
        // User ist in keiner Orga mehr Mitglied -> kann komplett geloescht werden
        $user = new User($g_adm_con);
        $user->GetUser($_GET['user_id']);
        $g_message->setForwardYesNo("$g_root_path/adm_program/administration/members/members_function.php?user_id=". $_GET["user_id"]. "&mode=3");
        $g_message->addVariableContent(utf8_encode("$user->first_name $user->last_name"));
        $g_message->addVariableContent(utf8_encode($g_current_organization->longname));
        $g_message->show("delete_user", "", "Löschen");
    }
}

$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($err_code, $err_text);
?>