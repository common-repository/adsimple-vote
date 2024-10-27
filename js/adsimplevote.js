/**
 * AdSimple-Vote — Let the users to vote with just one simple click. Create a question and get deep insights in the process. Listen your audience.
 * @encoding     UTF-8
 * @version      1.0.0
 * @copyright    Copyright (C) 2018 AdSimple (https://www.adsimple.at). All rights reserved.
 * @license      GPLv2 or later; See http://www.gnu.org/licenses/gpl-2.0.html
 * @author       Alexander Khmelnitskiy (hi@alexander.khmelnitskiy.ua)
 * @support      support@adsimple.at
 */

(function() {
    tinymce.create('tinymce.plugins.adsimplevote_plugin', {

        // URL argument holds the absolute url of our plugin directory.
        init : function(editor, url) {

			var html = ''
				+ '<div id="insert-adsimplevote">'
					+ '<p class="">Your shortcode is: <code class="shortcode">[adsimplevote id=""]</code></p>'
					+ '<p class="help">Select one of the existing votes.</p>'
					+ '<div class="search-wrapper">'
						+ '<label>'
							+ '<span class="search-label">Search</span>'
							+ ' <input type="search" id="adsimplevote-search" class="adsimplevote-search-field" autocomplete="off">'
							+ ' <span class="spinner"></span>'
						+ '</label>'
					+ '</div>'
					+ '<div id="adsimplevote-search-results" tabindex="0">'
						+ '<ul>'
						+ '</ul>'
					+ '</div>'
				+ '</div>';

            // Add new button.
            editor.addButton('adsimplevote', {
                title: 'Insert AdSimple Vote',
                image: url + '/../images/icon.svg',
				onclick: function () {
					// Open window
					editor.windowManager.open({
						title: 'Insert Vote',
						width: 500,
						height: 400,
						body: [
							{type: 'container', html: html }
						],
						buttons: [{
							text: 'Insert Vote',
									classes: 'btn primary',
									id: 'insert-vote-btn',
									onclick: function (e) {
										
										var selected = editor.selection.getContent();
										var shorcode = jQuery("#insert-adsimplevote .shortcode").text();
										var content = "";

										if( selected ){
											//If text is selected when button is clicked
											content = selected + shorcode;
										}else{
											content = shorcode;
										}

										editor.execCommand("mceInsertContent", 0, content);
										editor.windowManager.close();
									}
							}, {
							text: 'Close',
									id: 'close-vote-btn',
									onclick: 'close'
							}],
						onOpen:  function (e) {
							// Get Votes
							// TODO: Add pagination or ajax load more
							jQuery("#adsimplevote-search-results").addClass("loader"); // Show loader
							jQuery.ajax({
								url: adsimple_data['rest_url'] + "wp/v2/adsimplevote/?filter[posts_per_page]=42",
								dataType: 'json'
							}).done(function(data) {
								jQuery("#adsimplevote-search-results ul").empty();
								jQuery.each(data, function(index, element) {
									jQuery("#adsimplevote-search-results ul").append(''
										+ '<li>'
											+ '<input class="item-vote" type="hidden" value="' + element['id'] + '">'
											+ '<span class="item-title">' + element['title']['rendered'] + '</span>'
											+ '<span class="item-info">' + (new Date(element['date']).toLocaleDateString()) + '</span>'
										+ '</li>');
								});
							})
							.fail(function(data) {
								console.log("error!");
								console.log(data);
							})
							.always(function() {
								jQuery("#adsimplevote-search-results").removeClass("loader"); // Hide loader
							});
							
							// Filter votes
							// TODO: Add ajax request to REST API
							jQuery("#adsimplevote-search").on('keyup paste', function () {
								var s = jQuery(this).val().toLowerCase(); // for case-insensitive search
								if(s.length > 1){ // If the query is longer than 2 letters we look for items.
								   jQuery("#adsimplevote-search-results ul li").each(function (index) {
									   if(~jQuery(this).find(".item-title").text().toLowerCase().indexOf(s)){
										   jQuery(this).show();
									   }else{
										   jQuery(this).hide();
									   }
								   });
								}else{
									jQuery("#adsimplevote-search-results ul li").show();
								}
							});
							
							// Select vote item
							jQuery("body").on('click', '#adsimplevote-search-results ul li', function () {
								jQuery("#adsimplevote-search-results ul li").removeClass('selected');
								jQuery(this).addClass('selected');
								jQuery("#insert-adsimplevote .shortcode").html('[adsimplevote id="' + jQuery(this).find(".item-vote").val() + '"]');
							});
							
						}
					});
					
				}
            });

        },

        createControl : function(n, cm) {
            return null;
        },

        getInfo : function() {
            return {
                longname : "AdSimple Vote",
                author : "www.AdSimple.at",
                version : "1.0.0"
            };
        }
    });

    tinymce.PluginManager.add("adsimplevote_plugin", tinymce.plugins.adsimplevote_plugin);
	
})();