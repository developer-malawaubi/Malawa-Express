# Malawa Express

Malawa Express is a cross-border C2C (Consumer-to-Consumer) marketplace designed to empower informal traders, artisans, and buyers operating between South Africa (ZA) and Mozambique (MZ). 

By offering dual-currency pricing, local payment methods, verified seller profiles, and strategically located physical collection points, Malawa Express bridges the gap between regional merchants and modern digital commerce.

---

## Significance and Core Vision

Informal cross-border trade between South Africa and Mozambique accounts for significant economic activity but is frequently hindered by structural challenges. Malawa Express is engineered to address these operational pain points:
* **Language Integration:** Bilingual support in English and Portuguese allows traders to communicate and transact without language barriers.
* **Currency Conversion:** Dynamic pricing in South African Rand (ZAR) and Mozambican Metical (MZN) with automatic conversion rates.
* **Logistics Optimization:** Predefined physical Collection Points (located in Johannesburg, Durban, Cape Town, Maputo, and Ressano Garcia) simplify delivery logistics and package exchanges.
* **Trust and Safety:** Identity verification badges, user ratings, and reviews establish a transparent, accountable trading environment.
* **Payment Accessibility:** Integration with established gateways like PayFast (for South Africa) alongside support for regional mobile money platforms like M-Pesa, mKesh, and eMola (for Mozambique).

---

## Key Features

* **Bilingual Localization:** Fully localized user interface and database support for bilingual product titles, descriptions, and categories.
* **Cross-Border Checkout:** A streamlined cart and checkout workflow with integrated PayFast sandbox support.
* **In-App Messaging:** Direct buyer-to-seller messaging threads to negotiate transactions and arrange pick-up details.
* **Physical Collection Points:** Preconfigured pick-up hubs selectable during checkout to ensure secure handovers.
* **Professional Responsive Interface:** Sleek, high-performance dark-theme UI built with HSL-tailored colors, smooth animations, and optimized layout.
* **Administrative Control Panel:** Comprehensive management interface for administrators to moderate listings, update user account verification status, process orders, and track platform transaction volume.

---

## Technology Stack

* **Back-End:** Core PHP (Session security, routing, validation, template rendering)
* **Database:** MySQL via PDO (Prepared statements for SQL injection prevention, strict foreign key constraints)
* **Front-End:** Vanilla CSS (Flexbox/Grid structure, custom dark-theme variables, fluid transitions) and JavaScript (Real-time client-side search, category filters, and interactive UI controls)

---

## File Structure

```bash
├── index.php                         # Main application router and page renderer
├── database.php                      # Database connection PDO functions and CRUD operations
├── functions.php                     # Localization strings, formatting helpers, and template layout elements
├── data.php                          # Fallback mock data structures (when database is offline)
├── database.sql                      # MySQL schema initialization and seed data
├── admin_migration.sql               # Additional schema upgrades for admin operations
├── config.example.php                # Example environment file
├── config.php                        # Git-ignored local environment configuration (Database/Payment credentials)
├── style.css                         # Custom typography, components, page designs, and transitions
├── script.js                         # Dynamic search filters, tab switching, and navigation controls
├── assets/                           # Media resources, brand icons, and static assets
└── .gitignore                        # Configuration to exclude sensitive database details and system files
```

---

## Setup and Installation

### 1. Prerequisites
* **PHP:** Version 7.4 or newer.
* **Database:** MySQL Server.
* **Web Server:** Apache, Nginx, or the PHP built-in server.

### 2. Database Setup
1. Create a new MySQL database (e.g. `malawa_express_db`).
2. Import the schema and seed data using `database.sql`:
   ```bash
   mysql -u your_username -p malawa_express_db < database.sql
   ```
3. Run the migrations to support admin features:
   ```bash
   mysql -u your_username -p malawa_express_db < admin_migration.sql
   ```

### 3. Application Configuration
1. Duplicate the example configuration file:
   ```bash
   cp config.example.php config.php
   ```
2. Open `config.php` and replace the database and payment gateway credentials with your own details:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'malawa_express_db');
   define('DB_USER', 'your_mysql_user');
   define('DB_PASS', 'your_mysql_password');
   ```

### 4. Running the Project Locally
Run the built-in PHP development server in the root of the project:
```bash
php -S localhost:8000
```
Then, navigate to `http://localhost:8000` in your web browser.

---

## License and Credits
Developed as a regional marketplace solution to connect informal traders and buyers across borders.
