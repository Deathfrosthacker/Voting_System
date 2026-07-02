<?php
/**
 * ============================================================
 * ELECTION TIME VALIDATION HELPER
 * ============================================================
 * Centralized election timing validation functions.
 * All files should use these functions to ensure consistent
 * 24-hour minimum duration enforcement.
 */

/**
 * Get the configured minimum election duration in seconds.
 * Defaults to 86400 seconds (24 hours) if not set.
 */
function get_minimum_election_duration($conn): int {
    $result = mysqli_query($conn, 
        "SELECT setting_value FROM election_settings 
         WHERE setting_name = 'minimum_election_duration_seconds' 
         LIMIT 1"
    );
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return (int)$row['setting_value'];
    }
    return 86400; // Default: 24 hours
}

/**
 * Validate that an election has the minimum required duration.
 * 
 * @param string $startDatetime Start datetime (Y-m-d H:i:s format)
 * @param string $endDatetime   End datetime (Y-m-d H:i:s format)
 * @param int    $minSeconds    Minimum duration in seconds
 * @return array ['valid' => bool, 'error' => string|null, 'duration_hours' => float]
 */
function validate_election_duration(string $startDatetime, string $endDatetime, int $minSeconds = 86400): array {
    $start = strtotime($startDatetime);
    $end = strtotime($endDatetime);
    
    // Basic validation
    if ($start === false || $end === false) {
        return [
            'valid' => false, 
            'error' => 'Invalid datetime format provided.',
            'duration_hours' => 0
        ];
    }
    
    $durationSeconds = $end - $start;
    $durationHours = round($durationSeconds / 3600, 1);
    
    // End must be after start
    if ($durationSeconds <= 0) {
        return [
            'valid' => false,
            'error' => 'End datetime must be after the start datetime.',
            'duration_hours' => $durationHours
        ];
    }
    
    // Check minimum duration (24 hours)
    if ($durationSeconds < $minSeconds) {
        $requiredHours = round($minSeconds / 3600, 1);
        return [
            'valid' => false,
            'error' => "Election duration ($durationHours hours) is too short. "
                     . "Minimum required duration is $requiredHours hours.",
            'duration_hours' => $durationHours
        ];
    }
    
    return [
        'valid' => true,
        'error' => null,
        'duration_hours' => $durationHours
    ];
}

/**
 * Check if an election is currently active (within voting window).
 * Uses database NOW() equivalent for accurate time comparison.
 * 
 * @param string $startDatetime Start datetime
 * @param string $endDatetime   End datetime
 * @return array ['active' => bool, 'error' => string|null]
 */
function is_election_active(string $startDatetime, string $endDatetime): array {
    $now = time();
    $start = strtotime($startDatetime);
    $end = strtotime($endDatetime);
    
    if ($start === false || $end === false) {
        return ['active' => false, 'error' => 'Invalid election datetime configuration.'];
    }
    
    if ($now < $start) {
        $diff = ceil(($start - $now) / 60);
        if ($diff < 60) {
            return ['active' => false, 'error' => "Voting starts in $diff minutes."];
        }
        $hours = ceil($diff / 60);
        if ($hours < 24) {
            return ['active' => false, 'error' => "Voting starts in $hours hours."];
        }
        $days = ceil($hours / 24);
        return ['active' => false, 'error' => "Voting starts in $days days."];
    }
    
    if ($now > $end) {
        return ['active' => false, 'error' => 'This election has ended.'];
    }
    
    return ['active' => true, 'error' => null];
}

/**
 * Format a DATETIME value for display in a user-friendly format.
 * 
 * @param string $datetime MySQL DATETIME string
 * @return string Formatted date string
 */
function format_election_datetime(string $datetime): string {
    $ts = strtotime($datetime);
    if ($ts === false) return 'Invalid Date';
    return date('M d, Y \a\t g:i A', $ts); // e.g., "Jul 02, 2026 at 9:00 AM"
}

/**
 * Format a DATETIME value for HTML datetime-local input.
 * 
 * @param string $datetime MySQL DATETIME string
 * @return string Y-m-d\TH:i formatted string
 */
function format_for_datetime_input(string $datetime): string {
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    return date('Y-m-d\TH:i', $ts);
}

/**
 * Get the current datetime formatted for datetime-local input min attribute.
 * Ensures users cannot select datetimes in the past.
 * 
 * @return string Y-m-d\TH:i formatted current datetime
 */
function get_current_datetime_for_input(): string {
    return date('Y-m-d\TH:i');
}

/**
 * Convenience function: Get default start datetime (top of next hour).
 * Provides a sensible default when creating new elections.
 * 
 * @return string Y-m-d\TH:i formatted datetime
 */
function get_default_start_datetime(): string {
    return date('Y-m-d\TH:i', strtotime('+1 hour', ceil(time() / 3600) * 3600));
}

/**
 * Convenience function: Get default end datetime (24 hours after default start).
 * Ensures the default selection already meets the minimum duration requirement.
 * 
 * @return string Y-m-d\TH:i formatted datetime
 */
function get_default_end_datetime(): string {
    return date('Y-m-d\TH:i', strtotime('+25 hours', ceil(time() / 3600) * 3600));
}