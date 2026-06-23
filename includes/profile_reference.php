<?php
/**
 * Reference data for profile forms (languages + time zones), shared by the
 * profile API. The time-zone list mirrors en/edit-profile.php so the Buwana page
 * and the client-app form offer identical options.
 */

/** Supported IANA time zones, value => human label. */
function profile_timezones(): array
{
    return [
        'Etc/GMT+12' => 'Baker Island (UTC-12)',
        'Pacific/Pago_Pago' => 'Samoa (UTC-11)',
        'Pacific/Honolulu' => 'Hawaii (UTC-10)',
        'America/Anchorage' => 'Alaska (UTC-9)',
        'America/Los_Angeles' => 'Los Angeles (UTC-8)',
        'America/Denver' => 'Denver (UTC-7)',
        'America/Chicago' => 'Chicago (UTC-6)',
        'America/New_York' => 'New York (UTC-5)',
        'America/Toronto' => 'Toronto (UTC-5/UTC-4 DST)',
        'America/Halifax' => 'Halifax (UTC-4)',
        'America/Sao_Paulo' => 'São Paulo (UTC-3)',
        'Atlantic/South_Georgia' => 'South Georgia (UTC-2)',
        'Atlantic/Azores' => 'Azores (UTC-1)',
        'Etc/UTC' => 'UTC (Coordinated Universal Time)',
        'Europe/London' => 'London (UTC+0/UTC+1 DST)',
        'Europe/Berlin' => 'Berlin (UTC+1)',
        'Europe/Helsinki' => 'Helsinki (UTC+2)',
        'Europe/Moscow' => 'Moscow (UTC+3)',
        'Asia/Dubai' => 'Dubai (UTC+4)',
        'Asia/Karachi' => 'Karachi (UTC+5)',
        'Asia/Dhaka' => 'Dhaka (UTC+6)',
        'Asia/Jakarta' => 'Jakarta (UTC+7)',
        'Asia/Singapore' => 'Singapore (UTC+8)',
        'Asia/Shanghai' => 'Shanghai (UTC+8)',
        'Asia/Tokyo' => 'Tokyo (UTC+9)',
        'Australia/Sydney' => 'Sydney (UTC+10)',
        'Pacific/Guadalcanal' => 'Guadalcanal (UTC+11)',
        'Pacific/Auckland' => 'Auckland (UTC+12)',
    ];
}

/** Active languages for the language picker. */
function fetch_active_languages($buwana_conn): array
{
    $languages = [];
    $sql = "SELECT language_id, language_name_en, languages_native_name
            FROM languages_tb
            WHERE language_active = 1
            ORDER BY languages_native_name";
    if ($res = $buwana_conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $languages[] = $row;
        }
    }
    return $languages;
}
