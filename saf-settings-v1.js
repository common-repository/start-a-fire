var $j = jQuery;
$j(document).ready(function($) {

	init();
	
	function init() {
		getExcludedDomains();
	}

	/* ****************
	** GETTERS, SETTERS
	** ***************/

	// code below is the same as used in extensions

	function getExcludedDomains() {
		try {
			$j.ajax({
				method: 'POST',
				url: BASE_URL + 'user/getexcludeddomains?access_token=' + USER_ACTIVE_TOKEN,
			})
			.done(function(res) {
				if (res.success) {
					var excludedDomains = res.domains;
					for (var i = 0; i < excludedDomains.length; i++) {
						var row = "<tr value=\"" + excludedDomains[i] + "\"><td>" + excludedDomains[i] + "</td><td><span class=\"dashicons dashicons-trash saf_remove_domain\"></span></td></tr>";
						$j('.saf_excludeddomains_table').append(row);
					};
				};
			});
		} catch(err) {
			console.log("-23454548 " + err);
		}
	}

	// add new excluded domain, call getExcludedDomains()
	function addExcludedDomain(domain,element,token) {
		try {
			$j.ajax({
				method: 'POST',
				url: BASE_URL + 'user/addexcludeddomains?access_token=' + token,
				data: {'domain': domain},
			})
			.done(function(res) {
				if (res.success) {
					element.val('');
					$j('.saf_excludeddomains_table').empty();
					getExcludedDomains();
				};
			});
		} catch(err) {
			console.log("-23454548 " + err);
		}
	}

	// remove existing excluded domain, call getExcludedDomains()
	function removeExcludedDomain(domain,element,token) {
		try {
			$j.ajax({
				method: 'POST',
				url: BASE_URL + 'user/deleteexcludeddomains?access_token=' + token,
				data: {'domain': domain},
			})
			.done(function(res) {
				if (res.success) {
					$j('.saf_excludeddomains_table').empty();
					getExcludedDomains();
				};
			});
		} catch(err) {
			console.log("-752347 " + err);
		}
	}

	/* ***************
	** EVENT LISTENERS
	** **************/

	$j(document).on('click', ".saf_remove_domain", function(event) {
		var row = $j(this).parent().parent();
		var domain = row.attr('value');
		removeExcludedDomain(domain,row,USER_ACTIVE_TOKEN);
	});

	$j("#saf_add_domain").click(function(event) {
		var field = $j("#saf_excluded_domains");
		var domain = field.val();
		addExcludedDomain(domain,field,USER_ACTIVE_TOKEN);
	});

});