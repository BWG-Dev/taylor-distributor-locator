<?php
/**
 * Admin CSV Import Page — M4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sample_url = wp_nonce_url(
	admin_url( 'admin-ajax.php?action=tdl_download_sample_csv' ),
	'tdl_csv_import',
	'nonce'
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Import Distributors from CSV', 'taylor-distributor-locator' ); ?></h1>

	<!-- ── Title migration card (populated by initMigration() in tdl-csv-import.js) -->
	<div id="tdl-migration-card" style="margin-top:16px;">
		<div class="notice notice-info inline" style="margin:0;">
			<p><?php esc_html_e( 'Checking distributor post titles…', 'taylor-distributor-locator' ); ?></p>
		</div>
	</div>

	<div class="notice notice-warning inline" style="margin-top:16px;">
		<p>
			<strong><?php esc_html_e( 'Update behavior:', 'taylor-distributor-locator' ); ?></strong>
			<?php esc_html_e(
				'Each CSV row is one distributor post (one physical location). If a post title matches an existing post, its location data and service zones will be fully replaced by this CSV. This cannot be undone — export a backup first if needed.',
				'taylor-distributor-locator'
			); ?>
		</p>
	</div>

	<div class="tdl-import-layout">

		<!-- ── Instructions ──────────────────────────────────────────── -->
		<div class="tdl-import-instructions">
			<h2><?php esc_html_e( 'CSV Format', 'taylor-distributor-locator' ); ?></h2>
			<p>
				<?php esc_html_e( 'Download the sample template to get started. Only company_name is required; all other columns are optional.', 'taylor-distributor-locator' ); ?>
				<a href="<?php echo esc_url( $sample_url ); ?>" class="button button-secondary tdl-download-btn">
					&#11015; <?php esc_html_e( 'Download Sample CSV', 'taylor-distributor-locator' ); ?>
				</a>
			</p>

			<p>
				<strong><?php esc_html_e( 'One row = one distributor post (one physical location).', 'taylor-distributor-locator' ); ?></strong>
				<?php esc_html_e( 'Use is_primary=1 for a company\'s headquarters (parent post) and is_primary=0 for branch locations (child posts). The post title is built automatically as "Company — Location" using location_name or city.', 'taylor-distributor-locator' ); ?>
			</p>

			<table class="widefat tdl-col-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Column', 'taylor-distributor-locator' ); ?></th>
						<th><?php esc_html_e( 'Description', 'taylor-distributor-locator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr class="tdl-row-required"><td><code>company_name</code></td><td><?php esc_html_e( 'Required. Exact company name — used for parent/child linking and post title.', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>phone</code></td><td><?php esc_html_e( 'Main phone number', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>website</code></td><td><?php esc_html_e( 'Full URL including https://', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>email_main</code></td><td><?php esc_html_e( 'Main contact email', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>email_sales</code></td><td><?php esc_html_e( 'Sales email (used for lead routing in M8)', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>email_parts</code></td><td><?php esc_html_e( 'Parts email', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>email_service</code></td><td><?php esc_html_e( 'Service email', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>email_installations</code></td><td><?php esc_html_e( 'Installations email', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>service_area_notes</code></td><td><?php esc_html_e( 'Free-text description of service area', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td colspan="2"><hr></td></tr>
					<tr><td><code>location_name</code></td><td><?php esc_html_e( 'e.g. "Headquarters" or "West Branch" — used in post title and map popup', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>street_address</code></td><td><?php esc_html_e( 'Physical street address', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>city</code></td><td><?php esc_html_e( 'City — used in post title if location_name is blank', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>state_province</code></td><td><?php esc_html_e( 'State or province code (TX, ON, etc.)', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>zip_postal</code></td><td><?php esc_html_e( 'ZIP or postal code', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>country_code</code></td><td><?php esc_html_e( '2-letter ISO code: US, CA, MX, etc.', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>location_phone</code></td><td><?php esc_html_e( 'Phone number for this specific location (overrides company phone in popup)', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>hours_operation</code></td><td><?php esc_html_e( 'e.g. "Mon–Fri 8am–5pm"', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>is_primary</code></td><td><?php esc_html_e( '1 = headquarters / parent post. 0 = branch / child post.', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td colspan="2"><hr></td></tr>
					<tr><td><code>zip_codes_served</code></td><td><?php esc_html_e( 'Semicolon-separated ZIPs or ranges: 75001;75002;75100-75199', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>states_served</code></td><td><?php esc_html_e( 'Semicolon-separated state/province codes: TX;OK;NM', 'taylor-distributor-locator' ); ?></td></tr>
					<tr><td><code>countries_served</code></td><td><?php esc_html_e( 'Semicolon-separated ISO country codes: US;CA', 'taylor-distributor-locator' ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<!-- ── Upload form ───────────────────────────────────────────── -->
		<div class="tdl-import-form-wrap">
			<h2><?php esc_html_e( 'Upload CSV', 'taylor-distributor-locator' ); ?></h2>

			<form id="tdl-csv-import-form" enctype="multipart/form-data">
				<p>
					<label for="csv_file"><strong><?php esc_html_e( 'Select CSV file:', 'taylor-distributor-locator' ); ?></strong></label><br>
					<input type="file" name="csv_file" id="csv_file" accept=".csv" required style="margin-top:6px;">
				</p>
				<p style="margin-bottom:12px;">
					<label>
						<input type="checkbox" id="tdl-dry-run" value="1" checked />
						<?php esc_html_e( 'Dry run (preview only — no changes will be made)', 'taylor-distributor-locator' ); ?>
					</label>
				</p>
				<p>
					<button type="submit" class="button button-primary" id="tdl-import-btn">
						<?php esc_html_e( 'Preview Import', 'taylor-distributor-locator' ); ?>
					</button>
				</p>
			</form>

			<div id="tdl-import-progress" style="display:none;">
				<div class="tdl-progress-bar">
					<div class="tdl-progress-fill"></div>
				</div>
				<p class="tdl-progress-text"></p>
			</div>

			<div id="tdl-import-result" style="display:none;"></div>
		</div>

	</div><!-- .tdl-import-layout -->

	<!-- ── Video tutorial ────────────────────────────────────────────── -->
	<?php
	/**
	 * TODO: Replace $tutorial_url with the final Loom / video embed URL before launch.
	 * When populated the placeholder box is replaced by a live iframe — no other changes needed.
	 * e.g. 'https://www.loom.com/embed/abc123'
	 */
	$tutorial_url = '';
	?>
	<div id="tdl-video-section" class="tdl-video-section">
		<h2><?php esc_html_e( 'How-to Video', 'taylor-distributor-locator' ); ?></h2>

		<div class="tdl-video-wrap">
			<?php if ( $tutorial_url ) : ?>
				<iframe
					src="<?php echo esc_url( $tutorial_url ); ?>"
					class="tdl-video-iframe"
					frameborder="0"
					allowfullscreen
					allow="autoplay; fullscreen"
				></iframe>
			<?php else : ?>
				<div class="tdl-video-placeholder">
					<div class="tdl-video-placeholder-inner">
						<span class="tdl-video-play-icon">&#9654;</span>
						<p class="tdl-video-placeholder-label">
							<?php esc_html_e( 'How-to video coming soon', 'taylor-distributor-locator' ); ?>
						</p>
						<p class="tdl-video-placeholder-sub">
							<?php esc_html_e( 'A walkthrough of the CSV import tool will be added here before launch.', 'taylor-distributor-locator' ); ?>
						</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div><!-- .tdl-video-section -->

</div><!-- .wrap -->

<!-- Floating jump-to-video button -->
<a href="#tdl-video-section" class="tdl-video-fab" title="<?php esc_attr_e( 'Watch how-to video', 'taylor-distributor-locator' ); ?>">
	&#9654;
	<span class="tdl-video-fab-label"><?php esc_html_e( 'How-to video', 'taylor-distributor-locator' ); ?></span>
</a>

<style>
/* Video section — bottom of page */
.tdl-video-section {
	margin-top: 40px;
	padding-top: 24px;
	border-top: 1px solid #e0e0e0;
	max-width: 760px;
	scroll-margin-top: 32px;
}

.tdl-video-wrap {
	margin-top: 12px;
}

.tdl-video-iframe {
	width: 100%;
	aspect-ratio: 16 / 9;
	border-radius: 4px;
	display: block;
}

.tdl-video-placeholder {
	width: 100%;
	aspect-ratio: 16 / 9;
	background: #f0f0f1;
	border: 2px dashed #c3c4c7;
	border-radius: 4px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.tdl-video-placeholder-inner {
	text-align: center;
	color: #646970;
}

.tdl-video-play-icon {
	display: block;
	font-size: 48px;
	line-height: 1;
	margin-bottom: 12px;
	color: #a7aaad;
}

.tdl-video-placeholder-label {
	font-size: 15px;
	font-weight: 600;
	margin: 0 0 4px;
	color: #3c434a;
}

.tdl-video-placeholder-sub {
	font-size: 13px;
	margin: 0;
}

/* Floating jump-to-video button (bottom-right corner) */
.tdl-video-fab {
	position: fixed;
	bottom: 32px;
	right: 32px;
	display: flex;
	align-items: center;
	gap: 8px;
	background: #2271b1;
	color: #fff;
	text-decoration: none;
	padding: 10px 16px 10px 14px;
	border-radius: 24px;
	font-size: 13px;
	font-weight: 600;
	box-shadow: 0 2px 8px rgba(0,0,0,0.25);
	transition: background 0.15s ease, box-shadow 0.15s ease;
	z-index: 9999;
	line-height: 1;
}

.tdl-video-fab:hover,
.tdl-video-fab:focus {
	background: #135e96;
	color: #fff;
	box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.tdl-video-fab-label {
	font-size: 12px;
}

.tdl-import-layout {
	display: grid;
	grid-template-columns: 1fr 380px;
	gap: 40px;
	margin-top: 20px;
	align-items: start;
}

.tdl-import-instructions .widefat {
	margin-top: 16px;
}

.tdl-col-table td,
.tdl-col-table th {
	padding: 6px 10px;
}

.tdl-col-table code {
	background: #f0f0f0;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 12px;
}

.tdl-row-required td {
	font-weight: 600;
}

.tdl-download-btn {
	margin-left: 10px;
	vertical-align: middle;
}

.tdl-import-form-wrap {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px 24px;
}

/* Progress bar */
.tdl-progress-bar {
	height: 6px;
	background: #e0e0e0;
	border-radius: 3px;
	overflow: hidden;
	margin: 16px 0 8px;
}

.tdl-progress-fill {
	height: 100%;
	background: #2271b1;
	width: 0%;
	transition: width 0.3s ease;
}

.tdl-progress-text {
	font-size: 13px;
	color: #666;
	margin: 0;
}

/* Stat boxes */
.tdl-import-stats {
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
	margin-bottom: 16px;
}

.tdl-stat-box {
	flex: 1;
	min-width: 70px;
	text-align: center;
	padding: 10px 8px;
	border-radius: 4px;
	border: 1px solid #ddd;
}

.tdl-stat-box .tdl-stat-num {
	display: block;
	font-size: 24px;
	font-weight: 700;
	line-height: 1.2;
}

.tdl-stat-box .tdl-stat-label {
	display: block;
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	margin-top: 2px;
}

.tdl-stat-box.tdl-stat-created { background: #edfaef; border-color: #68de7c; color: #1a7a2e; }
.tdl-stat-box.tdl-stat-updated { background: #e8f0fb; border-color: #72aee6; color: #1d4b82; }
.tdl-stat-box.tdl-stat-skipped { background: #f6f7f7; border-color: #c3c4c7; color: #555; }
.tdl-stat-box.tdl-stat-errors  { background: #fcf0f1; border-color: #f8a9ad; color: #8a2424; }

/* Row error table */
.tdl-error-table-wrap h3 {
	margin: 16px 0 8px;
	font-size: 14px;
}

.tdl-error-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.tdl-error-table th {
	text-align: left;
	padding: 6px 8px;
	background: #f6f7f7;
	border: 1px solid #ddd;
}

.tdl-error-table td {
	padding: 6px 8px;
	border: 1px solid #ddd;
	vertical-align: top;
}

.tdl-error-table tr:nth-child(even) td {
	background: #fafafa;
}

.tdl-error-list {
	margin: 0;
	padding: 0 0 0 16px;
}

.tdl-error-list li {
	margin: 2px 0;
}

/* Created / Updated name logs */
.tdl-name-log {
	margin-bottom: 12px;
	border: 1px solid #ddd;
	border-radius: 4px;
	overflow: hidden;
}

.tdl-name-log summary {
	padding: 8px 12px;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	user-select: none;
}

.tdl-name-log--created summary { background: #edfaef; color: #1a7a2e; }
.tdl-name-log--updated summary { background: #e8f0fb; color: #1d4b82; }

.tdl-name-log-list {
	margin: 0;
	padding: 8px 12px 8px 28px;
	font-size: 13px;
	line-height: 1.7;
	border-top: 1px solid #ddd;
	max-height: 200px;
	overflow-y: auto;
}

/* Edit link in warnings table */
.tdl-edit-link {
	margin-left: 8px;
	font-size: 12px;
	font-weight: 400;
	white-space: nowrap;
}

/* Fatal error */
#tdl-import-result.tdl-fatal {
	padding: 10px 14px;
	background: #fcf0f1;
	border-left: 4px solid #d63638;
	color: #d63638;
	font-weight: 600;
}

@media (max-width: 1200px) {
	.tdl-import-layout {
		grid-template-columns: 1fr;
	}
}
</style>
