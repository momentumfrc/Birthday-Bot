<html>
<head>
    <title>Login</title>
    <?php
    session_start();
    require_once 'vars.php';
    if(isset($_SESSION["loggedIn"]) && $_SESSION["loggedIn"]) {
        header('Location: index.php');
    }
    $loginfail = false;
    if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["password"])) {
        if($_POST["password"] === $usepassword) {
            $_SESSION["loggedIn"] = true;
            header('Location: index.php');
        } else {
            $loginfail = true;
        }
    }
    ?>
</head>
<body>
    <form method="POST" action="<?php echo(htmlentities($_SERVER["PHP_SELF"])); ?>">
        <input name="password" type="password" placeholder="Password">
        <input type="submit">
    </form>
    <?php
    if($loginfail) {
        echo('<p id="loginfail">Incorrect password</p>');
    }
    ?>
</body>
</html>