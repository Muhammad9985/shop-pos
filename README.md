# Shop POS - Point of Sale System

[![GitHub stars](https://img.shields.io/github/stars/Muhammad9985/shop-pos.svg)](https://github.com/Muhammad9985/shop-pos/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/Muhammad9985/shop-pos.svg)](https://github.com/Muhammad9985/shop-pos/network)
[![GitHub issues](https://img.shields.io/github/issues/Muhammad9985/shop-pos.svg)](https://github.com/Muhammad9985/shop-pos/issues)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A comprehensive web-based Point of Sale (POS) system built with PHP and MySQL for retail businesses. Features inventory management, sales tracking, user roles, and complete business analytics.

## 🌟 Live Demo

[View Live Demo](https://mr-software.online/) | [Portfolio](https://mr-software.online/)

## 🚀 Features

- **Point of Sale Interface** - Fast product selection and checkout
- **Inventory Management** - Track products, categories, and stock levels
- **Purchase Management** - Record supplier purchases and pricing
- **Sales Analytics** - Daily, weekly, monthly sales reports with charts
- **User Management** - Admin and shopkeeper roles with permissions

- **Responsive Design** - Works on desktop, tablet, and mobile devices

## 📋 System Requirements

- **PHP:** 7.4 or higher
- **MySQL:** 5.7 or higher
- **Web Server:** Apache/Nginx
- **Browser:** Modern browsers (Chrome, Firefox, Safari, Edge)

## 🛠️ Installation

### Clone the Repository
```bash
git clone https://github.com/Muhammad9985/shop-pos.git
cd shop-pos
```

### Setup Steps
1. **Clone/Download** the project files
2. **Upload** to your web server directory
3. **Create** MySQL database and import `database.sql`
4. **Configure** database connection in `config/database.php`
5. **Set permissions** for `uploads/products/` folder (755)
6. **Access** via web browser and login

### Default Login Credentials

#### Admin Account
- **Username:** `admin`
- **Password:** `password`
- **Access:** Full system control, all modules

#### Shopkeeper Account  
- **Username:** `shopkeeper`
- **Password:** `password`
- **Access:** Configurable by admin (default: POS only)

⚠️ **IMPORTANT: Change default passwords immediately after installation for security!**

## 📁 Project Structure

```
shop-pos/
├── api/
│   └── sales.php              # Sales API endpoint
├── assets/
│   ├── css/
│   │   └── style.css          # Main stylesheet
│   └── js/
│       └── script.js          # JavaScript functionality
├── config/
│   └── database.php           # Database configuration
├── includes/
│   ├── header.php             # Common header
│   └── footer.php             # Common footer
├── uploads/
│   └── products/              # Product images storage
├── auth.php                   # Authentication functions
├── categories.php             # Category management
├── daily-sales.php            # Sales analytics
├── dashboard.php              # Business dashboard
├── database.sql               # Database schema
├── index.php                  # Point of Sale interface
├── login.php                  # User login
├── logout.php                 # User logout
├── products.php               # Product management
├── purchases.php              # Purchase management
├── roles.php                  # User role management
└── update-password.php        # Password update handler
```

## 👥 User Roles & Permissions

### 🔑 Admin Role
**Full system access with all permissions:**

| Module | View | Add | Edit | Delete |
|--------|------|-----|------|--------|
| Dashboard | ✅ | - | - | - |
| Point of Sale | ✅ | ✅ | - | - |
| Products | ✅ | ✅ | ✅ | ✅* |
| Categories | ✅ | ✅ | ✅ | ✅* |
| Purchases | ✅ | ✅ | ✅ | - |
| Daily Sales | ✅ | - | - | - |
| Role Management | ✅ | - | ✅ | - |
| Password Management | ✅ | - | ✅ | - |

*Cannot delete items with existing stock/relationships

### 👤 Shopkeeper Role
**Configurable permissions set by admin:**

| Module | Default Access |
|--------|----------------|
| Point of Sale | ✅ Full Access |
| Dashboard | ❌ No Access (configurable) |
| Products | ❌ No Access (configurable) |
| Categories | ❌ No Access (configurable) |
| Purchases | ❌ No Access (configurable) |
| Daily Sales | ❌ No Access (configurable) |
| Role Management | ❌ No Access |

## 📄 Page Details

### 🏪 Point of Sale (`index.php`)
**Main sales interface for processing transactions**
- Product grid with category filtering
- Real-time stock checking
- Shopping cart with price input
- Individual item entry system
- Receipt generation and printing
- Transaction ID generation
- Stock validation before sale

**Access:** All logged-in users

### 📊 Dashboard (`dashboard.php`)
**Business overview and analytics**
- Today's sales summary
- Monthly revenue with growth comparison
- Low stock alerts
- Top-selling products
- Sales trend charts (last 7 days)
- Category performance analysis
- Recent transactions list

**Access:** Admin or users with dashboard permission

### 📦 Products (`products.php`)
**Complete product inventory management**
- Add/edit/delete products
- Product images upload
- Brand and category assignment
- Real-time stock calculation (purchases - sales)
- Card and table view modes
- Advanced filtering (category, stock level, search)
- Stock status indicators (good/low/out)
- Bulk operations support

**Access:** Admin or users with products permission
**Restrictions:** Cannot delete products with stock

### 🏷️ Categories (`categories.php`)
**Product category organization**
- Create/edit/delete categories
- Auto-generated URL slugs
- Product count per category
- Search and filter categories
- Category usage tracking

**Access:** Admin or users with categories permission
**Restrictions:** Cannot delete categories with products

### 🛒 Purchases (`purchases.php`)
**Supplier purchase tracking**
- Record product purchases from suppliers
- Set unit price and sale price
- Quantity management
- Supplier information tracking
- Purchase history with filtering
- Cost analysis and reporting
- Automatic stock calculation

**Access:** Admin or users with purchases permission

### 📈 Daily Sales (`daily-sales.php`)
**Comprehensive sales analytics**
- Customizable date ranges (today, week, month, year)
- Category-wise sales filtering
- Hourly sales trend charts
- Top products analysis
- Category performance metrics
- Revenue summaries with market share
- Recent transactions tracking
- Print-friendly reports

**Access:** Admin or users with daily-sales permission

### 👥 Role Management (`roles.php`)
**User permission configuration**
- Manage shopkeeper permissions
- Granular access control (view/add/edit/delete)
- Module-wise permission setting
- Password management for users
- Permission templates

**Access:** Admin only

### 🔐 Authentication System
**Secure user management**
- Session-based authentication
- Password hashing (PHP password_hash)
- Role-based access control
- Permission validation on every request
- Automatic logout on inactivity
- Password change functionality

## 🛡️ Security Features

### 🔐 Access Control
- **Role-based permissions** system
- **Session management** with timeout
- **SQL injection protection** via prepared statements
- **XSS protection** with input sanitization
- **CSRF protection** on forms
- **File upload validation** for images

### 🚫 Business Logic Protection
- **Stock validation** before sales
- **Referential integrity** protection
- **Deletion restrictions** for items with dependencies
- **Input validation** on all forms
- **Error handling** with user-friendly messages

## 🎨 User Interface

### 📱 Responsive Design
- **Mobile-first** approach
- **Touch-friendly** interface for tablets
- **Collapsible sidebar** for small screens
- **Optimized layouts** for different screen sizes

### 🎯 User Experience
- **Modal-based confirmations** (no browser alerts)
- **Real-time search** and filtering
- **Drag-and-drop** file uploads
- **Keyboard shortcuts** support
- **Loading indicators** for async operations
- **Toast notifications** for user feedback

### 🎨 Visual Design
- **Modern CSS3** styling with gradients
- **FontAwesome icons** throughout
- **Color-coded status** indicators
- **Consistent spacing** and typography
- **Professional color scheme**
- **Hover effects** and transitions

## 📊 Database Schema

### Core Tables
- **users** - User accounts and roles
- **user_permissions** - Granular permission system
- **categories** - Product categories
- **products** - Product inventory
- **purchases** - Supplier purchase records
- **sales** - Individual sale items
- **transactions** - Sale transaction headers

### Key Relationships
- Products belong to categories
- Sales reference products and transactions
- Purchases reference products
- User permissions reference users
- Foreign key constraints maintain data integrity

## 🔧 Configuration

### Database Setup
```php
// config/database.php
$host = "localhost";
$db_name = "shop_pos";
$username = "your_username";
$password = "your_password";
```

## 🚀 Deployment Guide

### Installation Steps
1. **Upload files** to web server
2. **Create database** and import schema
3. **Configure database** connection
4. **Set folder permissions** for uploads
5. **Access via browser** and change passwords
6. **Configure business settings**

## 📞 Support & Contact

### 👨💻 Developer
**Muhammad Rafique**
- **Portfolio:** [mr-software.online](https://mr-software.online/)
- **LinkedIn:** [Muhammad Rafique](https://www.linkedin.com/in/muhammad-rafique-944b05159)
- **Email:** rafiqalbaloshi3@gmail.com
- **WhatsApp:** +923156224392

### 🆘 Technical Support
For technical support, bug reports, or customization:
- **Response Time:** 24-48 hours
- **Available Services:**
  - Custom feature development
  - Integration with external systems
  - UI/UX modifications
  - Multi-location support
  - Training and documentation

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## ⭐ Show Your Support

Give a ⭐️ if this project helped you!

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Thanks to all contributors who helped improve this project
- Built with modern web technologies for optimal performance
- Designed with user experience in mind

---

**© 2025 Muhammad Rafique. All rights reserved.**

*Built with ❤️ for retail businesses worldwide*

