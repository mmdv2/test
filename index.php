<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $port = $_POST['port'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $server_ip = $_SERVER['SERVER_ADDR']; // IP سرور

    // تولید فایل پیکربندی Dante
    $dante_conf = "
logoutput: /var/log/danted.log
internal: 0.0.0.0 port = $port
external: $server_ip
socksmethod: username
user.privileged: root
user.unprivileged: nobody

client pass {
    from: 0.0.0.0/0 to: 0.0.0.0/0
    socksmethod: username
}
socks pass {
    from: 0.0.0.0/0 to: 0.0.0.0/0
    command: bind connect udpassociate
    socksmethod: username
}
";

    // ذخیره فایل پیکربندی
    file_put_contents('/etc/danted.conf', $dante_conf);

    // به‌روزرسانی نام کاربری و رمز عبور
    shell_exec("sudo useradd -M -s /sbin/nologin $username");
    shell_exec("echo '$username:$password' | sudo chpasswd");

    // ری‌استارت سرویس Dante
    shell_exec("sudo systemctl restart danted");

    // نمایش اطلاعات اتصال
    $connection_info = "SOCKS5 Proxy Details:\nServer: $server_ip\nPort: $port\nUsername: $username\nPassword: $password";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOCKS5 Proxy Manager</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>SOCKS5 Proxy Manager</h1>
        <form method="POST">
            <div class="form-group">
                <label for="port">Port</label>
                <input type="number" id="port" name="port" value="1080" required>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="proxyuser" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="text" id="password" name="password" value="proxypass" required>
            </div>
            <button type="submit" class="generate-btn">Apply Settings</button>
        </form>
        <?php if (isset($connection_info)) { ?>
            <div class="links">
                <h3>Connection Info</h3>
                <pre><?php echo $connection_info; ?></pre>
            </div>
        <?php } ?>
    </div>
</body>
</html>
