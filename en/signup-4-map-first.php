<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

function build_login_url($base, array $params) {
    $delimiter = (strpos($base, '?') !== false) ? '&' : '?';
    return $base . $delimiter . http_build_query($params);
}

// Page setup
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'signup-4';
$version = '0.776';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));
$location_full = "";

// Already logged in?
if (!empty($_SESSION['buwana_id'])) {
    $redirect_url = $_SESSION['redirect_url'] ?? $app_info['app_url'] ?? '/';
    echo "<script>
        alert('Looks like you’re already logged in! Redirecting to your dashboard...');
        window.location.href = '$redirect_url';
    </script>";
    exit();
}

// 🧩 Validate buwana_id
$buwana_id = $_GET['id'] ?? null;
if (!$buwana_id || !is_numeric($buwana_id)) {
    die("⚠️ Invalid or missing Buwana ID.");
}

// 🧠 PART 1: Fetch user info
$first_name = 'User';
$location_full = '';
$location_watershed = '';
$latitude = '';
$longitude = '';
$emoji_icon = null; // Added variable

$sql = "SELECT first_name, location_full, location_watershed, location_lat, location_long, earthling_emoji FROM users_tb WHERE buwana_id = ?";
$stmt = $buwana_conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $buwana_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $location_full, $location_watershed, $latitude, $longitude, $earthling_emoji);
    $stmt->fetch();
    $stmt->close();
}

// ✅ Check if signup is already completed
if (!is_null($earthling_emoji) && trim($earthling_emoji) !== '') {
    // Redirect because signup is already done
    $login_url = build_login_url($app_info['app_login_url'], [
        'lang' => $lang,
        'id'   => $buwana_id
    ]);
    echo "<script>
        alert('Whoops! Looks like you’ve already completed your signup. No need to return to this page! Please login to your " . htmlspecialchars($app_info['app_display_name']) . " account.');
        window.location.href = '$login_url';
    </script>";
    exit();
}



// 🌍 Fetch countries
$countries = [];
$sql_countries = "SELECT country_id, country_name FROM countries_tb ORDER BY country_name ASC";
$result_countries = $buwana_conn->query($sql_countries);
if ($result_countries && $result_countries->num_rows > 0) {
    while ($row = $result_countries->fetch_assoc()) {
        $countries[] = $row;
    }
}

// 🗣️ Fetch languages
$languages = [];
$sql_languages = "SELECT language_id, languages_native_name FROM languages_tb ORDER BY languages_native_name ASC";
$result_languages = $buwana_conn->query($sql_languages);
if ($result_languages && $result_languages->num_rows > 0) {
    while ($row = $result_languages->fetch_assoc()) {
        $languages[] = $row;
    }
}
?>






<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" integrity="sha512-puJW3E/qXDqYp9IfhAI54BJEaWIfloJ7JWs7OeD5i6ruC9JZL1gERT1wjtwXFlh7CjE7ZJ+/vcRZRkIYIb6p4g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>


    <?php require_once ("../includes/signup-4-inc.php"); ?>



<!-- PAGE CONTENT -->
   <?php
   $page_key = str_replace('-', '_', $page); // e.g. 'signup-1' → 'signup_1'
   ?>

   <div id="top-page-image"
        class="top-page-image"
        data-light-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_light']) ?>"
        data-dark-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_dark']) ?>">
   </div>


    <div id="form-submission-box" class="landing-page-form">
        <div class="form-container" style="box-shadow: #0000001f 0px 5px 20px;">

            <div style="text-align:center;width:100%;margin:auto;">
                <p style="color:green;" data-lang-id="001-email-confirmed">✔ Your email is confirmed!</p>
                <div id="status-message" style="font-family: 'Arvo';margin-top:15px;"><span data-lang-id="002-now"> Now</span> <?php echo htmlspecialchars($first_name); ?><span data-lang-id="003-now-localize-you"> let's get you localized.</div>
                <div id="sub-status-message" data-lang-id="004-lets-determine-bioregion" style="font-size:1.3em;padding-top:10px;padding-bottom:10px;">
                    Let's determine your bioregion: the watershed where you live.
                </div>
            </div>

            <!-- ACTIVATE 3 FORM -->
            <form id="user-signup-form" method="post" action="signup-4_process.php?id=<?php echo htmlspecialchars($buwana_id); ?>">

                <!-- LOCATION FULL -->
                <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
                    <input type="text" id="location_full" name="location_full" aria-label="Location Full" required style="padding-left:45px;" value="<?= htmlspecialchars((string)$location_full, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="location_full" data-lang-id="005-your-neighbourhood" style="border-radius:10px 10px 0px 0px;padding-bottom: 10px;"  placeholder=" "  required >Your neighbourhood...</label>
                    <div id="loading-spinner" class="spinner" style="display: none;"></div>
                    <div id="location-pin" class="pin-icon">🏡</div>
                    <p class="form-caption" data-lang-id="006-start-typing-neighbourhood">
                        Start typing the name of your neighbourhood, and <a href="https://openstreetmap.org.org" target="_blank">openstreetmaps.org</a> will fill in the rest.
                    </p>
                    <div id="location-error-required" class="form-field-error" data-lang-id="000-field-required-error">
                        This field is required.
                    </div>
                </div>

                <!-- Hidden Coordinates -->
                <input type="hidden" id="lat" name="latitude" value="<?= htmlspecialchars((string)$latitude) ?>">
                <input type="hidden" id="lon" name="longitude" value="<?= htmlspecialchars((string)$longitude) ?>">

                <!-- MAP AND WATERSHED SEARCH SECTION -->
                <div class="form-item" id="watershed-map-section" style="display: none; margin-top:20px;">
                    <label for="watershed_select" data-lang-id="007-in-which-river-basin">In which river basin do you live?</label><br>
                    <div id="map" style="height: 350px; border-radius: 0px 0px 12px 12px; margin-top: 8px;"></div>
                    <p class="form-caption" data-lang-id="008-the map shows" style="margin-top:10px;">
                        ℹ️ The map shows rivers and streams around you. Choose the one to which your water flows.
                    </p>
                    <select id="watershed_select" name="watershed_select" aria-label="Watershed Select" style="width: 100%; padding: 10px;" required>
                        <option value="" disabled <?= empty($location_watershed) ? 'selected' : '' ?> data-lang-id="010-select-your-river">👉 Select your local river...</option>
                        <?php if (!empty($location_watershed)) : ?>
                            <option value="<?= htmlspecialchars($location_watershed) ?>" selected><?= htmlspecialchars($location_watershed) ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Kick-Ass Submit Button -->
                <div id="submit-section" style="display:none;" class="submit-button-wrapper">
                    <p style="margin-bottom:25px;" data-lang-id="011-non-political">
                        Yes!  We use <a href="#" onclick="openAboutRiverBasins();" class="underline-link">watershed bioregions</a> as an alternative non-politcal, grounded way to localize our users.
                    </p>

                    <button type="submit" id="submit-button" class="kick-ass-submit">
                        <span id="submit-button-text" data-lang-id="012-next-button">Next ➡</span>
                        <span id="submit-emoji" class="submit-emoji" style="display: none;"></span>
                    </button>
                </div>

            </form>

        </div>

        <div id="browser-back-link" style="font-size: medium; text-align: center; margin: auto; align-self: center; padding-top: 40px; padding-bottom: 40px; margin-top: 0px;">
            <p style="font-size: medium;">
                <a href="#" onclick="browserBack(event)" data-lang-id="000-go-back">↩ Go back one step</a>
            </p>
        </div>
    </div>

</div>


<!-- FOOTER STARTS HERE -->
<?php require_once ("../footer-2025.php"); ?>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const locationField = document.getElementById('location_full');
    const watershedSection = document.getElementById('watershed-map-section');
    const submitSection = document.getElementById('submit-section');

    if (locationField && locationField.value.trim() !== '') {
        watershedSection.style.display = 'block';
        if (submitSection) {
            submitSection.style.display = 'block';
        }
    }
});
</script>


<script>





//FUnctions to access the openstreetmaps api and to populate the local area field and watershed field.
$(function () {
    let debounceTimer;
    let map, userMarker;
    let riverLayerGroup = L.layerGroup();

    // --- SECTION 1: Show/hide pin icon based on input value and loading state ---
    // This function manages the visibility of the location pin based on whether
    // the input field is empty or loading
    function updatePinIconVisibility() {
        if ($("#location_full").val().trim() === "" || $("#loading-spinner").is(":hidden")) {
            $("#location-pin").show();
        } else {
            $("#location-pin").hide();
        }
    }

    // --- SECTION 2: Initialize autocomplete for location search using OpenStreetMap Nominatim API ---
    // This section uses jQuery UI Autocomplete to fetch location suggestions from the OpenStreetMap Nominatim API.
    // It debounces the search query and sends a request to the API, returning location results.
    $("#location_full").autocomplete({
        source: function (request, response) {
            $("#loading-spinner").show();
            $("#location-pin").hide(); // Hide the pin icon when typing starts

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                $.ajax({
                    url: "https://nominatim.openstreetmap.org/search",
                    dataType: "json",
                    headers: {
                        'User-Agent': 'ecobricks.org'
                    },
                    data: {
                        q: request.term,
                        format: "json"
                    },
                    success: function (data) {
                        $("#loading-spinner").hide();
                        updatePinIconVisibility(); // Show the pin when data has loaded

                        // Map the returned data to an array of display_name, lat, and lon
                        response($.map(data, function (item) {
                            return {
                                label: item.display_name,
                                value: item.display_name,
                                lat: item.lat,
                                lon: item.lon
                            };
                        }));
                    },
                    error: function (xhr, status, error) {
                        $("#loading-spinner").hide();
                        updatePinIconVisibility(); // Show the pin when an error occurs
                        console.error("Autocomplete error:", error);
                        response([]);
                    }


                });
            }, 300);
        },
        select: function (event, ui) {
            // When a location is selected, the lat/lon values are populated and
            // the map/watershed sections are displayed.
            console.log('Selected location:', ui.item);
            $('#lat').val(ui.item.lat);
            $('#lon').val(ui.item.lon);

            initializeMap(ui.item.lat, ui.item.lon); // Initialize the map
            $('#watershed-map-section').fadeIn(); // Show the watershed map section
            $('#community-section').fadeIn(); // Show the community section
            showSubmitButton(); // Display the submit button

            updatePinIconVisibility(); // Show pin icon after selection
        },
        minLength: 3
    });

    // Update pin icon visibility when the user types in the location input field
    $("#location_full").on("input", function () {
        updatePinIconVisibility();
    });

    // --- SECTION 3: Show the submit button and set the height of the main div ---
    // This function fades in the submit button and adjusts the height of the `#main` div
    function showSubmitButton() {
        $('#submit-section').fadeIn();

        // Set the height of the main div to 1500px when the submit button is shown
        $('#main').css('height', '1500px');
    }

    // --- SECTION 4: Initialize the map using Leaflet and display user location ---
    // This section initializes a Leaflet map, centered on the selected latitude and longitude.
    // It also adds a marker for the user's selected location and loads nearby rivers.
    function initializeMap(lat, lon) {
        if (map) {
            map.remove(); // Remove the previous map instance if it exists
        }
        map = L.map('map', { preferCanvas: true }).setView([lat, lon], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Add a marker to the map to show the user's selected location
        userMarker = L.marker([lat, lon]).addTo(map).bindPopup("Your Location").openPopup();

        // Fix map display issue if loaded in a hidden or resized container
        setTimeout(() => {
            map.invalidateSize(); // Ensure the map resizes correctly
        }, 200);

        // Fetch nearby rivers using Overpass API
        fetchNearbyRivers(lat, lon);
    }


function fetchNearbyRivers(lat, lon) {
    riverLayerGroup.clearLayers(); // Clear previous rivers from the map
    const lang = window.currentLanguage || 'en'; // Use JS dynamic language
    let translations = {};

    // Select translations based on language
    switch (lang) {
        case 'fr':
            translations = fr_Page_Translations;
            break;
        case 'es':
            translations = es_Page_Translations;
            break;
        case 'id':
            translations = id_Page_Translations;
            break;
        case 'ar':
            translations = ar_Page_Translations;
            break;
        case 'zh':
            translations = zh_Page_Translations;
            break;
        case 'de':
            translations = de_Page_Translations;
            break;
        default:
            translations = en_Page_Translations;
    }

    // Clear and set the placeholder option using translation
    $("#watershed_select").empty().append(
        $('<option>', {
            value: "",
            disabled: true,
            selected: true,
            text: translations["010-select-your-river"] || "👉 Select your local river....",
            'data-lang-id': "010-select-your-river"
        })
    );

    const overpassUrl = `https://overpass-api.de/api/interpreter?data=[out:json];(way["waterway"="river"](around:5000,${lat},${lon});relation["waterway"="river"](around:5000,${lat},${lon}););out geom;`;

    $.get(overpassUrl, function (data) {
        let rivers = data.elements;
        let uniqueRivers = new Set();

        rivers.forEach((river) => {
            let riverName = river.tags.name;
            if (riverName && !uniqueRivers.has(riverName) && !riverName.toLowerCase().includes("unnamed")) {
                uniqueRivers.add(riverName);

                let coordinates = river.geometry.map(point => [point.lat, point.lon]);
                let riverPolyline = L.polyline(coordinates, { color: 'blue' }).addTo(riverLayerGroup).bindPopup(riverName);
                riverLayerGroup.addTo(map);

                $("#watershed_select").append(new Option(riverName, riverName));
            }
        });

        if (uniqueRivers.size === 0) {
            $("#watershed_select").append(
                $('<option>', {
                    value: "",
                    disabled: true,
                    text: translations["011b-no-rivers-found"] || "No rivers or watersheds found nearby",
                    'data-lang-id': "011b-no-rivers-found"
                })
            );
        }

        // Add special fixed options (translated)
        $("#watershed_select").append(
            $('<option>', {
                value: "watershed unknown",
                text: translations['011c-unknown'] || "I don't know",
                'data-lang-id': "011c-unknown"
            })
        );
        $("#watershed_select").append(
            $('<option>', {
                value: "watershed unseen",
                text: translations['011d-unseen'] || "I don't see my river",
                'data-lang-id': "011d-unseen"
            })
        );
        $("#watershed_select").append(
            $('<option>', {
                value: "no watershed",
                text: translations['011e-no-watershed'] || "No watershed",
                'data-lang-id': "011e-no-watershed"
            })
        );

        // Re-run switchLanguage to apply ARIA/placeholder translations on new items
        if (window.currentLanguage) {
            switchLanguage(window.currentLanguage);
        }

    }).fail(function () {
        console.error("Failed to fetch data from Overpass API.");
        $("#watershed_select").append(
            $('<option>', {
                value: "",
                disabled: true,
                text: translations["011f-fetch-error"] || "Error fetching rivers",
                'data-lang-id': "011f-fetch-error"
            })
        );

        // Ensure translation applied to error
        if (window.currentLanguage) {
            switchLanguage(window.currentLanguage);
        }
    });
}



    // --- SECTION 6: Form submission handling ---
    // This section logs the latitude and longitude when the form is submitted.
//     $('#user-info-form').on('submit', function () {
//         console.log('Latitude:', $('#lat').val());
//         console.log('Longitude:', $('#lon').val());
//         // Additional submit handling if needed
//     });
});


function openAboutRiverBasins() {
    const content = `
        <div style="text-align: center;margin:auto;padding:10%;">
            <div class="bioregions-top" style="width:100%; height:155px; margin:auto auto -10px auto"></div>
            <h2 data-lang-id="013-watershed-title">Watersheds</h2>
            <p data-lang-id="014-watershed-description">A watershed is an area defined by the drainage of rain, melting snow, or ice converging to a single point, typically a river, lake, or ocean. These basins form natural boundaried bioregions, usually demarked by the crests of hills or mountains. Watersheds play a crucial ecological role and provide water for human use.</p>
            <h2>💦</h2>
        </div>
    `;
    openModal(content);
}

function openAboutOSM() {
    const content = `
        <div style="text-align: center;margin:auto;padding:10%;">
            <h2>OpenStreetMap.org</h2>
            <p data-lang-id="015-osm-description">We make a point of not using Google maps and instead use OpenStreetMap (a foundation not a for-profit corporation) for localizing users. OpenStreetMap is built by a community of mappers that contribute and maintain data about roads, rivers, trails, and much more, all over the world. OpenStreetMap is open-data and open-source: anyone is free to use it for any purpose as long as you credit OpenStreetMap and its contributors.</p>
            <p><a href="https://www.openstreetmap.org/about" target="_blank">↗ openstreetmap.org/about</a></p>
        </div>
    `;
    openModal(content);
}


</script>





<?php require_once ("../scripts/app_modals.php");?>




</body>
</html>
