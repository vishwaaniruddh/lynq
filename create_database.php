<?php
/**
 * Create the clarity_db database for the ADV CRM Users Module
 */

// Database connection parameters
$host = "localhost";
$user = "root";
$pass = "";
$new_dbname = "clarity_db";

try {
    // Connect to MySQL server (without specifying database)
    $con = new mysqli($host, $user, $pass);
    
    if ($con->connect_error) {
        throw new Exception("Connection failed: " . $con->connect_error);
    }
    
    echo "Connected to MySQL server successfully.\n";
    
    // Create the new database
    $sql = "CREATE DATABASE IF NOT EXISTS `$new_dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    
    if ($con->query($sql) === TRUE) {
        echo "Database '$new_dbname' created successfully (or already exists).\n";
    } else {
        throw new Exception("Error creating database: " . $con->error);
    }
    
    // Verify database was created
    $result = $con->query("SHOW DATABASES LIKE '$new_dbname'");
    if ($result->num_rows > 0) {
        echo "✓ Database '$new_dbname' verified to exist.\n";
    } else {
        throw new Exception("Database verification failed.");
    }
    
    $con->close();
    echo "\nDatabase setup completed successfully!\n";
    echo "You can now run the migrations to create the tables.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}