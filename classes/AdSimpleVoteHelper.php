<?php
class AdSimpleVoteHelper
{
    /**
     * Get plugin settings with default values.
     *
     * @return array
     */
    static public function getOptions() {
        // Default values.
        $defaults = array(
            'key_color' => '#0577e8', // KeyColor.
            'social_share_buttons' => array(), // Social Buttons.
            'before_vote_msg_header' => __('Vote!', 'adsimple-vote'), // Before Vote Message header.
            'before_vote_msg_description' => __('Drag slider and make your voice heard.', 'adsimple-vote'), // Before Vote Message description.
            'after_vote_msg_header' => __('Thanks for voting!', 'adsimple-vote'), // After Vote Message header.
            'after_vote_msg_description' => __('Get your friends to vote, share this page.', 'adsimple-vote'), // After Vote Message description.
        );

        return wp_parse_args(get_option('adsimplevote_settings'), $defaults);
    }

    /**
     * Get Vote post
     *
     * @param int $id
     * @return null|WP_Post
     */
    static public function getVote($id) {
        if (!$id) {
            return null;
        }

        $vote = get_post($id);
        if (!$vote || $vote->post_type !== ASV_POST_TYPE) {
            return null;
        }

        return $vote;
    }

    /**
     * Get votes data for the chart.
     *
     * @param $vote_id
     * @return array
     */
    static public function getVotesData($vote_id) {
        global $wpdb;

        $res = $wpdb->get_results(
            $wpdb->prepare(
                "
                    SELECT $wpdb->adsimplevote.value, COUNT($wpdb->adsimplevote.value) AS cnt
                    FROM $wpdb->adsimplevote
                    WHERE $wpdb->adsimplevote.vote_id = %d
                    GROUP BY $wpdb->adsimplevote.value
                ",
                array($vote_id)
            )
        );

        // Init data array
        $arr = array_fill(0, 100, 0);

        // Fill votes
        foreach ($res as $row) {
            $arr[$row->value] = (int)$row->cnt;
        }

        return $arr;
    }

    /**
     * Update existing vote value.
     *
     * @param $vote_id
     * @param $vote_val
     * @param $user_ip
     * @param $guid
     * @param $modified
     * @return false|int
     */
    static public function updateVote($vote_id, $vote_val, $user_ip, $guid, $modified) {
        global $wpdb;

        $res = $wpdb->update(
            $wpdb->adsimplevote,
            array('value' => $vote_val, 'ip' => $user_ip, 'modified' => $modified),
            array('vote_id' => $vote_id, 'guid' => $guid),
            array( '%d', '%s', '%s' ),
            array( '%d', '%s' )
        );

        return $res;
    }

    /**
     * Insert new vote value.
     *
     * @param $vote_id
     * @param $vote_val
     * @param $user_ip
     * @param $guid
     * @param $created
     * @param $modified
     * @return int
     */
    static public function insertVote($vote_id, $vote_val, $user_ip, $guid, $created, $modified) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->adsimplevote,
            array( 'vote_id' => $vote_id, 'value' => $vote_val, 'ip' => $user_ip, 'guid' => $guid, 'created' => $created, 'modified' => $modified),
            array( '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Users can vote 10 times from 1 IP in 24 hours.
     *
     * @param $vote_id
     * @param string $ip
     * @return bool
     */
    static public function checkVotesLimits($vote_id, $ip) {
        global $wpdb;

        $res = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $wpdb->adsimplevote WHERE ($wpdb->adsimplevote.modified > DATE_SUB(NOW(), INTERVAL 24 HOUR)) AND vote_id = %d AND ip = %s",
                array(
                    $vote_id,
                    $ip,
                )
            )
        );

        if (count($res) > 9) {
            return false;
        }

        return true;
    }

    /**
     * Return count of votes for vote.
     * @param $vote_id
     * @return int
     */
    static public function countVotes($vote_id) {
        global $wpdb;

        $res = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COUNT(id) AS cnt FROM $wpdb->adsimplevote WHERE $wpdb->adsimplevote.vote_id = %d",
                array(
                    $vote_id,
                )
            )
        );

        return (int)$res[0]->cnt;
    }

    /**
     * Get user IP
     *
     * @since 1.0.0
     * @access public
     */
    static public function getIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR']; //to check ip passed from proxy
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Add some validation
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = "UNKNOWN";
        }

        return $ip;
    }

    /**
     * Get resource suffix
     *
     * @return string
     */
    static public function getResourceSuffix() {
        $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
        return $suffix;
    }
}