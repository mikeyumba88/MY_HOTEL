# My Hotel - Hotel Management System

A comprehensive hotel management system built with PHP and MySQL, featuring role-based access control for administrators, receptionists, and guests.

## 🏨 Features

### Guest Features
- User registration and authentication
- View available rooms
- Book rooms online
- View and manage personal bookings
- Request booking modifications
- Request booking cancellations

### Receptionist Features
- Dashboard overview
- Book rooms on behalf of guests
- Manage check-ins and check-outs
- View and manage bookings
- View room availability

### Admin Features
- Full system dashboard
- Manage users (admin, receptionists, guests)
- Manage rooms (add, edit, delete, pricing)
- Manage all bookings
- Review and approve modification/cancellation requests
- Audit log viewing and tracking

### System Features
- Role-based access control (Admin, Receptionist, Guest)
- Room availability tracking
- Booking status management (Pending, Confirmed, Checked-in, Checked-out, Cancelled, Completed)
- Audit logging for all critical operations
- Edit and cancellation request workflow
- Early checkout detection
- Multi-currency support (default: ZMW - Zambian Kwacha)

## 🛠️ Tech Stack

- **Backend**: PHP (with PDO for database access)
- **Database**: MySQL/MariaDB
- **Frontend**: HTML, CSS, vanilla JavaScript
- **Server**: Apache (XAMPP)

## 📁 Project Structure

```
my_hotel/
├── admin/              # Admin panel pages
│   ├── admin_dashboard.php
│   ├── manage_users.php
│   ├── manage_rooms.php
│   ├── manage_bookings.php
│   ├── manage_requests.php
│   ├── audit_integration.php
│   ├── AuditLog.php
│   └── ...
├── reception/          # Receptionist panel pages
│   ├── reception_dashboard.php
│   ├── book_for_guest.php
│   ├── manage_checkins.php
│   ├── manage_checkouts.php
│   └── ...
├── public/             # Guest-facing pages
│   ├── login.php
│   ├── register.php
│   ├── guest_dashboard.php
│   ├── book_room.php
│   ├── my_bookings.php
│   ├── view_rooms.php
│   └── ...
├── classes/            # PHP class files
│   ├── User.php
│   ├── Room.php
│   ├── Booking.php
│   └── AuditLog.php
├── config/             # Configuration files
│   └── db.php          # Database connection
├── helpers/            # Helper functions
│   └── currency.php
├── css/                # Stylesheets
│   └── style.css
└── database/           # Database setup
    └── schema.sql
```

## 🚀 Installation

### Prerequisites
- XAMPP (or LAMP/WAMP) installed
- PHP 7.4+ 
- MySQL/MariaDB

### Step-by-Step Setup

1. **Clone/Copy the project**
   ```bash
   # Copy the my_hotel folder to your XAMPP htdocs directory
   # Usually: C:\xampp\htdocs\my_hotel
   ```

2. **Start XAMPP services**
   - Start Apache server
   - Start MySQL server

3. **Set up the database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the database schema from `database/schema.sql`
   - This will create the `hotel_db` database with all required tables

4. **Configure database connection**
   - Open `config/db.php`
   - Update the database credentials if needed:
     ```php
     $host = "localhost";
     $dbname = "hotel_db";
     $username = "root";
     $password = "";  // Default XAMPP password is empty
     ```

5. **Create an admin user**
   - Run the `admin/create_admin.php` script in your browser
   - Or manually insert an admin user into the database

6. **Access the application**
   - Guest/Login page: `http://localhost/my_hotel/public/login.php`
   - Admin panel: `http://localhost/my_hotel/admin/admin_dashboard.php`
   - Reception panel: `http://localhost/my_hotel/reception/reception_dashboard.php`

## 🗄️ Database Schema

The system uses the following tables:

| Table | Description |
|-------|-------------|
| `users` | Stores user accounts (admins, receptionists, guests) |
| `rooms` | Stores room information and pricing |
| `bookings` | Stores all booking records |
| `edit_requests` | Stores booking modification requests |
| `cancellation_requests` | Stores booking cancellation requests |
| `audit_logs` | Stores audit trail of all system changes |

## 👤 User Roles

| Role | Permissions |
|------|-------------|
| **Admin** | Full access to all features, user management, system configuration |
| **Receptionist** | Book rooms for guests, manage check-ins/outs, view bookings |
| **Guest** | Register, book rooms, view own bookings, request modifications |

## 🔒 Security Features

- Password hashing for user authentication
- Role-based access control
- SQL injection protection via PDO prepared statements
- Audit logging for tracking system changes
- IP address and user agent logging

## 📝 Booking Workflow

1. **Guest books a room** → Status: `Pending`
2. **Receptionist/Admin confirms** → Status: `Confirmed`
3. **Guest checks in** → Status: `Checked-in`
4. **Guest checks out** → Status: `Checked-out` or `Completed`

### Cancellation/Modification Requests
- Guests can request booking modifications or cancellations
- Requests are reviewed by Admin/Reception
- Approved requests are processed accordingly

## 🛡️ Audit Logging

The system includes comprehensive audit logging that tracks:
- User actions (create, update, delete)
- Table changes with before/after values
- IP addresses and user agents
- Timestamps for all actions

## 📄 License

This project is open-source and available for educational and commercial use.

## 🤝 Contributing

Feel free to submit issues and enhancement requests!

## 📧 Support

For support, please contact the development team or open an issue in the project repository.
