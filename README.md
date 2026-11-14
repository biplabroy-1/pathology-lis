# PathoLab LIS — Complete Diagnostic Laboratory Information System

A modern, full-featured Diagnostic Laboratory Information System (LIS) built with **Laravel 13**, **Tailwind CSS v4**, and **MySQL**. Designed for hospital networks, reference labs, and multi-branch diagnostic centers with NABL accreditation support.

---

## 🌟 Key Features

1. **Dashboard & Analytics**: Real-time KPI telemetry, daily revenue charts, sample rejection stats, machine & department workload monitors.
2. **Patient Management (UHID)**: Unique Patient ID generator, demographic history, clinical notes, document/prescription uploads, and duplicate record merge.
3. **Appointment Scheduling**: Online, walk-in, and home collection slots with SMS/WhatsApp confirmation alerts.
4. **Doctor & Referrer Management**: Specialization tracking, referral volume analytics, and digital report sharing portal.
5. **Investigation Master (16 Categories)**: Covers Haematology, Biochemistry, Microbiology, Serology, Histopathology, Radiology, Cardiology, etc. with gender/age-specific reference ranges.
6. **Health Checkup Packages**: Pre-configured wellness & corporate checkup bundles with custom package builder.
7. **Billing & Invoicing**: Test/package billing, discount coupons, GST calculation, multi-mode payments (Cash, UPI, Card), partial/advance payments, and printable tax invoices.
8. **Sample Collection & Accession**: Barcode generation, accession numbering, container tracking, specimen acceptance/rejection, and storage rack mapping.
9. **Phlebotomy & Home Collection**: Route planning, phlebotomist dispatch, mobile collection checklist, and cash reconciliation.
10. **11 Laboratory Department Queues**: Dedicated workflow queues for each department with TAT monitoring.
11. **Worklist & Priority Processing**: STAT/Urgent prioritization, technician assignment, and rerun records.
12. **Equipment & Analyzers**: Calibration/preventive maintenance scheduling, downtime tracking, and HL7/ASTM integration readiness.
13. **Reagents & Consumables Inventory**: Batch & expiry alerts, min-stock triggers, stock-in/out adjustments, and purchase order tracking.
14. **Result Entry & Verification**: Parameter-wise entry, abnormal/critical flagging, delta checks, technician submission, and pathologist electronic signature.
15. **Reports & QR Delivery**: Professional NABL report layout, QR verification, English/Hindi bilingual toggle, and WhatsApp/Email delivery history.
16. **Quality Control (QC) & QA**: Interactive Levey-Jennings charts, Westgard rules evaluation (`1-2s`, `1-3s`), and CAPA incident tracking.
17. **Critical Value Alerts**: Automatic threshold alerts with acknowledgement logging.
18. **Doctor & Patient Portals**: Dedicated self-service portals for report downloads and status tracking.
19. **Role-Based Authentication**: Strict auth protection across all routes with audit trail logging.

---

## 🚀 Quick Setup Guide

### 1. Requirements
- PHP `>= 8.4`
- Composer `>= 2.0`
- MySQL / MariaDB (via XAMPP or native)
- Node.js & npm

### 2. Installation

```bash
# Clone the repository
git clone https://github.com/webotapp-academy/pathology.git
cd pathology

# Install PHP & JS dependencies
composer install
npm install
npm run build

# Environment configuration
cp .env.example .env
php artisan key:generate

# Configure database in .env:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=pathology
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations & seed data
php artisan migrate --seed

# Start development server
php artisan serve
```

### 3. Demo Staff Logins (Password: `password`)
- **Chief Pathologist**: `admin@patholab.local`
- **Lab Technician**: `tech@patholab.local`
- **Front Desk & Billing**: `desk@patholab.local`

---

## 📄 License
This project is open-source under the MIT license.
