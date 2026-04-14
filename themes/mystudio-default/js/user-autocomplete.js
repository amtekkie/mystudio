/**
 * Lightweight user autocomplete replacement for Select2.
 * Supports single-select and multi-select (comma-separated) modes.
 *
 * Usage (in external JS — $ is safe here):
 *   UserAutoComplete('inputId', { multiple: false });
 *   UserAutoComplete('inputId', { multiple: true, maxSelection: 5 });
 */
(function($) {
	'use strict';

	window.UserAutoComplete = function(inputId, opts) {
		opts = $.extend({
			multiple: false,
			maxSelection: 0,
			url: 'xmlhttp.php?action=get_users',
			minLength: 2,
			debounce: 250
		}, opts);

		var inp = $('#' + inputId);
		if (!inp.length) return;

		inp.attr('autocomplete', 'off');

		// Ensure parent is position-relative for dropdown positioning
		var wrap = inp.parent();
		if (wrap.css('position') === 'static') {
			wrap.css('position', 'relative');
		}

		// Create results dropdown
		var menu = $('<div class="dropdown-menu w-100" style="max-height:200px;overflow-y:auto;z-index:1055;"></div>');
		inp.after(menu);

		var timer;

		function doSearch(query, onResults) {
			$.getJSON(opts.url, { query: query }, function(data) {
				menu.empty();
				if (!data || !data.length) { menu.removeClass('show'); return; }
				onResults(data);
				menu.addClass('show');
			});
		}

		if (!opts.multiple) {
			// Single-select mode
			inp.on('input', function() {
				clearTimeout(timer);
				var q = $.trim(inp.val());
				if (q.length < opts.minLength) { menu.removeClass('show').empty(); return; }
				timer = setTimeout(function() {
					doSearch(q, function(data) {
						$.each(data, function(i, u) {
							$('<button type="button" class="dropdown-item"></button>')
								.text(u.text || u.id)
								.on('mousedown', function(e) {
									e.preventDefault();
									inp.val(u.text || u.id);
									menu.removeClass('show').empty();
								})
								.appendTo(menu);
						});
					});
				}, opts.debounce);
			});
		} else {
			// Multi-select mode — comma-separated values
			inp.on('input', function() {
				clearTimeout(timer);
				var full = inp.val();
				var parts = full.split(',');
				var current = $.trim(parts[parts.length - 1]);
				if (current.length < opts.minLength) { menu.removeClass('show').empty(); return; }
				timer = setTimeout(function() {
					doSearch(current, function(data) {
						$.each(data, function(i, u) {
							$('<button type="button" class="dropdown-item"></button>')
								.text(u.text || u.id)
								.on('mousedown', function(e) {
									e.preventDefault();
									parts[parts.length - 1] = (u.text || u.id);
									var joined = $.map(parts, function(v) { return $.trim(v); }).filter(function(v) { return v; });
									if (opts.maxSelection > 0 && joined.length > opts.maxSelection) {
										joined = joined.slice(0, opts.maxSelection);
									}
									inp.val(joined.join(', ') + ', ');
									menu.removeClass('show').empty();
									inp.focus();
								})
								.appendTo(menu);
						});
					});
				}, opts.debounce);
			});
		}

		inp.on('blur', function() {
			setTimeout(function() { menu.removeClass('show'); }, 150);
		});
	};
})(jQuery);
