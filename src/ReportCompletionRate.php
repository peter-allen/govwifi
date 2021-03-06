<?php

namespace Alphagov\GovWifi;

use DateInterval;
use DateTime;

/**
 * Sends the registration process completion rate details to
 * the Performance Platform.
 *
 * Data is sent weekly, for registrations between 14 and 7 days ago,
 * to allow for pre-registered users to complete the process.
 *
 * @package Alphagov\GovWifi
 */
class ReportCompletionRate extends PerformancePlatformReport {
    const REPORT_CHANNELS = [
        [
            'condition' => "(contact LIKE '+%' AND userdetails.contact = userdetails.sponsor)",
            'extras'    => [ 'channel' => 'sms' ]
        ], [
            'condition' => "(contact LIKE '%@%' AND userdetails.contact = userdetails.sponsor)",
            'extras'    => [ 'channel' => 'email' ]
        ], [
            'condition' => "(userdetails.contact != userdetails.sponsor)",
            'extras'    => [ 'channel' => 'sponsor' ]
        ]
    ];

    public function getMetricName() {
        return 'completion-rate';
    }

    public function sendMetrics($date = null) {
        $dateObject = new DateTime();
        if (! empty($date)) {
            $dateObject = new DateTime($date);
        }
        $date = $dateObject->format('Y-m-d');

        // The date object retains it's state - so these will be 7 and 14 days before the date provided.
        $endDate = $dateObject->sub(new DateInterval('P7D'))->format('Y-m-d');
        $startDate = $dateObject->sub(new DateInterval('P7D'))->format('Y-m-d');

        $defaults = [
            'timestamp'    => $date . 'T00:00:00+00:00',
            'period'       => 'week'
        ];

        foreach (self::REPORT_CHANNELS as $channel) {
            // Number of registered users for the given channel
            $this->sendSimpleMetric(array_merge($defaults, [
                'extras' => array_merge(
                    [ 'stage' => 'start' ],
                    $channel['extras']
                ),
                'sql' => "SELECT count(username) AS count FROM userdetails "
                    . "WHERE date(created_at) BETWEEN '" . $startDate . "' AND '" . $endDate . "' "
                    . "AND " . $channel['condition']
            ]));

            // Number of users successfully logged in from the list above
            $this->sendSimpleMetric(array_merge($defaults, [
                'extras' => array_merge(
                    [ 'stage' => 'complete' ],
                    $channel['extras']
                ),
                'sql' => "SELECT count(distinct(userdetails.username)) AS count FROM "
                    . "userdetails LEFT JOIN session ON (userdetails.username = session.username) "
                    . "WHERE date(userdetails.created_at) BETWEEN '" . $startDate . "' AND '" . $endDate . "' "
                    . "AND session.username IS NOT NULL AND " . $channel['condition']
            ]));
        }
    }
}
