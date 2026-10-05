/**
 * WPCalibrate Robots Repair Admin JavaScript.
 *
 * @package WPCalibrate\RobotsRepair
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		// 1. Copy to Clipboard Handler.
		$('.wpcrr-btn-copy').on('click', function (e) {
			e.preventDefault();
			var targetSelector = $(this).data('target');
			var $target = $(targetSelector);

			if ($target.length) {
				var content = $target.val() || $target.text();
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(content).then(function () {
						alert(wpcrrData.strings.copySuccess);
					});
				} else {
					$target.select();
					document.execCommand('copy');
					alert(wpcrrData.strings.copySuccess);
				}
			}
		});

		// 2. Editor Live Syntax and Danger Checker.
		var $editor = $('#wpcrr-editor-textarea');
		var $warningBox = $('#wpcrr-live-warning');
		var $dangerConfirmRow = $('#wpcrr-danger-confirm-row');
		var $syntaxHint = $('#wpcrr-syntax-hint');

		function evaluateEditorRules() {
			if (!$editor.length) return;

			var text = $editor.val();
			var lines = text.split(/\r?\n/);
			var hasGlobalDisallow = false;
			var inWildcardUA = false;
			var syntaxIssues = 0;

			for (var i = 0; i < lines.length; i++) {
				var line = lines[i].trim();
				if (line === '' || line.startsWith('#')) continue;

				var colonIdx = line.indexOf(':');
				if (colonIdx === -1) {
					syntaxIssues++;
					continue;
				}

				var dir = line.substring(0, colonIdx).trim().toLowerCase();
				var val = line.substring(colonIdx + 1).trim();

				if (dir === 'user-agent') {
					inWildcardUA = (val === '*');
				} else if (inWildcardUA && dir === 'disallow' && val === '/') {
					hasGlobalDisallow = true;
				}
			}

			if (hasGlobalDisallow) {
				$warningBox.show();
				$dangerConfirmRow.show();
				$syntaxHint.html('<span style="color: #d62020; font-weight: bold;">Warning: Site-wide crawl block (Disallow: /) present!</span>');
			} else {
				$warningBox.hide();
				$dangerConfirmRow.hide();
				if (syntaxIssues > 0) {
					$syntaxHint.html('<span style="color: #b35900;">' + syntaxIssues + ' line(s) missing colon delimiter.</span>');
				} else {
					$syntaxHint.text('All directives look structurally valid.');
				}
			}
		}

		if ($editor.length) {
			$editor.on('input keyup change', evaluateEditorRules);
			evaluateEditorRules(); // Initial check.
		}

		// 3. Preset Templates Loader.
		$('.wpcrr-preset-btn').on('click', function (e) {
			e.preventDefault();
			var presetContent = $(this).data('preset');
			if ($editor.length && presetContent) {
				$editor.val(presetContent);
				evaluateEditorRules();
			}
		});

		// Reset to original.
		$('.wpcrr-btn-reset-rules').on('click', function (e) {
			e.preventDefault();
			var original = $(this).data('original');
			if ($editor.length && original) {
				$editor.val(original);
				evaluateEditorRules();
			}
		});

		// 4. Modal Viewer for Backups.
		$('.wpcrr-btn-view-backup').on('click', function (e) {
			e.preventDefault();
			var content = $(this).data('content');
			var title = $(this).data('title');

			$('#wpcrr-modal-title').text('Backup: ' + title);
			$('#wpcrr-modal-content').text(content);
			$('#wpcrr-backup-modal').fadeIn(150);
		});

		$('.wpcrr-modal-close, .wpcrr-modal-backdrop').on('click', function (e) {
			if (e.target === this || $(this).hasClass('wpcrr-modal-close')) {
				$('#wpcrr-backup-modal').fadeOut(150);
			}
		});

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $('#wpcrr-backup-modal').is(':visible')) {
				$('#wpcrr-backup-modal').fadeOut(150);
			}
		});
	});
})(jQuery);
