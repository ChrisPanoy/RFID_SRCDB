# Teacher Barcode System Setup Guide

## 🚀 Quick Setup Instructions

### Step 1: Database Migration
1. Open phpMyAdmin or your MySQL client
2. Select your attendance database (`aiesccsc_attendance`)
3. Go to SQL tab
4. Copy and paste the contents of `database/add_teacher_barcode.sql`
5. Click "Go" to execute the script

### Step 2: Test the Setup
1. Visit: `http://localhost/aiesccs/test_teacher_barcode.php`
2. Check if the database migration was successful
3. Review teacher barcode status

### Step 3: Generate Barcodes
1. Go to **Manage Teachers** (`manage_teachers.php`)
2. Ensure teachers have **Teacher ID** set (e.g., "001", "02")
3. Click **"Generate"** button for teachers with Teacher ID
4. Barcode images will be created in `assets/barcodes/` directory

### Step 4: Test Login
1. Go to **Teacher Login** (`teacher_login.php`)
2. Click **"Barcode Scan"** tab
3. Enter a teacher's Teacher ID (e.g., "001") or scan barcode
4. Login should work automatically

## 📋 Features Overview

### Barcode Status Indicators:
- 🟢 **Green**: "Ready for scanning" (barcode generated + image exists)
- 🟠 **Orange**: "Ready to Generate" (has Teacher ID, needs barcode generation)
- 🔴 **Red**: "No Teacher ID" (must set Teacher ID first)

### Login Options:
- **Email/Password**: Traditional login method
- **Barcode Scan**: Scan Teacher ID barcode or type Teacher ID manually

### Key Benefits:
- **Simple**: Teacher ID = Barcode (no complex codes)
- **Consistent**: Matches student system behavior
- **Flexible**: Can scan OR type Teacher ID manually
- **Print Ready**: Professional SVG barcode images

## 🔧 Troubleshooting

### Database Issues:
- Make sure the SQL script runs without errors
- Check that `barcode` column exists in `teachers` table
- Verify existing teachers have their `teacher_id` values

### Barcode Generation Issues:
- Ensure `assets/barcodes/` directory is writable
- Check that teachers have valid `teacher_id` values
- Verify JsBarcode library loads correctly

### Login Issues:
- Test with known Teacher ID values
- Check database connection
- Verify teacher account is verified in users table

## 📁 Files Modified:
- `manage_teachers.php` - Barcode generation and display
- `teacher_login.php` - Dual login system (Email + Barcode)
- `database/add_teacher_barcode.sql` - Database migration
- `test_teacher_barcode.php` - Testing utility

## 🎯 Usage Workflow:
1. **Admin**: Set Teacher ID → Generate Barcode → Print for teacher
2. **Teacher**: Scan barcode OR type Teacher ID → Automatic login

---
**Ready to use!** The system now works exactly like the student barcode system.
