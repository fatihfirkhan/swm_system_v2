<div align="center">

# 🗑️ Smart Waste Management System

### Final Year Project | Bachelor of Information Technology
**Universiti Tun Hussein Onn Malaysia (UTHM)**

[![PHP Version](https://img.shields.io/badge/PHP-8.1-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat&logo=docker&logoColor=white)](https://www.docker.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

*A comprehensive web-based solution for efficient waste collection management and community engagement*

[Live Demo](#) • [Documentation](#) • [Report Bug](#) • [Request Feature](#)

</div>

---

## 📋 Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [System Architecture](#-system-architecture)
- [Getting Started](#-getting-started)
- [Deployment](#-deployment)
- [User Roles](#-user-roles)
- [Screenshots](#-screenshots)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🎯 Overview

The **Smart Waste Management System** is a modern, role-based web application designed to streamline waste collection operations and improve resident engagement. The system bridges the gap between waste collection staff, administrators, and residents through real-time scheduling, tracking, and communication features.

### 🌟 Key Highlights

- **Multi-Role Dashboard** - Customized interfaces for Admins, Staff, and Residents
- **Real-Time Tracking** - Live updates on collection schedules and status
- **Photo Evidence System** - Visual proof for complaints and collections
- **Intelligent Scheduling** - Dynamic route and area-based collection planning
- **Responsive Design** - Mobile-friendly interface for on-the-go access
- **Docker Ready** - Containerized deployment for easy scaling

---

## ✨ Features

### 👨‍💼 Admin Features
- 📊 **Comprehensive Dashboard** - View system-wide statistics and trends
- 👥 **User Management** - Manage staff and resident accounts
- 🚛 **Truck & Route Management** - Assign trucks and define collection areas
- 📅 **Schedule Orchestration** - Create and manage collection schedules
- 📈 **Advanced Reporting** - Generate insights and analytics
- 📢 **Broadcast Notifications** - Send announcements to users

### 👷 Staff Features
- 🗺️ **Today's Assignment** - View daily collection routes and areas
- ✅ **Task Management** - Mark collections as completed
- 📍 **Route Optimization** - Access optimized collection routes
- 📊 **Performance Metrics** - Track completion rates and KPIs
- 📝 **Activity Logging** - Record collection activities

### 🏠 Resident Features
- 📅 **Collection Schedule** - View upcoming waste collection dates
- 📢 **Complaint System** - Report missed collections with photo uploads
- 📍 **Area-Based Information** - Location-specific collection details
- 🔔 **Notifications** - Receive schedule updates and announcements
- 📊 **Personal Dashboard** - Track complaint status and history
- 🔐 **Password Reset** - Secure email-based password recovery

---

## 🛠️ Tech Stack

### Backend
![PHP](https://img.shields.io/badge/PHP-8.1-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-2.4-D22128?style=for-the-badge&logo=apache&logoColor=white)

### Frontend
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)

### DevOps & Tools
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Git](https://img.shields.io/badge/Git-F05032?style=for-the-badge&logo=git&logoColor=white)
![AWS EC2](https://img.shields.io/badge/AWS_EC2-FF9900?style=for-the-badge&logo=amazon-aws&logoColor=white)

### Libraries & Frameworks
- **SB Admin 2** - Admin dashboard template
- **Chart.js** - Data visualization
- **DataTables** - Interactive tables
- **Font Awesome** - Icons
- **jQuery** - DOM manipulation

---

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Client Layer                         │
│  (Web Browsers: Desktop, Mobile, Tablet)                    │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                    Presentation Layer                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │    Admin     │  │     Staff    │  │   Resident   │      │
│  │  Dashboard   │  │  Dashboard   │  │  Dashboard   │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                   Application Layer                          │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐            │
│  │   Auth     │  │  Schedule  │  │ Complaint  │            │
│  │  Handler   │  │  Manager   │  │  Handler   │            │
│  └────────────┘  └────────────┘  └────────────┘            │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                     Data Layer                               │
│              MySQL Database (Docker)                         │
│  ┌──────┐  ┌──────────┐  ┌──────────┐  ┌──────┐           │
│  │Users │  │ Schedule │  │Complaints│  │Trucks│           │
│  └──────┘  └──────────┘  └──────────┘  └──────┘           │
└─────────────────────────────────────────────────────────────┘
```

---

## 🚀 Getting Started

### Prerequisites

- **Docker** & **Docker Compose** (recommended)
- OR **XAMPP/LAMP Stack** (PHP 8.1+, MySQL 8.0+, Apache 2.4+)
- Git

### 📦 Installation

#### Option 1: Docker (Recommended)

```bash
# Clone repository
git clone https://github.com/fatihfirkhan/swm_system_v2.git
cd swm_system_v2

# Build and start containers
docker compose up -d --build

# Import database
docker exec -i swm_db mysql -u root -proot123 swm_system < swm_system.sql

# Access application
open http://localhost
```

#### Option 2: Local (XAMPP)

```bash
# Clone to htdocs
cd C:\xampp\htdocs
git clone https://github.com/fatihfirkhan/swm_system_v2.git

# Import database
mysql -u root swm_system < swm_system.sql

# Start XAMPP services (Apache, MySQL)

# Access application
open http://localhost/swm_system_v2/public
```

### 🔑 Default Login Credentials

| Role     | Username/Email          | Password  |
|----------|-------------------------|-----------|
| Admin    | `ADM001`                | `admin123`|
| Staff    | `STF001`                | `staff123`|
| Resident | `resident@example.com`  | `pass123` |

---

## ☁️ Deployment

### AWS EC2 Deployment

```bash
# SSH to EC2
ssh -i your-key.pem ubuntu@your-ec2-ip

# Clone repository
git clone https://github.com/fatihfirkhan/swm_system_v2.git
cd swm_system_v2

# Build and run
docker compose up -d --build

# Import database
docker exec -i swm_db mysql -u root -proot123 swm_system < swm_system.sql
```

### Environment Configuration

Update database credentials in `includes/db.php`:

```php
$host = "db";              // Docker: 'db', Local: 'localhost'
$dbname = "swm_system";
$username = "root";
$password = "root123";     // Match docker-compose.yml
```

---

## 👥 User Roles

### 🔴 Administrator
- Full system access and control
- User and resource management
- System-wide reporting and analytics
- Notification broadcasting

### 🔵 Staff (Waste Collection)
- View assigned collection routes
- Update collection status
- Access performance metrics
- Log daily activities

### 🟢 Resident
- View collection schedules
- Submit complaints with evidence
- Track complaint resolution
- Receive notifications

---

## � Password Reset Feature

The system includes a secure password reset feature for residents:

### Setup Instructions

1. **Run Database Migration**
   ```bash
   # Access the setup page
   http://localhost/swm_system_v2/setup_password_reset.php
   ```
   Click "Run Setup" to create the required database table.

2. **Configure Email Settings**
   
   See [EMAIL_SETUP.md](EMAIL_SETUP.md) for detailed configuration options:
   - Gmail SMTP (Recommended)
   - Local PHP mail()
   - Alternative SMTP providers

3. **Test the Feature**
   - Visit login page and click "Forgot Password?"
   - Enter resident email address
   - Check email for reset link
   - Set new password

### Security Features
- ✅ Secure token generation (64-character random hash)
- ✅ Token expires after 1 hour
- ✅ One-time use tokens
- ✅ Password hashing with bcrypt
- ✅ Email validation
- ✅ Resident-only access

### Files Added
- `public/forgot_password.php` - Request reset link
- `public/reset_password.php` - Set new password
- `backend/handle_forgot_password.php` - Generate token & send email
- `backend/handle_reset_password.php` - Verify token & update password
- `add_password_reset_table.sql` - Database migration
- `setup_password_reset.php` - Setup wizard
- `EMAIL_SETUP.md` - Email configuration guide

---

## �📸 Screenshots

<details>
<summary>Click to expand screenshots</summary>

### Admin Dashboard
![Admin Dashboard](docs/screenshots/admin-dashboard.png)

### Staff Dashboard
![Staff Dashboard](docs/screenshots/staff-dashboard.png)

### Resident Dashboard
![Resident Dashboard](docs/screenshots/resident-dashboard.png)

### Schedule Management
![Schedule Management](docs/screenshots/schedule.png)

### Complaint System
![Complaint System](docs/screenshots/complaints.png)

</details>

---

## 📁 Project Structure

```
swm_system_v2/
├── 📂 api/                      # API endpoints
├── 📂 assets/                   # Images, logos
├── 📂 backend/                  # Backend logic
│   ├── handle_login.php
│   ├── handle_register.php
│   └── staff/
├── 📂 includes/                 # Shared components
│   ├── db.php                   # Database connection
│   ├── admin_sidebar.php
│   └── staff/
├── 📂 public/                   # Public web root
│   ├── admin_dashboard.php
│   ├── staff_dashboard.php
│   ├── resident_dashboard.php
│   ├── 📂 css/                  # Stylesheets
│   ├── 📂 js/                   # JavaScript files
│   ├── 📂 uploads/              # User uploads
│   └── 📂 vendor/               # Third-party libraries
├── 📄 Dockerfile                # Docker configuration
├── 📄 docker-compose.yml        # Docker services
├── 📄 swm_system.sql            # Database schema
└── 📄 README.md                 # This file
```

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Author

**Muhammad Fatih Firkhan**  
Bachelor of Information Technology  
Universiti Tun Hussein Onn Malaysia (UTHM)

- 🌐 GitHub: [@fatihfirkhan](https://github.com/fatihfirkhan)
- 📧 Email: your.email@example.com
- 💼 LinkedIn: [Your Profile](#)

---

## 🙏 Acknowledgments

- **UTHM Faculty of Computer Science and Information Technology** - Academic supervision
- **SB Admin 2** - Dashboard template
- **Bootstrap Team** - UI framework
- **Docker Community** - Containerization support

---

<div align="center">

**⭐ Star this repository if you find it helpful!**

Made with ❤️ for efficient waste management

</div>
