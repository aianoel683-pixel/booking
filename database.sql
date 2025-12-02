-- Hotel Booking System Database Schema
-- Created for comprehensive hotel management

CREATE DATABASE IF NOT EXISTS hotel_booking_system;
USE hotel_booking_system;

-- Users table with role-based access
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'front_desk', 'housekeeping', 'manager') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Room categories table
CREATE TABLE room_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL,
    max_occupancy INT NOT NULL,
    amenities TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rooms table
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_number VARCHAR(10) UNIQUE NOT NULL,
    category_id INT NOT NULL,
    floor INT,
    status ENUM('available', 'occupied', 'reserved', 'maintenance', 'cleaning') DEFAULT 'available',
    housekeeping_status ENUM('clean', 'dirty', 'needs_maintenance') DEFAULT 'clean',
    features TEXT,
    photo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES room_categories(id)
);

-- Guests table
CREATE TABLE guests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    id_type VARCHAR(50),
    id_number VARCHAR(50),
    id_photo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bookings table
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    guest_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    adults INT DEFAULT 1,
    children INT DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'confirmed',
    payment_status ENUM('pending', 'paid', 'partial') DEFAULT 'pending',
    special_requests TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Payments table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'online') NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    transaction_id VARCHAR(100),
    notes TEXT,
    processed_by INT NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Extra charges table (minibar, room service, etc.)
CREATE TABLE extra_charges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    charge_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Housekeeping tasks table
CREATE TABLE housekeeping_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    assigned_to INT NOT NULL,
    task_type ENUM('cleaning', 'maintenance', 'inspection') NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Maintenance requests table
CREATE TABLE maintenance_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    reported_by INT NOT NULL,
    issue_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    photo_url VARCHAR(255),
    status ENUM('reported', 'in_progress', 'resolved') DEFAULT 'reported',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (reported_by) REFERENCES users(id)
);

-- System settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hotel_name VARCHAR(100) NOT NULL,
    hotel_address TEXT,
    hotel_phone VARCHAR(20),
    hotel_email VARCHAR(100),
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    check_in_time TIME DEFAULT '14:00:00',
    check_out_time TIME DEFAULT '12:00:00',
    currency VARCHAR(3) DEFAULT 'PHP',
    logo_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default room categories
INSERT INTO room_categories (name, description, base_price, max_occupancy, amenities) VALUES
('Single', 'Cozy single room with basic amenities', 1500.00, 1, 'WiFi, TV, AC, Private Bathroom'),
('Twin', 'Two single beds perfect for friends or colleagues', 2000.00, 2, 'WiFi, TV, AC, Private Bathroom, Mini-fridge'),
('Deluxe', 'Spacious room with premium amenities', 3000.00, 3, 'WiFi, Smart TV, AC, Private Bathroom, Mini-bar, Coffee Maker'),
('Suite', 'Luxurious suite with separate living area', 5000.00, 4, 'WiFi, Smart TV, AC, Private Bathroom, Mini-bar, Coffee Maker, Sitting Area');

-- Insert default admin user
INSERT INTO users (username, password, email, first_name, last_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@hotel.com', 'System', 'Administrator', 'admin');

-- Insert default system settings
INSERT INTO system_settings (hotel_name, hotel_address, hotel_phone, hotel_email, tax_rate) VALUES
('Grand Hotel', '123 Main Street, City Center', '+63-912-345-6789', 'info@grandhotel.com', 12.00);