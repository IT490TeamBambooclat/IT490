<?php
session_start();

// Redirect user back to login page if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit;
}

$username = htmlspecialchars($_SESSION['username']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - Welcome <?php echo $username; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #eef2f3;
            text-align: center;
            padding-top: 100px;
        }

        .container {
            background-color: white;
            width: 400px;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        h1 {
            color: #004080;
        }

        .logout-btn {
            display: inline-block;
            margin-top: 20px;
            background-color: #d9534f;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }

        .logout-btn:hover {
            background-color: #c9302c;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Welcome, <?php echo $username; ?>!</h1>
    <p>You are successfully signed in.</p>

    <form action="logout.php" method="POST">
        <button type="submit" class="logout-btn">Log Out</button>
    </form>
</div>

</body>
</html>

