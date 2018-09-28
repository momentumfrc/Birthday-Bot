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
                writeToLog("IP ".$_SERVER['REMOTE_ADDR']." added ".$_POST["name"]."'s birthday","users");
                $stmt = $DB->prepare("INSERT INTO ".$table." (`birthday`, `name`) VALUES ( ? , ? )");
                if($stmt === FALSE) {
                    throw new Exception($DB->error);
                }
                $bday = $_POST["month"]."-".$_POST["day"];
                $stmt->bind_param("ss", $bday, $_POST["name"]);
                $stmt->execute();
                $stmt->close();
                break;
            default:
                die("Invalid post data");
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
    </style>
</head>
<body>
<div id="maindiv">
    <h1>Birthday Bot</h1>

    <table>
    <tr>
        <th>Name</th>
        <th>Birthday</th>
        <th id="actions">Actions</th>
    </tr>
    <?php
    $result = $DB->query("SELECT * FROM ".$table." ORDER BY `birthday` ASC");
    if(!$result) {
        throw new Exception($DB->error);
    }
    if($result->num_rows === 0) {
        echo('<tr><td colspan="3">No birthdays!</td></tr>');
    } else {
        while($row = $result->fetch_assoc()) {
            $bday = DateTime::createFromFormat("!m-d", $row['birthday']);
            echo('<tr>');
            echo('<td>'.$row["name"].'</td>');
            echo('<td>'.$bday->format('F j').'</td>');
            echo('<td>
                    <form action="'.htmlentities($_SERVER["PHP_SELF"]).'" method="POST">
                    <input name="id" type="hidden" value="'.$row['id'].'">
                    <input name="action" type="hidden" value="delete">
                    <input type="submit" value="Delete">
                    </form>
                  </td>
                ');
        }
    }
    ?>
    <tr>
        <form action="<?php echo(htmlentities($_SERVER["PHP_SELF"])); ?>" method="POST">
        <td><input name="name" type="text" placeholder="Name"></td>
        <td>
            <select name="month">
                <?php
                for($i = 1; $i <= 12; $i++) {
                    $dateobj = DateTime::createFromFormat("!m", $i);
                    echo('<option value="'.$dateobj->format('m').'">'.$dateobj->format('F').'</option>');
                }
                ?>
            </select>
            <input name="day" type="text" pattern="[0-3][0-9]" title="Two digit day" placeholder="DD" size="3">
        </td>
        <td><input type="submit"></td>
        <input name="action" type="hidden" value="add">
        </form>
    </tr>
    </table>

</div>
</body>
</html>