/**
 * WordPress 7 Command Palette and integration settings.
 *
 * @package Functionalities
 */

(function (wp) {
	'use strict';

	if (!wp || !wp.data || !wp.i18n || !wp.apiFetch) {
		return;
	}

	var config = window.functionalitiesWp7 || {};
	var __ = wp.i18n.__;

	function notify(status, message) {
		try {
			wp.data.dispatch('core/notices').createNotice(status, message, {
				type: 'snackbar'
			});
		} catch (error) {
			// A missing notice store must not block the requested action.
		}
	}

	function runAbility(name, input) {
		return wp.apiFetch({
			path: config.abilitiesPath + 'functionalities/' + name + '/run',
			method: 'POST',
			data: { input: input || null }
		});
	}

	function registerCommands() {
		if (!wp.commands || !wp.commands.store) {
			return;
		}

		var register = wp.data.dispatch(wp.commands.store).registerCommand;
		[
			{
				name: 'functionalities/open-dashboard',
				label: __('Functionalities: Open dashboard', 'functionalities'),
				category: 'view',
				callback: function () { window.location.href = config.urls.dashboard; }
			},
			{
				name: 'functionalities/manage-redirects',
				label: __('Functionalities: Manage redirects', 'functionalities'),
				category: 'view',
				callback: function () { window.location.href = config.urls.redirects; }
			},
			{
				name: 'functionalities/manage-tasks',
				label: __('Functionalities: Manage tasks', 'functionalities'),
				category: 'view',
				callback: function () { window.location.href = config.urls.tasks; }
			},
			{
				name: 'functionalities/manage-svg-icons',
				label: __('Functionalities: Manage SVG icons', 'functionalities'),
				category: 'view',
				callback: function () { window.location.href = config.urls.svgIcons; }
			},
			{
				name: 'functionalities/scan-assumptions',
				label: __('Functionalities: Scan site assumptions', 'functionalities'),
				category: 'action',
				callback: function () {
					runAbility('scan-assumptions').then(function () {
						notify('success', __('Assumption scan completed.', 'functionalities'));
					}).catch(function (error) {
						notify('error', error.message || String(error));
					});
				}
			}
		].forEach(function (command) {
			register(command);
		});
	}

	function bindSettings() {
		var toggle = document.querySelector('[data-functionalities-ai-toggle]');
		if (!toggle) {
			return;
		}
		var status = document.querySelector('[data-functionalities-wp7-status]');
		toggle.addEventListener('change', function () {
			toggle.disabled = true;
			wp.apiFetch({
				path: config.settingsPath,
				method: 'POST',
				data: { ai_explanations: toggle.checked }
			}).then(function () {
				status.textContent = config.i18n.saved;
			}).catch(function (error) {
				toggle.checked = !toggle.checked;
				status.textContent = error.message || String(error);
			}).finally(function () {
				toggle.disabled = false;
			});
		});
	}

	wp.domReady(function () {
		registerCommands();
		bindSettings();
	});
})(window.wp);
