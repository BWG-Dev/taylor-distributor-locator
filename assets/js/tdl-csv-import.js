/**
 * Taylor Distributor Locator — CSV Import (M4)
 */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		initCsvImport();
		initMigration();
	} );

	// ── CSV import ────────────────────────────────────────────────────────────

	function initCsvImport() {
		const $form     = $( '#tdl-csv-import-form' );
		const $progress = $( '#tdl-import-progress' );
		const $fill     = $( '.tdl-progress-fill' );
		const $text     = $( '.tdl-progress-text' );
		const $result   = $( '#tdl-import-result' );
		const $btn      = $( '#tdl-import-btn' );
		const i18n      = tdlCsvImport.i18n;

		if ( ! $form.length ) return;

		$form.on( 'submit', function ( e ) {
			e.preventDefault();

			const file = $( '#csv_file' )[ 0 ].files[ 0 ];
			if ( ! file ) {
				showFatal( 'Please select a CSV file.' );
				return;
			}

			const formData = new FormData();
			formData.append( 'action', 'tdl_import_csv' );
			formData.append( 'nonce', tdlCsvImport.nonce );
			formData.append( 'csv_file', file );

			$btn.prop( 'disabled', true );
			$result.hide().removeClass( 'tdl-fatal' ).empty();
			$progress.show();
			$fill.css( 'width', '0%' );
			$text.text( i18n.uploading );

			$.ajax( {
				url:         tdlCsvImport.ajaxUrl,
				method:      'POST',
				data:        formData,
				processData: false,
				contentType: false,

				xhr: function () {
					const xhr = new window.XMLHttpRequest();
					xhr.upload.addEventListener( 'progress', function ( evt ) {
						if ( evt.lengthComputable ) {
							// Upload phase: 0–60%. Processing phase: 60–100%.
							const pct = Math.round( ( evt.loaded / evt.total ) * 60 );
							$fill.css( 'width', pct + '%' );
							$text.text( i18n.uploading );
						}
					} );
					return xhr;
				},

				beforeSend: function () {
					$fill.css( 'width', '60%' );
					$text.text( i18n.processing );
				},

				success: function ( response ) {
					$fill.css( 'width', '100%' );

					if ( response.success ) {
						renderResult( response.data );
					} else {
						showFatal( response.data && response.data.message
							? response.data.message
							: i18n.error );
					}
				},

				error: function () {
					$fill.css( 'width', '100%' );
					showFatal( i18n.error );
				},

				complete: function () {
					$btn.prop( 'disabled', false );
					setTimeout( function () {
						$progress.fadeOut();
					}, 800 );
				},
			} );
		} );

		// ── Result rendering ──────────────────────────────────────────────────

		/**
		 * Render stat boxes, created/updated name logs, and the per-row warning table.
		 * @param {{ stats: object, created_names: string[], updated_names: string[], row_errors: Array }} data
		 */
		function renderResult( data ) {
			const stats        = data.stats         || {};
			const createdNames = data.created_names || [];
			const updatedNames = data.updated_names || [];
			const rowErrors    = data.row_errors    || [];

			let html = '<div class="tdl-import-stats">';
			html += statBox( stats.created || 0, i18n.created, 'created' );
			html += statBox( stats.updated || 0, i18n.updated, 'updated' );
			html += statBox( stats.skipped || 0, i18n.skipped, 'skipped' );
			html += statBox( stats.errors  || 0, i18n.errors,  'errors' );
			html += '</div>';

			if ( createdNames.length ) {
				html += nameLog( i18n.created + ' (' + createdNames.length + ')', createdNames, 'created' );
			}

			if ( updatedNames.length ) {
				html += nameLog( i18n.updated + ' (' + updatedNames.length + ')', updatedNames, 'updated' );
			}

			if ( rowErrors.length ) {
				html += '<div class="tdl-error-table-wrap">';
				html += '<h3>' + esc( i18n.rowErrors ) + ' (' + rowErrors.length + ')</h3>';
				html += '<table class="tdl-error-table">';
				html += '<thead><tr>';
				html += '<th>' + esc( i18n.company ) + '</th>';
				html += '<th>' + esc( i18n.rows )    + '</th>';
				html += '<th>' + esc( i18n.issue )   + '</th>';
				html += '</tr></thead><tbody>';

				rowErrors.forEach( function ( entry ) {
					const row    = ( entry.rows   || [] ).join( ', ' );
					const errors = entry.errors   || [];

					let companyCell = esc( entry.company || '' );
					if ( entry.edit_url ) {
						companyCell += ' <a href="' + esc( entry.edit_url ) + '" class="tdl-edit-link">Edit post</a>';
					}

					html += '<tr>';
					html += '<td>' + companyCell + '</td>';
					html += '<td>' + esc( row ) + '</td>';
					html += '<td><ul class="tdl-error-list">';
					errors.forEach( function ( msg ) {
						html += '<li>' + esc( msg ) + '</li>';
					} );
					html += '</ul></td>';
					html += '</tr>';
				} );

				html += '</tbody></table></div>';
			}

			$result.html( html ).show();
		}

		function nameLog( summaryText, names, modifier ) {
			let html = '<details class="tdl-name-log tdl-name-log--' + modifier + '">';
			html += '<summary>' + esc( summaryText ) + '</summary>';
			html += '<ul class="tdl-name-log-list">';
			names.forEach( function ( name ) {
				html += '<li>' + esc( name ) + '</li>';
			} );
			html += '</ul></details>';
			return html;
		}

		function statBox( count, label, modifier ) {
			return '<div class="tdl-stat-box tdl-stat-' + modifier + '">' +
				'<span class="tdl-stat-num">' + parseInt( count, 10 ) + '</span>' +
				'<span class="tdl-stat-label">' + esc( label ) + '</span>' +
				'</div>';
		}

		function showFatal( message ) {
			$result.addClass( 'tdl-fatal' ).text( message ).show();
		}
	}

	// ── Title migration ───────────────────────────────────────────────────────

	function initMigration() {
		const $card = $( '#tdl-migration-card' );
		if ( ! $card.length ) return;

		const i18n = tdlCsvImport.i18n;

		// Auto-preview on load: check how many posts need migration.
		$.post(
			tdlCsvImport.ajaxUrl,
			{ action: 'tdl_migrate_titles_preview', nonce: tdlCsvImport.nonce },
			function ( r ) {
				if ( ! r.success ) {
					$card.html(
						'<div class="notice notice-error inline" style="margin:0;"><p>' +
						esc( r.data && r.data.message ? r.data.message : i18n.migError ) +
						'</p></div>'
					);
					return;
				}

				const changes = r.data.changes || [];

				if ( changes.length === 0 ) {
					$card.html(
						'<div class="notice notice-success inline" style="margin:0;"><p>' +
						esc( i18n.migNone ) +
						'</p></div>'
					);
					return;
				}

				// Build preview list of old → new titles.
				let listHtml = '';
				changes.forEach( function ( c ) {
					listHtml +=
						'<li><code>' + esc( c.old_title ) + '</code> &rarr; ' +
						'<code>' + esc( c.new_title ) + '</code></li>';
				} );

				$card.html(
					'<div class="notice notice-warning inline" style="margin:0;">' +
					'<p><strong>' + esc( i18n.migRequired ) + ':</strong> ' +
					esc( i18n.migCount.replace( '%d', changes.length ) ) + '</p>' +
					'<details style="margin:8px 0 12px;">' +
					'<summary style="cursor:pointer;">' + esc( i18n.migPreview ) + '</summary>' +
					'<ul style="margin:8px 0 0 8px;font-size:12px;max-height:200px;overflow-y:auto;">' +
					listHtml + '</ul></details>' +
					'<button id="tdl-mig-run-btn" class="button button-primary" style="margin-bottom:12px;">' +
					esc( i18n.migRun ) + '</button>' +
					'<span id="tdl-mig-status" style="margin-left:10px;font-size:13px;color:#666;"></span>' +
					'</div>'
				);

				$( '#tdl-mig-run-btn' ).on( 'click', function () {
					const $btn    = $( this );
					const $status = $( '#tdl-mig-status' );

					$btn.prop( 'disabled', true );
					$status.text( i18n.migRunning );

					$.post(
						tdlCsvImport.ajaxUrl,
						{ action: 'tdl_migrate_titles_run', nonce: tdlCsvImport.nonce },
						function ( res ) {
							if ( res.success ) {
								$card.html(
									'<div class="notice notice-success inline" style="margin:0;">' +
									'<p><strong>' + esc( i18n.migComplete ) + ':</strong> ' +
									parseInt( res.data.updated, 10 ) + ' ' + esc( i18n.migUpdated ) +
									'</p></div>'
								);
							} else {
								$status.text( i18n.migError );
								$btn.prop( 'disabled', false );
							}
						}
					);
				} );
			}
		);
	}

	/** Minimal HTML escaping for dynamic content inserted via innerHTML. */
	function esc( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

} )( jQuery );
