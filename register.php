<!DOCTYPE html>
<html>

<head>

<title>DFMS Register</title>

<link rel="stylesheet"
href="assets/css/style.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>

<body class="auth-body">

<div class="auth-container">

<div class="auth-card">

<h1>Create Account</h1>

<form
action="save_user.php"
method="POST">

<input
type="text"
name="full_name"
placeholder="Full Name"
required>

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

<select name="role">

<option>Administrator</option>

<option>Farm Manager</option>

<option>Veterinarian</option>

<option>Farm Worker</option>

</select>

<button type="submit">

Register

</button>

</form>

<a href="login.php">

Already have an account?

</a>

</div>

</div>

</body>
</html>