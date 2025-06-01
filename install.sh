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
    echo "3) SOCKS5 with Cloudflare"
    echo "4) Exit Script"
    echo "========================================"
}

# تابع نصب پیش‌نیازها
install_prerequisites() {
    echo "Updating system..."
    sudo apt update && sudo apt upgrade -y
    echo "Installing prerequisites..."
    sudo apt install -y nginx php-fpm php-cli dante-server git unzip
}

# تابع نصب پایه SOCKS5
install_basic_socks5() {
    install_prerequisites
    echo "Setting up basic SOCKS5 with Dante..."
    sudo systemctl enable danted
    echo "Basic SOCKS5 setup complete!"
}

# تابع نصب SOCKS5 با Nginx
install_socks5_with_nginx() {
    install_prerequisites
    echo "Setting up SOCKS5 with Nginx..."
    sudo mkdir -p /var/www/html/proxy
    sudo cp index.php style.css /var/www/html/proxy/
    sudo chown -R www-data:www-data /var/www/html/proxy
    sudo chmod -R 755 /var/www/html/proxy
    echo "Nginx setup complete!"
}

# تابع نصب SOCKS5 با Cloudflare
install_socks5_with_cloudflare() {
    install_socks5_with_nginx
    echo "Configuring Cloudflare integration..."
    read -p "Enter your domain (e.g., proxy.example.com): " DOMAIN
    echo "Please add the following DNS record in Cloudflare:"
    echo "Type: A, Name: proxy, IPv4: <your_server_ip>, Proxy Status: Proxied"
    echo "After adding DNS, enable Full SSL in Cloudflare SSL/TLS settings."
    echo "Cloudflare setup instructions provided. Manual configuration required."
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
            read -p "Enter the port for the web interface (default: 8080): " PORT
            PORT=${PORT:-8080}
            sudo bash -c "cat > /etc/nginx/sites-available/proxy <<EOF
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
}
EOF"
            sudo ln -sf /etc/nginx/sites-available/proxy /etc/nginx/sites-enabled/
            sudo nginx -t
            sudo systemctl restart nginx
            sudo ufw allow $PORT/tcp
            echo "Setup complete! Access at http://<your_server_ip>:$PORT"
            read -p "Press Enter to continue..."
            ;;
        3)
            install_socks5_with_cloudflare
            read -p "Enter the port for the web interface (default: 8080): " PORT
            PORT=${PORT:-8080}
            sudo ufw allow $PORT/tcp
            echo "Access at https://$DOMAIN:$PORT after Cloudflare setup."
            read -p "Press Enter to continue..."
            ;;
        4)
            echo "Exiting script..."
            exit 0
            ;;
        *)
            echo "Invalid option, please try again..."
            read -p "Press Enter to continue..."
            ;;
    esac
done
