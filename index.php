<html>
<head>
    <title>Birthday Bot Configuration</title>
    <?php
    require_once 'vars.php';
    session_start();
    if($requirelogin && !(isset($_SESSION["loggedIn"])  && $_SESSION["loggedIn"]) ) {
        header('Location: login.php');
    }

    function writeToLog($string, $log) {
        file_put_contents("./".$log.".log", date("d-m-Y_h:i:s")."-- ".$string."\r\n", FILE_APPEND);
    }

    function sql_query($DB, $sql, $varstr, ...$vars) {
        $stmt = $DB->prepare($sql);
        if($stmt === FALSE) {
            throw new Exception('SQL ERROR: '.$DB->error);
        }
        $stmt->bind_param($varstr, ...$vars);
        $stmt->execute();
        $stmt->close();
    }

    function check_required_fields($postdata, $fields) {
        foreach($fields as $field) {
            if(!isset($postdata[$field]) || empty($postdata[$field])) {
                throw new Exception('DataFormatError: Missing required field '.$field);
            }
        }
    }

    $DB = new mysqli("localhost", $DBUser, $DBPass, $Database);
    if($DB->connect_error) {
        die("Connection failed: ".$DB->connect_error);
    }

    if($_SERVER["REQUEST_METHOD"] === "POST") {
        switch($_POST["action"]) {
            case "delete":
                $stmt = $DB->prepare("SELECT name FROM ".$table." WHERE `id`=?");
                if($stmt === FALSE) {
                    throw new Exception($DB->error);
                }
                $stmt->bind_param("i", $_POST["id"]);
                $stmt->execute();
                $stmt->bind_result($name);
                $stmt->fetch();
                writeToLog("IP ".$_SERVER['REMOTE_ADDR']." deleted ".$name."'s birthday", "users");
                $stmt->close();
                $stmt = $DB->prepare("DELETE FROM ".$table." WHERE `id`=?");
                if($stmt === FALSE) {
                    throw new Exception($DB->error);
                }
                $stmt->bind_param("i", $_POST["id"]);
                $stmt->execute();
                $stmt->close();
                break;
            case "add":
                check_required_fields($_POST, array("name", "month", "day"));

                writeToLog("IP ".$_SERVER['REMOTE_ADDR']." added ".$_POST["name"]."'s birthday","users");

                $bday = htmlentities($_POST["month"])."-".htmlentities($_POST["day"]);
                if(empty($_POST['year']) && empty($_POST['slackid'])) {
                    sql_query($DB, "INSERT INTO ".$table." (`birthday`, `name`) VALUES ( ? , ? )", "ss", $bday, htmlentities($_POST["name"]));
                } elseif(empty($_POST['slackid'])) {
                    sql_query($DB, "INSERT INTO ".$table." (`birthday`, `name`, `year`) VALUES ( ? , ? , ? )", "ssi", $bday, htmlentities($_POST["name"]), htmlentities($_POST["year"]));
                } elseif(empty($_POST['year'])) {
                    sql_query($DB, "INSERT INTO ".$table." (`birthday`, `name`, `slackid`) VALUES ( ? , ?, ? )", "sss", $bday, htmlentities($_POST["name"]), htmlentities($_POST["slackid"]));
                } else {
                    sql_query($DB, "INSERT INTO ".$table." (`birthday`, `name`, `year`, `slackid`) VALUES ( ? , ? , ? , ? )", "ssis", $bday, htmlentities($_POST["name"]), htmlentities($_POST['year']), htmlentities($_POST['slackid']));
                }
                break;
            case "update":
                check_required_fields($_POST, array("name", "month", "day"));

                writeToLog("IP ".$_SERVER['REMOTE_ADDR']." updated ".$_POST["name"]."'s birthday","users");

                if(empty($_POST['id'])) {
                    throw new Exception("DataFormatError: Cannot update with empty id");
                }
                $bday = $_POST["month"]."-".$_POST["day"];
                if(empty($_POST['year']) && empty($_POST['slackid'])) {
                    sql_query($DB, "UPDATE ".$table."  SET `birthday`=?, `name`=?, `year`=NULL, `slackid`=NULL WHERE `id`=?", "ssi", $bday, $_POST["name"], $_POST['id']);
                } elseif(empty($_POST['slackid'])) {
                    sql_query($DB, "UPDATE ".$table." SET `birthday`=?, `name`=?, `year`=?, `slackid`=NULL WHERE `id`=?", "ssii", $bday, $_POST["name"], $_POST["year"], $_POST['id']);
                } elseif(empty($_POST['year'])) {
                    sql_query($DB, "UPDATE ".$table." SET `birthday`=?, `name`=?, `year`=NULL, `slackid`=? WHERE `id`=?", "sssi", $bday, $_POST["name"], $_POST["slackid"], $_POST['id']);
                } else {
                    sql_query($DB, "UPDATE ".$table." SET `birthday`=?, `name`=?, `year`=?, `slackid`=? WHERE `id`=?", "ssisi", $bday, $_POST["name"], $_POST['year'], $_POST['slackid'], $_POST['id']);
                }
                break;
            default:
                die("DataFormatError: Invalid action \"".$_POST['action']."\"");
                break;
        }
    }

    ?>

    <style>
    * {
        font-family: helvetica, arial, sans-serif;
    }
    table, th, td {
        border: 1px solid black;
        border-collapse: collapse;
    }
    h1 {
        text-align: center;
        font-family: helvetica, arial, sans-serif;
    }
    body {
        margin: 0;
        background-image: url("grey.png");
        background-attachment: fixed;
    }
    #maindiv {
        width: 60%;
        min-width: 500;
        margin: auto;
        background-color: rgba(255, 255, 255, 0.67);
        padding: 15px 70px;
        min-height: 100%;
        box-shadow: 0px 0px 10px 1px #06ceff;
        overflow-x: auto;
    }
    table {
        width: 100%;
    }
    form {
        margin: 0;
    }
    td {
        text-align: center;
    }
    #actions {
        width: 1em;
    }
    #selfform {
        display: none;
    }
    </style>
    <script src='jquery-3.4.1.min.js'></script>
    <script>

    var editing = 0;

    function get_date_chooser(selected) {
        const monthnames = [<?php
        for($i = 1; $i <= 12; $i++) {
            echo("'".DateTime::createFromFormat("!m", $i)->format("F")."'");
            if($i < 12) {
                echo(',');
            }
        }
        ?>];
        var chooserstr = '<select class="month-select">'
        for(var i = 1; i <= monthnames.length; i++) {
            var numstr;
            if(i < 10) {
                numstr = "0" + i;
            } else {
                numstr = i;
            }
            if(i+1 === selected) {
                chooserstr += '<option value="'+numstr+'" selected>'+monthnames[i-1]+'</option>';
            } else {
                chooserstr += '<option value="'+numstr+'">'+monthnames[i-1]+'</option>';
            }
        }
        chooserstr += '</select>';
        return chooserstr;
    }

    function add_form(form, name, value) {
        form.append('<input type="hidden" name="'+name+'" value="'+value+'">');
    }

    function submit_delete_form() {
        const form = $('#selfform');
        const element = $(this)
        const id = element.closest("tr").data('id');
        add_form(form, "action", "delete");
        add_form(form, "id", id);
        form.submit();
    }

    function setup_handlers() {
        $("input.day-input").off('keypress').on('keypress', function(e) {
            const chr = String.fromCharCode(e.which);
            const val = $(this).val();
            if(val.length == 0) {
                return !("0123".indexOf(chr) < 0);
            } else if (val.length == 1) {
                if(val == '3') {
                    return !("01".indexOf(chr) < 0);
                } else if (val == '0') {
                    return !("123456789".indexOf(chr) < 0);
                } else {
                    return !("0123456789".indexOf(chr) < 0);
                }
            } else {
                return false;
            }
        });

        $("input.year-input").off('keypress').on('keypress', function(e) {
            const chr = String.fromCharCode(e.which);
            const val = $(this).val();
            if(val.length >= 4) {
                return false;
            }
            return !("0123456789".indexOf(chr) < 0);
        });
    }

    function create_edit_row() {
        const element = $(this);
        const row = element.closest('tr');
        const id = row.data('id');

        const nametd = row.children('td.name-td')[0];
        const slackidtd = row.children('td.slackid-td')[0];
        const datetd = row.children('td.date-td')[0];
        const jqdatetd = $(datetd);

        nametd.innerHTML = '<input class="name-input" type="text" placeholder="Name" value="'+nametd.innerHTML+'">';
        slackidtd.innerHTML = '<input class="slackid-input" type="text" placeholder="Slack ID" value="'+slackidtd.innerHTML+'">';
        datetd.innerHTML = get_date_chooser(parseInt(jqdatetd.data('month')))
                            + '<input class="day-input" type="text" pattern="[0-3][0-9]" title="Two digit day" placeholder="DD" size="3"  value="'+jqdatetd.data('day')+'">'
                            + '<input class="year-input" type="text" pattern="[0-9]{4}" title="Four digit year" placeholder="YYYY" size="5" value="'+jqdatetd.data('year')+'">';

        element.off('click');
        element.on('click', submit_edit_row);
        element.attr("class", "submitbutton");
        element.html('Submit');

        $("button.editbutton").attr("disabled", true);

        setup_handlers();
    }

    function submit_edit_row() {
        const element = $(this);
        const row = element.closest('tr');
        const id = row.data('id');

        const form = $('#selfform');

        const datetd = row.children('td.date-td');

        const name = row.children('td.name-td').children('input.name-input').val();
        const slackid = row.children('td.slackid-td').children('input.slackid-input').val();
        const month = datetd.children('select.month-select').val();
        const day = datetd.children('input.day-input').val();
        const year = datetd.children('input.year-input').val();

        if(!name) {
            window.alert("Missing name field!");
            return;
        }

        if(!day) {
            window.alert("Missing day field!");
            return;
        }

        if(day.length != 2) {
            window.alert("Invalid day (correct format is DD)");
            return;
        }

        if(year && year.length != 4) {
            window.alert("Invalid year (correct format is YYYY)");
            return;
        }

        if(id === "add") {
            add_form(form, "action", "add");
        } else {
            add_form(form, "action", "update");
            add_form(form, "id", id);
        }
        add_form(form, "name", name);
        add_form(form, "slackid", slackid);
        add_form(form, "month", month);
        add_form(form, "day", day);
        add_form(form, "year", year);
        form.submit();
    }

    $(document).ready(function() {
        $("button.deletebutton").on('click', submit_delete_form);
        $("button.editbutton").on('click', create_edit_row);
        $("button.addbutton").on('click', submit_edit_row);
        setup_handlers();
    });

    </script>
</head>
<body>

<form id="selfform" action="<?php echo(htmlentities($_SERVER['PHP_SELF'])); ?>" method="POST">
</form>

<div id="maindiv">
    <h1>Birthday Bot</h1> 

    <table>
    <tr>
        <th>Name</th>
        <th>Slack ID</th>
        <th>Birthday</th>
        <th id="actions">Actions</th>
    </tr>
    <?php
    $result = $DB->query("SELECT * FROM ".$table." ORDER BY `birthday` ASC");
    if(!$result) {
        throw new Exception($DB->error);
    }
    if($result->num_rows === 0) {
        echo('<tr><td colspan="4">No birthdays!</td></tr>');
    } else {
        while($row = $result->fetch_assoc()) {
            $bday = DateTime::createFromFormat("!m-d", $row['birthday']);
            echo('<tr data-id="'.$row['id'].'">');
            echo('<td class="name-td">'.$row["name"].'</td>');
            if(isset($row["slackid"])) {
                echo('<td class="slackid-td">'.$row["slackid"].'</td>');
            } else {
                echo('<td class="slackid-td"></td>');
            }
            if(isset($row['year']) && !empty($row['year'])) {
                echo('<td class="date-td" data-month="'.$bday->format('m').'" data-day="'.$bday->format('d').'" data-year="'.$row['year'].'">'.$bday->format('F j').', '.$row['year'].'</td>');
            } else {
                echo('<td class="date-td" data-month="'.$bday->format('m').'" data-day="'.$bday->format('d').'" data-year="">'.$bday->format('F j').'</td>');
            }
            echo('<td class="action-td">
                    <button class="deletebutton">Delete</button>
                    <button class="editbutton">Edit</button>
                  </td>
                ');
        }
    }
    ?>
    <tr data-id="add">
        <td class="name-td"><input class="name-input" type="text" placeholder="Name"></td>
        <td class="slackid-td"><input class="slackid-input" type="text" placeholder="Slack ID"></td>
        <td class="date-td">
            <select class="month-select">
                <?php
                for($i = 1; $i <= 12; $i++) {
                    $dateobj = DateTime::createFromFormat("!m", $i);
                    echo('<option value="'.$dateobj->format('m').'">'.$dateobj->format('F').'</option>');
                }
                ?>
            </select>
            <input class="day-input" type="text" pattern="[0-3][0-9]" title="Two digit day" placeholder="DD" size="3">
            <input class="year-input" type="text" pattern="[0-9]{4}" title="Four digit year" placeholder="YYYY" size="5">
        </td>
        <td class="action-td"><button class="addbutton">Add</button></td>
    </tr>
    </table>

</div>
</body>
</html>
