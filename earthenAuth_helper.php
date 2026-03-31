<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/security-headers.php';
send_security_headers();








function startSecureSession() {
    // Start the session if it's not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Regenerate the session ID periodically to prevent session fixation
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['CREATED'] > 1800) { // Regenerate session ID every 30 minutes
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}

// Function to check user login status
function isLoggedIn() {
    return isset($_SESSION['buwana_id']) ? true : false;
}

// Define $is_logged_in globally for all scripts including this file
startSecureSession();
$is_logged_in = isLoggedIn();



// Example function to handle null for integer binding
function getUserFirstName($buwana_conn, $buwana_id) {
    $first_name = '';

    // Ensure $buwana_id is an integer; otherwise, default to 0
    $buwana_id = $buwana_id ?? 0;

    $sql_user_info = "SELECT first_name FROM users_tb WHERE buwana_id = ?";
    $stmt_user_info = $buwana_conn->prepare($sql_user_info);

    if ($stmt_user_info) {
        $stmt_user_info->bind_param('i', $buwana_id);
        if ($stmt_user_info->execute()) {
            $stmt_user_info->bind_result($first_name);
            $stmt_user_info->fetch();
        }
        $stmt_user_info->close();
    } else {
        error_log("Failed to prepare statement in getUserFirstName: " . $buwana_conn->error);
    }

    // Handle empty result
    return $first_name ?: '👤';
}


function getWatershedName($buwana_conn, $buwana_id) {
    $watershed_name = '';
    // Query to get the user's watershed name from users_tb
    $sql_watershed = "SELECT location_watershed FROM users_tb WHERE buwana_id = ?";
    $stmt_watershed = $buwana_conn->prepare($sql_watershed);

    if ($stmt_watershed) {
        $stmt_watershed->bind_param('i', $buwana_id); // buwana_id is numeric
        if ($stmt_watershed->execute()) {
            $stmt_watershed->bind_result($watershed_name);
            $stmt_watershed->fetch();
            $stmt_watershed->close();
        }
    }

    // If $watershed_name is still empty or null, set a default value
    if (empty($watershed_name)) {
        $watershed_name = 'Unknown Watershed'; // Default value if no valid watershed name is found
    }

    return $watershed_name;
}


function getFirstName($buwana_conn, $buwana_id) {
    $first_name = '';

    // Query to get the user's first name from users_tb
    $sql_firstName = "SELECT first_name FROM users_tb WHERE buwana_id = ?";
    $stmt_firstName = $buwana_conn->prepare($sql_firstName);

    if ($stmt_firstName) {
        $stmt_firstName->bind_param('i', $buwana_id); // Assuming buwana_id is an integer
        if ($stmt_firstName->execute()) {
            $stmt_firstName->bind_result($first_name); // Fetch result into $first_name
            $stmt_firstName->fetch();
            $stmt_firstName->close();
        }
    }

    // If $first_name is still empty or null, set a default value
    if (empty($first_name)) {
        $first_name = '👤'; // Default value if no valid first name is found
    }

    return $first_name;
}



function getCommunityName($buwana_conn, $buwana_id) {
    $community_name = ''; // Initialize the community name variable

    // Step 1: Query to get the user's community_id from users_tb
    $sql_community_id = "SELECT community_id FROM users_tb WHERE buwana_id = ?";
    $stmt_community_id = $buwana_conn->prepare($sql_community_id);

    if ($stmt_community_id) {
        $stmt_community_id->bind_param('i', $buwana_id); // Assuming buwana_id is an integer now
        if ($stmt_community_id->execute()) {
            $stmt_community_id->bind_result($community_id);
            $stmt_community_id->fetch();
            $stmt_community_id->close();

            // Step 2: Use the retrieved community_id to get the com_name from communities_tb
            if (!empty($community_id)) {
                // community_id is the primary key in communities_tb
                // use it to retrieve the com_name
                $sql_community_name = "SELECT com_name FROM communities_tb WHERE community_id = ?";
                $stmt_community_name = $buwana_conn->prepare($sql_community_name);

                if ($stmt_community_name) {
                    $stmt_community_name->bind_param('i', $community_id); // Assuming community_id is an integer
                    if ($stmt_community_name->execute()) {
                        $stmt_community_name->bind_result($community_name);
                        $stmt_community_name->fetch();
                        $stmt_community_name->close();
                    }
                }
            }
        }
    }

    // If $community_name is still empty or null, set a default value
    if (empty($community_name)) {
        $community_name = 'Unknown Community'; // Default value if no valid community name is found
    }

    return $community_name; // Return the correct variable
}

startSecureSession();
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Initialize user variables
$first_name = '';
$buwana_id = '';
$watershed_id = '';
$location_watershed = '';
$is_logged_in = isLoggedIn(); // Check if the user is logged in using the helper function



function getUserFullLocation($buwana_conn, $buwana_id) {
    $location_full = '';

    // Query to get the user's full location from the users_tb table
    $sql_location = "SELECT location_full FROM users_tb WHERE buwana_id = ?";
    $stmt_location = $buwana_conn->prepare($sql_location);

    if ($stmt_location) {
        $stmt_location->bind_param('i', $buwana_id);
        if ($stmt_location->execute()) {
            $stmt_location->bind_result($location_full);
            $stmt_location->fetch();
            $stmt_location->close();
        }
    }

    // If $location_full is still empty or null, set a default value
    if (empty($location_full)) {
        $location_full = 'Unknown Location'; // Default value if no valid location is found
    }

    return $location_full;
}



function getUserContinent($buwana_conn, $buwana_id) {
    $continent_code = '';
    $country_icon = '';

    // Query to get the user's continent_code from users_tb
    $sql_continent = "SELECT continent_code FROM users_tb WHERE buwana_id = ?";
    $stmt_continent = $buwana_conn->prepare($sql_continent);

    if ($stmt_continent) {
        $stmt_continent->bind_param('i', $buwana_id);
        if ($stmt_continent->execute()) {
            $stmt_continent->bind_result($continent_code);
            $stmt_continent->fetch();
            $stmt_continent->close();
        }
    }

    // Determine the globe emoticon based on the continent_code
    switch (strtoupper($continent_code)) {
        case 'AF':
            $country_icon = '🌍'; // Africa
            break;
        case 'EU':
            $country_icon = '🌍'; // Europe
            break;
        case 'AS':
            $country_icon = '🌏'; // Asia
            break;
        case 'NA':
        case 'SA':
            $country_icon = '🌎'; // North America, South America
            break;
        case 'AU':
        case 'OC':
            $country_icon = '🌏'; // Australia, Oceania
            break;
        case 'AN':
            $country_icon = '❄️'; // Antarctica
            break;
        default:
            $country_icon = '🌐'; // Default icon if continent is not recognized
            break;
    }

    return $country_icon;
}


// Function to fetch ecobrick data for retry
function retryEcobrick($gobrik_conn, $ecobrick_unique_id) {
    // Ensure ecobrick_unique_id is an integer
    $ecobrick_unique_id = intval($ecobrick_unique_id);

    // Fetch the ecobrick data from the database, including the status
    $sql = "SELECT ecobricker_maker, volume_ml, weight_g, sequestration_type, plastic_from, brand_name, community_id, location_full, bottom_colour, location_lat, location_long, location_watershed, country_id, status FROM tb_ecobricks WHERE ecobrick_unique_id = ?";
    $stmt = $gobrik_conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $ecobrick_unique_id);
        $stmt->execute();

        // Bind the results to variables, including status
        $stmt->bind_result(
            $ecobricker_maker, $volume_ml, $weight_g, $sequestration_type, $plastic_from,
            $brand_name, $community_id, $location_full, $bottom_colour, $location_lat, $location_long,
            $location_watershed, $country_id, $status
        );

        // Fetch the data and check the status
        if ($stmt->fetch()) {
            // If status is "authenticated", show an alert and redirect the user
            if ($status == "authenticated") {
                echo "<script>
                        alert('Ecobrick is authenticated. You cannot edit an authenticated ecobrick. Retry function skipped.');
                        window.location.href = 'log.php'; // Redirect after the user clicks OK
                      </script>";
                return;  // Quit the function early if status is "authenticated"
            }

            // Output JavaScript to populate the form if status is not "authenticated"
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('ecobricker_maker').value = '" . htmlspecialchars($ecobricker_maker, ENT_QUOTES) . "';
                    document.getElementById('volume_ml').value = '" . htmlspecialchars($volume_ml, ENT_QUOTES) . "';
                    document.getElementById('weight_g').value = '" . htmlspecialchars($weight_g, ENT_QUOTES) . "';
                    document.getElementById('sequestration_type').value = '" . htmlspecialchars($sequestration_type, ENT_QUOTES) . "';
                    document.getElementById('plastic_from').value = '" . htmlspecialchars($plastic_from, ENT_QUOTES) . "';
                    document.getElementById('brand_name').value = '" . htmlspecialchars($brand_name, ENT_QUOTES) . "';
                    document.getElementById('community_select').value = '" . htmlspecialchars($community_id, ENT_QUOTES) . "';
                    document.getElementById('location_full').value = '" . htmlspecialchars($location_full, ENT_QUOTES) . "';
                    document.getElementById('lat').value = '" . htmlspecialchars($location_lat, ENT_QUOTES) . "';
                    document.getElementById('lon').value = '" . htmlspecialchars($location_long, ENT_QUOTES) . "';
                    document.getElementById('location_watershed').value = '" . htmlspecialchars($location_watershed, ENT_QUOTES) . "';
                    document.getElementById('country_id').value = '" . htmlspecialchars($country_id, ENT_QUOTES) . "';

                    // Set the selected option for the bottom_colour dropdown
                    const bottomColorSelect = document.getElementById('bottom_colour');
                    const bottomColorValue = '" . htmlspecialchars($bottom_colour, ENT_QUOTES) . "';
                    for (let i = 0; i < bottomColorSelect.options.length; i++) {
                        if (bottomColorSelect.options[i].value === bottomColorValue) {
                            bottomColorSelect.options[i].selected = true;
                            break;
                        }
                    }
                });
            </script>";
        } else {
            echo "<script>alert('No ecobrick found with this unique ID.'); window.location.href = 'log.php';</script>";
        }

        $stmt->close();
    } else {
        error_log("Error preparing retryEcobrick statement: " . $gobrik_conn->error);
        echo "<script>alert('An error occurred while fetching the ecobrick data. Please try again later.'); window.location.href = 'log.php';</script>";
    }
}



// Function to generate or return a serial number
function setSerialNumber($gobrik_conn, $ecobrick_unique_id = null) {
    // If the ecobrick_unique_id is provided, use it directly
    if (!is_null($ecobrick_unique_id) && !empty($ecobrick_unique_id)) {
        return [
            'ecobrick_unique_id' => $ecobrick_unique_id,
            'serial_no' => $ecobrick_unique_id // In this case, use the same ID for serial_no
        ];
    }

    // If no ecobrick_unique_id, generate a new one from the database
    $query = "SELECT MAX(ecobrick_unique_id) as max_unique_id FROM tb_ecobricks";
    $result = $gobrik_conn->query($query);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return [
            'ecobrick_unique_id' => $row['max_unique_id'] + 1,
            'serial_no' => $row['max_unique_id'] + 1
        ];
    } else {
        throw new Exception('No records found in the database.');
    }
}





function getGEA_status($buwana_id) {
    // Include the database connection if not already included
    global $gobrik_conn; // Use the existing connection variable

    // Prepare the SQL statement to fetch the gea_status
    $sql = "SELECT gea_status FROM tb_ecobrickers WHERE buwana_id = ?";
    $stmt = $gobrik_conn->prepare($sql);

    // Check if the statement was prepared successfully
    if ($stmt) {
        $stmt->bind_param("i", $buwana_id);
        $stmt->execute();
        $stmt->bind_result($gea_status);
        $stmt->fetch();
        $stmt->close();

        // Return the fetched gea_status
        return $gea_status;
    } else {
        // Log error or handle it appropriately
        error_log("Database error: " . $gobrik_conn->error);
        return null;
    }
}

function getUser_Role($buwana_id) {
    // Use the existing connection variable
    global $gobrik_conn;

    // Prepare your query to select the user_role
    $sql = "SELECT user_roles FROM tb_ecobrickers WHERE buwana_id = ?";
    $stmt = $gobrik_conn->prepare($sql);

    // Check if the statement was prepared successfully
    if ($stmt) {
        $stmt->bind_param("i", $buwana_id);
        $stmt->execute();

        // Bind the result to a variable
        $stmt->bind_result($user_roles);
        $stmt->fetch();
        $stmt->close();

        // Return the fetched user_role
        return $user_roles;
    } else {
        // Log error or handle it appropriately
        error_log("Database error: " . $gobrik_conn->error);
        return null;
    }
}




function getEcobrickerID($buwana_id) {
    // Include the database connection if not already included
    global $gobrik_conn; // Use the existing connection variable

    // Prepare the SQL statement to fetch the ecobricker_id
    $sql = "SELECT ecobricker_id FROM tb_ecobrickers WHERE buwana_id = ?";
    $stmt = $gobrik_conn->prepare($sql);

    // Check if the statement was prepared successfully
    if ($stmt) {
        // Bind the buwana_id as an integer to the SQL statement
        $stmt->bind_param("i", $buwana_id);
        $stmt->execute();

        // Bind the result to the ecobricker_id variable
        $stmt->bind_result($ecobricker_id);
        $stmt->fetch();

        // Close the prepared statement
        $stmt->close();

        // Return the fetched ecobricker_id
        return $ecobricker_id;
    } else {
        // Log error or handle it appropriately
        error_log("Database error: " . $gobrik_conn->error);
        return null;
    }
}





// Function to display alert message in different languages
function getNoEcobrickAlert($lang) {
    switch ($lang) {
        case 'fr':
            return "Whoop ! Aucun ecobrick n'a pu être trouvé pour la mise à jour. Quelque chose s'est mal passé lors du processus d'enregistrement ou vous avez supprimé cet ecobrick. Essayez d'enregistrer à nouveau.";
        case 'es':
            return "¡Whoop! No se pudo encontrar un ecobrick para actualizar. Algo salió mal en el proceso de registro o eliminaste este ecobrick. Intenta registrarte nuevamente.";
        case 'id':
            return "Whoop! Tidak ada ecobrick yang dapat ditemukan untuk diperbarui. Ada yang salah dalam proses pencatatan atau Anda telah menghapus ecobrick ini. Cobalah mencatat lagi.";
        default: // English as default
            return "Whoop! No ecobrick could be found to update. Something went wrong in the logging process or you've deleted this ecobrick. Try logging again.";
    }
}




?>
