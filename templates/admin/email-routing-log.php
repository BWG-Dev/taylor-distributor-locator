<?php
/**
 * Admin template — Email Routing Log page.
 *
 * Variables provided by TDL_Admin_Log::render_log_page():
 *   object[] $entries
 *   int      $total
 *   int      $total_pages
 *   int      $current_page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
  <h1><?php esc_html_e( 'Email Routing Log', 'taylor-distributor-locator' ); ?></h1>
  <p class="description" style="margin-bottom:16px;">
    <?php esc_html_e( 'Records every email routing decision made when a quote request form is submitted. Times are UTC.', 'taylor-distributor-locator' ); ?>
  </p>

  <?php if ( empty( $entries ) ) : ?>
    <p><?php esc_html_e( 'No routing events have been recorded yet.', 'taylor-distributor-locator' ); ?></p>
  <?php else : ?>

  <table class="wp-list-table widefat fixed striped" style="table-layout:auto;">
    <thead>
      <tr>
        <th style="width:160px;"><?php esc_html_e( 'Date / Time (UTC)', 'taylor-distributor-locator' ); ?></th>
        <th style="width:80px;"><?php esc_html_e( 'Severity', 'taylor-distributor-locator' ); ?></th>
        <th style="width:200px;"><?php esc_html_e( 'Distributor', 'taylor-distributor-locator' ); ?></th>
        <th style="width:110px;"><?php esc_html_e( 'Source Used', 'taylor-distributor-locator' ); ?></th>
        <th><?php esc_html_e( 'Recipient Email', 'taylor-distributor-locator' ); ?></th>
        <th style="width:75px;"><?php esc_html_e( 'Entry', 'taylor-distributor-locator' ); ?></th>
        <th><?php esc_html_e( 'Message', 'taylor-distributor-locator' ); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ( $entries as $row ) :
        // Severity colours — plain hex so no external CSS dependency.
        $sev_style = [
          'info'    => 'color:#2271b1;background:#e8f0fb;',
          'warning' => 'color:#b26200;background:#fef8ef;',
          'error'   => 'color:#cc1818;background:#fdf1f1;',
        ];
        $source_style = [
          'email_sales' => 'color:#2271b1;',
          'email_main'  => 'color:#b26200;',
          'none'        => 'color:#cc1818;',
        ];
        $sev          = $row->severity;
        $source       = $row->routing_source;
        $dist_edit_url = $row->distributor_id ? get_edit_post_link( (int) $row->distributor_id ) : '';
        $gf_entry_url  = $row->gf_entry_id
          ? admin_url( 'admin.php?page=gf_entries&view=entry&id=' . absint( TDL_GF_Integration::get_form_id() ) . '&lid=' . absint( $row->gf_entry_id ) )
          : '';
      ?>
      <tr>
        <td style="font-size:12px;white-space:nowrap;"><?php echo esc_html( $row->created_at ); ?></td>

        <td>
          <span style="display:inline-block;padding:2px 7px;border-radius:3px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;<?php echo isset( $sev_style[ $sev ] ) ? esc_attr( $sev_style[ $sev ] ) : ''; ?>">
            <?php echo esc_html( $sev ); ?>
          </span>
        </td>

        <td>
          <?php if ( $dist_edit_url ) : ?>
            <a href="<?php echo esc_url( $dist_edit_url ); ?>" style="font-size:12px;">#<?php echo absint( $row->distributor_id ); ?></a>
          <?php elseif ( $row->distributor_id ) : ?>
            <span style="font-size:12px;">#<?php echo absint( $row->distributor_id ); ?></span>
          <?php else : ?>
            &mdash;
          <?php endif; ?>
          <?php if ( ! empty( $row->distributor_name ) ) : ?>
            <br><small style="color:#666;"><?php echo esc_html( $row->distributor_name ); ?></small>
          <?php endif; ?>
        </td>

        <td>
          <code style="font-size:11px;<?php echo isset( $source_style[ $source ] ) ? esc_attr( $source_style[ $source ] ) : ''; ?>">
            <?php echo esc_html( $source ); ?>
          </code>
        </td>

        <td style="font-size:12px;word-break:break-all;"><?php echo esc_html( $row->recipient_email ); ?></td>

        <td>
          <?php if ( $gf_entry_url ) : ?>
            <a href="<?php echo esc_url( $gf_entry_url ); ?>" style="font-size:12px;">#<?php echo absint( $row->gf_entry_id ); ?></a>
          <?php elseif ( $row->gf_entry_id ) : ?>
            <span style="font-size:12px;">#<?php echo absint( $row->gf_entry_id ); ?></span>
          <?php else : ?>
            &mdash;
          <?php endif; ?>
        </td>

        <td style="font-size:12px;color:#444;"><?php echo esc_html( $row->message ); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ( $total_pages > 1 ) : ?>
  <div style="margin-top:16px;display:flex;align-items:center;gap:6px;">
    <?php
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only pagination.
    $current_page_num = max( 1, absint( $_GET['paged'] ?? 1 ) );
    $base_url = admin_url( 'edit.php?post_type=distributor&page=tdl-routing-log' );
    for ( $i = 1; $i <= $total_pages; $i++ ) :
        $url = add_query_arg( 'paged', $i, $base_url );
        if ( $i === $current_page_num ) : ?>
          <strong style="display:inline-block;padding:4px 10px;background:#2271b1;color:#fff;border-radius:3px;font-size:13px;"><?php echo absint( $i ); ?></strong>
        <?php else : ?>
          <a href="<?php echo esc_url( $url ); ?>" style="display:inline-block;padding:4px 10px;background:#f0f0f1;border-radius:3px;font-size:13px;color:#2271b1;text-decoration:none;"><?php echo absint( $i ); ?></a>
        <?php endif;
    endfor; ?>
    <span style="font-size:12px;color:#666;margin-left:8px;">
      <?php
      printf(
          /* translators: 1: total entries */
          esc_html__( '%d total entries', 'taylor-distributor-locator' ),
          absint( $total )
      );
      ?>
    </span>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>
