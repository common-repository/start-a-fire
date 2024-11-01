jQuery(document).ready(function($) {
	$(window).load(function() {
		// wait for rich text editor to load
		if (tinyMCE.activeEditor!=null) {

			/* *********
			** FUNCTIONS
			** ********/

			function isExcludedDomain(link) {
				if (SAF_EXCLUDED_DOMAINS == null) {
					return false;
				}
				try {
					for (var i = 0; i < SAF_EXCLUDED_DOMAINS.length; i++) {
						if (link.indexOf(SAF_EXCLUDED_DOMAINS[i]) > -1) {
							return true;
						}
					}
					return false;
				} catch(e) {
					console.log("-725423" + e);
					return false;
				}
			}

			function fireToggleButtonsActions() {
				var node = tinymce.activeEditor.selection.getNode();
				// WP always adds <a> tag as parent, thus only checking for parents
				var parentTag = $(node).closest('a');
				try {
					var link = $(node).closest('a').attr('href');
					if ((link.indexOf(SAF_URL_SHORTENER) > -1 || parentTag.hasClass('saf_no_replace') || !(parentTag.hasClass('saf_no_replace'))) && !isExcludedDomain(link)) {
						addLinkToggleButtons();
					} else {
						removeLinkToggleButtons();
					}
				} catch(e) {
					console.log("-32187" + e);
				}
			}

			try {
				// WP version 4.0
				var previewMainWindow = $("#wp-link").find("#url-field");
				// WP versions 4.2 +
				if (previewMainWindow.length === 0) {
					previewMainWindow = $("#wp-link").find("#wp-link-url");
				}
			} catch(e) {
				console.log("-988821" + e);
			}

			function addLinkToggleButtons() {
				removeLinkToggleButtons();

				var node = tinymce.activeEditor.selection.getNode();
				var parentTag = $(node).closest('a');
				var currentLink = parentTag.hasClass('saf_no_replace');
				var previewParent = $(".wp-link-preview");

				try {
					if (currentLink) {
						if (previewMainWindow.length !== 0) {
							previewMainWindow.after("<span class='saf_inserted_fire saf_inserted_fire_link_window'><div tabindex='-1' class='mce-widget mce-btn saf_make_fire_container_link_window' ><button id='toggleFireButtonLinkWindow' role='presentation' type='button' tabindex='-1' class='saf_make_fire saf_gray_fire saf_tooltip' ><span class='tooltiptext'>Enable Start A Fire</span></button></div></span>");
						}
						$(previewParent).after("<span class='saf_inserted_fire'><div tabindex='-1' class='mce-widget mce-btn' ><button id='toggleFireButton' role='presentation' type='button' tabindex='-1' class='saf_make_fire saf_gray_fire saf_tooltip' ><span class='tooltiptext saf_tooltip_small'>Add your badge to this link</span></button></div></span>");
						$("#toggleFireButton, #toggleFireButtonLinkWindow").click(function(event) {
							parentTag.removeClass('saf_no_replace');
							parentTag.addClass('saf_do_replace');
							showBombaBox("Start A Fire badge will be added to this link right after you save this post",100000000,2,'error');
							addLinkToggleButtons();
						});
					} else {
						if (previewMainWindow.length !== 0) {
							previewMainWindow.after("<span class='saf_inserted_fire saf_inserted_fire_link_window'><div tabindex='-1' class='mce-widget mce-btn saf_make_fire_container_link_window' ><button id='toggleFireButtonLinkWindow' role='presentation' type='button' tabindex='-1' class='saf_make_fire saf_blue_fire saf_tooltip' ><span class='tooltiptext'>Disable Start A Fire</span></button></div></span>");
						}
						$(previewParent).after("<span class='saf_inserted_fire'><div tabindex='-1' class='mce-widget mce-btn' ><button id='toggleFireButton' role='presentation' type='button' tabindex='-1' class='saf_make_fire saf_blue_fire saf_tooltip' ><span class='tooltiptext'>Remove your badge from this link</span></button></div></span>");
						$("#toggleFireButton, #toggleFireButtonLinkWindow").click(function(event) {
							parentTag.removeClass('saf_do_replace');
							parentTag.addClass('saf_no_replace');
							showBombaBox("Start A Fire badge will be removed from this link right after you save this post",100000000,2,'error');
							addLinkToggleButtons();
						});
					}
				} catch(e) {
					console.log("-4213289" + e);
				}

			}

			function showBombaBox(text, z, t, msg_type) {
				/*	z = z-index
					t = top 
				*/ 
				if(!msg_type) msg_type = "";
				// if bomba box is already opened, remove it to display the new one
				if ($('#bomba').length) $('#bomba').remove();
				// show new bomba box
				$('body').append('<div id="bomba" class="' + msg_type + '"><div class="bomba_container">'+text+'</div></div>');
				if(z) $('#bomba').css("z-index",z);
				if(t) $('#bomba').css("top",t);
				$('#bomba').fadeIn(500).delay(3500).fadeOut(500,function() {
					$(this).remove();
				});	
			}

			function removeLinkToggleButtons() {
				$('.saf_inserted_fire').remove();
			}

			/* *********
			** LISTENERS
			** ********/

			tinymce.activeEditor.on('keydown', function(e) {
				fireToggleButtonsActions();
			});
			tinymce.activeEditor.on('click', function(e) {
				e.preventDefault();
				fireToggleButtonsActions();
			});
		}
	});
});