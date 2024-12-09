<?php
require_once 'TursoClient.php'; // Include your TursoClient class or autoload if necessary
require_once 'vendor/autoload.php'; // If using composer for dotenv

// Load environment variables (make sure the .env file is set up)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validate database environment variables
$databaseUrl = $_ENV['TURSO_DB_URL'] ?? '';
$authToken = $_ENV['TURSO_AUTH_TOKEN'] ?? '';
if (empty($databaseUrl) || empty($authToken)) {
    throw new \Exception('Database URL or Auth Token is not set in the environment variables.');
}

// Initialize Turso client
$tursoClient = new TursoClient($databaseUrl, $authToken);

// Handle the database operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the data from the POST request
    $email = $_POST['email'];
    $userType = $_POST['userType'];
    $businessName = $_POST['businessName'] ?? null;
    $businessType = $_POST['businessType'] ?? null;
    $businessAddress = $_POST['businessAddress'] ?? null;
    $farmName = $_POST['farmName'] ?? null;
    $biography = $_POST['biography'] ?? null;
    $farmLocation = $_POST['farmLocation'] ?? null;

    try {
        // Set up the database connection
        $tursoClient->connect();

        // Update the user type in the Users table
        $updateUserQuery = "UPDATE Users SET userType = ? WHERE email = ?";
        $tursoClient->execute($updateUserQuery, $userType, $email);

        // Insert corresponding data based on user type
        if ($userType === 'Business') {
            $insertBusinessQuery = "INSERT INTO Businesses (email, business_name, business_type, business_address) VALUES (?, ?, ?, ?)";
            $tursoClient->execute($insertBusinessQuery, $email, $businessName, $businessType, $businessAddress);
        } else if ($userType === 'Farmer') {
            $insertFarmerQuery = "INSERT INTO Farmers (email, farm_name, biography, farm_location) VALUES (?, ?, ?, ?)";
            $tursoClient->execute($insertFarmerQuery, $email, $farmName, $biography, $farmLocation);
        }

        // Close the database connection
        $tursoClient->close();

        echo json_encode(['success' => true, 'message' => 'User details updated successfully.']);
    } catch (Exception $e) {
        // Error handling
        echo json_encode(['success' => false, 'message' => 'Error updating user details: ' . $e->getMessage()]);
    }
}
?>
