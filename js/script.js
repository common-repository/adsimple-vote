/**
 * AdSimple-Vote — Let the users to vote with just one simple click. Create a question and get deep insights in the process. Listen your audience.
 * @encoding     UTF-8
 * @version      1.0.0
 * @copyright    Copyright (C) 2018 AdSimple (https://www.adsimple.at). All rights reserved.
 * @license      GPLv2 or later; See http://www.gnu.org/licenses/gpl-2.0.html
 * @author       Alexander Khmelnitskiy (hi@alexander.khmelnitskiy.ua)
 * @support      support@adsimple.at
 */

jQuery(document).ready(function() {
    // Read Cookie.
    var asvCookie = {};
    asvCookie = JSON.parse(readCookie("adsimplevote_data"));

    // If this is first visit
    if(asvCookie == null) {
        asvCookie = {};
        asvCookie['guid'] = getGUID(); // Mark User with GUID
        
        // Set Cookie
        createCookie("adsimplevote_data", JSON.stringify(asvCookie));
    }
    
    // Get all votes on page
    var adsimplevote = jQuery(".adsimplevote-box");
    
    // For each AdSimpleVote
    adsimplevote.each(function (i, v) {
        var f_new_vote = 1; // Is this first time vote for user?
        var f_counter_updated = 0; // After first vote, one time update counter.
        
        // ID of current vote
        var id = jQuery(this).attr("id").replace("adsimplevote-", "");

        // Initial value for tooltip
        var tooltip = jQuery('#adsimplevote-' + id + ' .vote-slider .value');
        var slider = jQuery('#adsimplevote-' + id + ' .vote-slider .range');
        tooltip.html(slider.val());
        
        // On slider draging - update and move tooltip
        jQuery('#adsimplevote-' + id + ' .vote-slider .range').on('input', function () {
            jQuery(this).next().html(this.value + " %");
            jQuery(this).next().css("margin-left", (-15 - Math.round(this.value * 0.2)) + "px"); // Tooltip correction
            jQuery(this).next().css("left", this.value + "%");
        });
        
        // On value changed.
        var vote_val = null;
        var old_vote_val = null;
        jQuery('#adsimplevote-' + id + ' .vote-slider .range').on('change', function () {
            
            // Store old vote value to decrement it on change vote.
            if(old_vote_val == null){
                old_vote_val = asvCookie["value_" + id];
            }else{
                old_vote_val = vote_val;
            }
            vote_val = this.value;
            
            // Send AJAX
            var data = {
                'action': 'process_vote',
                'vote_id': id,
                'vote_val': vote_val,
                'guid': asvCookie['guid'],
                'new_vote': f_new_vote
            };
            
            // AJAX call
            jQuery('#adsimplevote-' + id + ' .vote-slider .range').prop('disabled', true); // Disable slider
            jQuery.post(adsimplevote_ajax.url, data, function(data) {
                if(!data.status){
                    console.log("AJAX Error! See below:");
                    console.log(data);
                    
                    if(typeof data.message !== 'undefined'){
                        alert(data.message);
                    }
                }else{ // AJAX done
                    f_new_vote = 0;
                    
                    // After first vote, one time update counter.
                    if(! f_counter_updated){
                        jQuery('#adsimplevote-' + id + ' .asv-couter span b').text(parseInt(jQuery('#adsimplevote-' + id + ' .asv-couter span b').text()) + 1); 
                        f_counter_updated = 1;
                    }
                
                    // Chart refresh with my vote
                    if(vote_val != null) {
                        window["adsimplevote_data_" + id]['series'][0][old_vote_val]--; // Value - 1 user voice 
                        window["adsimplevote_data_" + id]['series'][0][vote_val]++; // Value + 1 user voice 
                        showChart(id); // Refresh result chart.
                        showAterVoteMsg(); // Show After Vote Message.
                    }
                }
            }, 'json').fail(function(data) {
                console.log("AJAX Error! See below:");
                console.log(data);
            }).always(function (){
                jQuery('#adsimplevote-' + id + ' .vote-slider .range').prop('disabled', false); // Enable slider
            });
            
            // Set Cookie
            asvCookie['value_' + id] = vote_val;
            createCookie("adsimplevote_data", JSON.stringify(asvCookie));
            
        });
        
        // Read Cookie
        asvCookie = JSON.parse(readCookie("adsimplevote_data"));
        if(asvCookie["value_" + id] != null){
            f_new_vote = 0; // Already voted in this poll.
            f_counter_updated = 1;
            
            // Set previous vote value
            jQuery('#adsimplevote-' + id + ' .vote-slider .range').val(asvCookie["value_" + id]);
            tooltip.html(slider.val());
            tooltip.css("margin-left", (-15 - Math.round(slider.val() * 0.2)) + "px"); // Tooltip correction
            tooltip.css("left", slider.val() + "%");

            // Show result chart.
            showChart(id);
            showAterVoteMsg(); // Show After Vote Message.
        }else{
            // Hide result chart
            jQuery('#adsimplevote-' + id + ' .ct-chart').hide();
            showBeforeVoteMsg(); // Show Before Vote Message.
        }

        /**
        * Show After Vote Message.
        */
        function showAterVoteMsg() {
            
            console.log("showAterVoteMsg");
            
            // Hide Before Vote Message
            jQuery('#adsimplevote-' + id + ' .asv-before-vote-msg').hide();
            
            // Show After Vote Message
            jQuery('#adsimplevote-' + id + ' .asv-after-vote-msg').show();
        }
        
        /**
        * Show Before Vote Message.
        */
        function showBeforeVoteMsg() {
            // Show Before Vote Message
            jQuery('#adsimplevote-' + id + ' .asv-before-vote-msg').show();
            
            // Hide After Vote Message
            jQuery('#adsimplevote-' + id + ' .asv-after-vote-msg').hide();
        }
        
    });
    
    /**
     * Pseudo GUID, user as unique user id
     * 
     * @returns {String}
     */
    function getGUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    /**
    * Create сookie value
    * 
    * @param {type} name
    * @param {type} value
    * @param {type} days
    * @returns {undefined}
    */
    function createCookie(name, value, days) {
       var expires = "";
       if (days) {
           var date = new Date();
           date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
           expires = "; expires=" + date.toUTCString();
       }
       document.cookie = name + "=" + value + expires + "; path=/";
    }

    /**
    * Read value from Cookie
    * 
    * @param {type} name
    * @returns {unresolved}
    */
    function readCookie(name) {
       var nameEQ = name + "=";
       var ca = document.cookie.split(';');
       for (var i = 0; i < ca.length; i++) {
           var c = ca[i];
           while (c.charAt(0) == ' ')
               c = c.substring(1, c.length);
           if (c.indexOf(nameEQ) == 0)
               return c.substring(nameEQ.length, c.length);
       }
       return null;
    }

    /**
    * Erase Cookie Value
    * 
    * @param {type} name
    * @returns {undefined}
    */
    function eraseCookie(name) {
       createCookie(name, "", -1);
    }
    
});