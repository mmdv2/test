#!/bin/bash

# تابع نمایش منو
show_menu() {
    clear
    echo "========================================"
    echo "          SOCKS5 Proxy Setup            "
    echo "========================================"
    echo "Please select an option:"
    echo "1) Basic SOCKS5 Setup"
    echo "2) SOCKS5 with Nginx"
    echo "3) Uninstall Everything"
    echo "4) Exit Script"
    echo "========================================"
}

# تابع لاگ
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S %Z')] $1" >> /var/log/socks5_setup.log
    echo "$1"
}

# تابع حذف PHP (اگر نصب باشه)
remove_php() {
    log "Checking for installed PHP versions..."
    if dpkg -l | grep -q php; then
        log "Removing existing PHP versions..."
        sudo apt purge -y php* || { log "Error: Failed to purge PHP"; exit 1; }
        sudo apt autoremove -y || { log "Error: Failed to autoremove PHP"; exit 1; }
        log "PHP removed successfully."
    else
        log "No PHP versions found to remove."
    fi
}

# تابع نصب PHP 8.1
install_php81() {
    log "Adding PHP 8.1 repository..."
    sudo apt install -y software-properties-common || { log "Error: Failed to install software-properties-common"; exit 1; }
    sudo add-apt-repository ppa:ondrej/php -y || { log "Error: Failed to add PHP 8.1 repository"; exit 1; }
    sudo apt update || { log "Error: Update failed after adding repository"; exit 1; }
    log "Installing PHP 8.1..."
    sudo apt install -y php8.1-fpm php8.1-cli || { log "Error: PHP 8.1 installation failed"; exit 1; }
    sudo systemctl start php8.1-fpm || { log "Error: Starting PHP 8.1-FPM failed"; exit 1; }
    sudo systemctl enable php8.1-fpm || { log "Error: Enabling PHP 8.1-FPM failed"; exit 1; }
    log "PHP 8.1 installed and enabled successfully."
}

# تابع نصب پیش‌نیازهای پایه (فقط برای Dante)
install_basic_prerequisites() {
    log "Updating system..."
    sudo apt update && sudo apt upgrade -y || { log "Error: Update failed"; exit 1; }
    log "Installing Dante..."
    sudo apt install -y dante-server || { log "Error: Dante installation failed"; exit 1; }
}

# تابع نصب پیش‌نیازهای Nginx (برای گزینه 2)
install_nginx_prerequisites() {
    log "Updating system..."
    sudo apt update && sudo apt upgrade -y || { log "Error: Update failed"; exit 1; }
    # حذف PHP قبل از نصب
    remove_php
    # نصب PHP 8.1
    install_php81
    log "Installing Nginx and other dependencies..."
    sudo apt install -y nginx dante-server git unzip || { log "Error: Nginx installation failed"; exit 1; }
    # استارت و فعال‌سازی خودکار Nginx
    sudo systemctl start nginx || { log "Error: Starting Nginx failed"; exit 1; }
    sudo systemctl enable nginx || { log "Error: Enabling Nginx failed"; exit 1; }
    # ایجاد دایرکتوری‌ها با اطمینان
    [ -d /etc/nginx/sites-available ] || sudo mkdir -p /etc/nginx/sites-available
    [ -d /etc/nginx/sites-enabled ] || sudo mkdir -p /etc/nginx/sites-enabled
    # تنظیم دسترسی‌ها برای دایرکتوری‌ها
    sudo chown -R root:root /etc/nginx/sites-available
    sudo chown -R root:root /etc/nginx/sites-enabled
    sudo chmod -R 755 /etc/nginx/sites-available
    sudo chmod -R 755 /etc/nginx/sites-enabled
}

# تابع ایجاد فایل index.php
create_index_php() {
    log "Creating index.php..."
    sudo mkdir -p /var/www/html/proxy
    sudo bash -c "cat > /var/www/html/proxy/index.php <<'EOF'
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    \$port = \$_POST['port'];
    \$username = \$_POST['username'];
    \$password = \$_POST['password'];
    \$server_ip = \$_SERVER['SERVER_ADDR']; // IP سرور

    // تولید فایل پیکربندی Dante
    \$dante_conf = \"
logoutput: /var/log/danted.log
internal: 0.0.0.0 port = \$port
external: \$server_ip
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
\";

    // ذخیره فایل پیکربندی
    if (!file_put_contents('/etc/danted.conf', \$dante_conf)) {
        die('Error: Cannot write to /etc/danted.conf. Check permissions.');
    }

    // به‌روزرسانی نام کاربری و رمز عبور
    shell_exec(\"sudo useradd -M -s /sbin/nologin \$username\");
    shell_exec(\"echo '\$username:\$password' | sudo chpasswd\");

    // ری‌استارت سرویس Dante
    shell_exec(\"sudo systemctl restart danted\");

    // نمایش اطلاعات اتصال
    \$connection_info = \"SOCKS5 Proxy Details:\\nServer: \$server_ip\\nPort: \$port\\nUsername: \$username\\nPassword: \$password\";
}
?>

<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>SOCKS5 Proxy Manager</title>
    <link rel=\"stylesheet\" href=\"style.css\">
</head>
<body>
    <div class=\"container\">
        <h1>SOCKS5 Proxy Manager</h1>
        <form method=\"POST\">
            <div class=\"form-group\">
                <label for=\"port\">Port</label>
                <input type=\"number\" id=\"port\" name=\"port\" value=\"1080\" required>
            </div>
            <div class=\"form-group\">
                <label for=\"username\">Username</label>
                <input type=\"text\" id=\"username\" name=\"username\" value=\"proxyuser\" required>
            </div>
            <div class=\"form-group\">
                <label for=\"password\">Password</label>
                <input type=\"text\" id=\"password\" name=\"password\" value=\"proxypass\" required>
            </div>
            <button type=\"submit\" class=\"generate-btn\">Apply Settings</button>
        </form>
        <?php if (isset(\$connection_info)) { ?>
            <div class=\"links\">
                <h3>Connection Info</h3>
                <pre><?php echo \$connection_info; ?></pre>
            </div>
        <?php } ?>
    </div>
</body>
</html>
EOF"
    sudo chown www-data:www-data /var/www/html/proxy/index.php
    sudo chmod 644 /var/www/html/proxy/index.php
}

# تابع ایجاد فایل style.css
create_style_css() {
    log "Creating style.css..."
    sudo bash -c "cat > /var/www/html/proxy/style.css <<'EOF'
body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
    margin: 0;
    padding: 0;
}

.container {
    max-width: 600px;
    margin: 50px auto;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

h1 {
    text-align: center;
    color: #333;
}

.form-group {
    margin-bottom: 15px;
}

label {
    display: block;
    margin-bottom: 5px;
    color: #555;
}

input[type=\"text\"], input[type=\"number\"] {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}

button {
    padding: 10px 20px;
    background-color: #1da1f2;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

button:hover {
    background-color: #1991e2;
}

.generate-btn {
    display: block;
    width: 100%;
    margin-top: 10px;
}

.links {
    margin-top: 20px;
    padding: 10px;
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}
EOF"
    sudo chown www-data:www-data /var/www/html/proxy/style.css
    sudo chmod 644 /var/www/html/proxy/style.css
}

# تابع نصب پایه SOCKS5
install_basic_socks5() {
    install_basic_prerequisites
    log "Setting up basic SOCKS5 with Dante..."
    sudo systemctl enable danted || { log "Error: Enabling Dante failed"; exit 1; }
    log "Basic SOCKS5 setup complete!"
}

# تابع نصب SOCKS5 با Nginx
install_socks5_with_nginx() {
    install_nginx_prerequisites
    log "Setting up SOCKS5 with Nginx..."
    create_index_php
    create_style_css
    sudo chown -R www-data:www-data /var/www/html/proxy
    sudo chmod -R 755 /var/www/html/proxy

    # پرسیدن پورت از کاربر
    read -p "Enter the port for the web interface (default: 8066): " PORT
    PORT=${PORT:-8066}  # پیش‌فرض رو 8066 گذاشتم چون قبلاً استفاده کردید

    # پیکربندی Nginx
    log "Configuring Nginx..."
    # اطمینان از حذف فایل قدیمی
    sudo rm -f /etc/nginx/sites-available/proxy.conf
    sudo rm -f /etc/nginx/sites-enabled/proxy.conf
    # ایجاد فایل پیکربندی با پسوند .conf
    sudo bash -c "cat > /etc/nginx/sites-available/proxy.conf <<EOF
server {
    listen $PORT;
    server_name _;

    root /var/www/html/proxy;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # مسدود کردن درخواست‌های مخرب به مسیرهای پیش‌فرض
    location ~ ^/(sdk|odinhttpcall|evox|HNAP1|query|solr|cgi-bin|v2|\.env) {
        return 404;
    }
}
EOF"

    # فعال‌سازی سایت Nginx
    sudo ln -sf /etc/nginx/sites-available/proxy.conf /etc/nginx/sites-enabled/proxy.conf || { log "Error: Linking Nginx config failed"; exit 1; }
    sudo nginx -t || { log "Error: Nginx config test failed"; exit 1; }
    sudo systemctl restart nginx || { log "Error: Nginx restart failed"; exit 1; }

    # باز کردن پورت در فایروال
    sudo ufw allow $PORT/tcp || { log "Error: Opening port $PORT failed"; exit 1; }
    sudo ufw allow 1080/tcp || { log "Error: Opening port 1080 failed"; exit 1; }
    sudo ufw enable || { log "Error: Enabling firewall failed"; exit 1; }

    log "Setup complete! Access at http://<your_server_ip>:$PORT"
    echo "Setup complete! Access at http://<your_server_ip>:$PORT"
}

# تابع حذف نصب
uninstall_everything() {
    log "Uninstalling everything..."
    sudo rm -rf /var/www/html/proxy
    sudo rm -f /etc/nginx/sites-available/proxy.conf
    sudo rm -f /etc/nginx/sites-enabled/proxy.conf
    sudo systemctl stop nginx
    sudo systemctl disable nginx
    # حذف PHP
    remove_php
    sudo apt purge -y nginx dante-server
    sudo apt autoremove -y
    sudo ufw delete allow 8066/tcp
    sudo ufw delete allow 1080/tcp
    log "Uninstallation complete!"
    echo "Uninstallation complete!"
}

# حلقه اصلی منو
while true; do
    show_menu
    read -p "Enter your choice (1-4): " choice

    case $choice in
        1)
            install_basic_socks5
            read -p "Press Enter to continue..."
            ;;
        2)
            install_socks5_with_nginx
            read -p "Press Enter to continue..."
            ;;
        3)
            uninstall_everything
            read -p "Press Enter to continue..."
            ;;
        4)
            log "Exiting script..."
            echo "Exiting script..."
            exit 0
            ;;
        *)
            log "Invalid option, please try again..."
            echo "Invalid option, please try again..."
            read -p "Press Enter to continue..."
            ;;
    esac
done
