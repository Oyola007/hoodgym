# 🏋️ HoodGym

A multi-role gym management web application with separate portals for **admins, trainers and members**. Built with PHP and MySQL, designed with a dark industrial look (black and hazard-yellow).

## ✨ Features

- **Three role-based portals:** admin, trainer and member, each with its own dashboard
- **Admin tools:** manage trainers, users, packages, classes, bookings and payments
- **Bookings and classes:** members browse packages and classes and book sessions, with approval handled by admin
- **Payments:** M-Pesa STK Push (Safaricom Daraja), plus Paybill, bank transfer and a Flutterwave card option
- **Progress tracking:** database-driven statistics and progress charts for members
- **AI chat widget:** built-in assistant powered by the Anthropic (Claude) API

## 🛠 Tech stack

| Area | Tools |
|---|---|
| Backend | PHP |
| Database | MySQL |
| Frontend | HTML, CSS, JavaScript |
| Payments | M-Pesa Daraja API, Flutterwave |
| AI | Anthropic API (Claude) |
| Local server | XAMPP |

## 🚀 Run it locally (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this project folder into `C:\xampp\htdocs\hoodgym`.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`), create a database (for example `hood_gym`), and import `hood_gym.sql`. Then import the other `.sql` files (schema additions, updates and migrations) if your tables need them.
4. Add your own keys. Open `api/config.php` and `mpesa_config.php` and replace every `YOUR_..._HERE` placeholder with your real values (database details, Anthropic API key, M-Pesa Daraja keys).
5. Create an empty `uploads` folder in the project root if it doesn't exist.
6. Visit `http://localhost/hoodgym` in your browser.

> **Note:** M-Pesa STK Push callbacks need a public HTTPS address. For local testing, use a tunnel such as ngrok and put its address in the callback URL setting in `mpesa_config.php`.

## 🔐 Security

No real passwords, API keys or customer data are included in this repository. All secrets are placeholders, so you must add your own before the payments and AI chat will work.

## 📸 Screenshots

_Add screenshots of the admin, trainer and member dashboards here._

## 👤 Author

**Evans Omondi Oyola**: BSc ICT graduate, Kenya
[GitHub](https://github.com/Oyola007) · [Portfolio](https://oyola007.github.io) · evansomondi392@gmail.com
