<html>
<head>
    <title>Logged out</title>
    <?php
        session_start();
        session_unset();
        session_destroy();
        header('Location: index.php');
    ?>
</head>
<body>
    <h1>Logged out</h1>
</body>
</html>