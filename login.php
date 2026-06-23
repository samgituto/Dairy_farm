<!DOCTYPE html>
<html>

<head>

<title>DFMS Login</title>

<link rel="stylesheet"
href="assets/css/style.css">

</head>

<body class="auth-body">

<div class="auth-container">

<div class="auth-card">

<h1>Dairy Farm System</h1>

<form
action="authenticate.php"
method="POST">

<input
type="email"
name="email"
placeholder="Email Address"
required>

<input
type="password"
name="password"
placeholder="Password"
required>

<button type="submit">

Login

</button>

</form>

<a href="register.php">

Create Account

</a>

</div>

</div>

</body>
</html>