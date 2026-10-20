<?php
/**
 * HTML email template — quote request notification sent to the distributor.
 *
 * Rendered by TDL_Email_Router::send_email() via output buffering.
 * $template_data is extracted into local variables before this file is included.
 *
 * Available variables:
 *   string $distributor_name
 *   int    $entry_id
 *   string $customer_name
 *   string $customer_email
 *   string $customer_phone
 *   string $customer_company
 *   string $customer_message
 *   string $submitted_at
 *   string $site_name
 *   string $site_url
 *   string $gf_entry_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Expose $template_data array keys as local variables.
// phpcs:ignore WordPress.PHP.DontExtract.extract_extract — template vars, not user input; all values are pre-sanitized.
extract( $template_data, EXTR_SKIP );
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f0f0f0;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f0f0;">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <!--[if mso]><table width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
      <table role="presentation" cellpadding="0" cellspacing="0" border="0"
             style="max-width:600px;width:100%;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.10);">

        <!-- ── Header ── -->
        <tr>
          <td style="background-color:#1a1a1a;padding:28px 32px;">
            <p style="margin:0;font-size:20px;font-weight:bold;color:#ffffff;letter-spacing:0.3px;">
              <?php echo esc_html( $site_name ); ?>
            </p>
            <p style="margin:6px 0 0;font-size:13px;color:#aaaaaa;letter-spacing:0.5px;text-transform:uppercase;">
              <?php esc_html_e( 'New Quote Request', 'taylor-distributor-locator' ); ?>
            </p>
          </td>
        </tr>

        <!-- ── Intro ── -->
        <tr>
          <td style="padding:28px 32px 4px;">
            <p style="margin:0;font-size:15px;color:#222222;line-height:1.6;">
              <?php
              printf(
                  /* translators: %s: distributor name */
                  esc_html__( 'A new quote request has been submitted for %s.', 'taylor-distributor-locator' ),
                  '<strong>' . esc_html( $distributor_name ) . '</strong>'
              );
              ?>
            </p>
          </td>
        </tr>

        <!-- ── Customer Information ── -->
        <tr>
          <td style="padding:24px 32px 0;">
            <p style="margin:0 0 10px;font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:1.2px;color:#888888;">
              <?php esc_html_e( 'Customer Information', 'taylor-distributor-locator' ); ?>
            </p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="border-top:2px solid #eeeeee;">
              <?php
              $info_rows = [
                  __( 'Name',    'taylor-distributor-locator' ) => $customer_name,
                  __( 'Email',   'taylor-distributor-locator' ) => $customer_email,
                  __( 'Phone',   'taylor-distributor-locator' ) => $customer_phone    ?: '—',
                  __( 'Company', 'taylor-distributor-locator' ) => $customer_company  ?: '—',
              ];
              foreach ( $info_rows as $label => $value ) :
              ?>
              <tr>
                <td style="padding:10px 12px 10px 0;border-bottom:1px solid #f0f0f0;width:34%;font-size:13px;color:#777777;vertical-align:top;">
                  <?php echo esc_html( $label ); ?>
                </td>
                <td style="padding:10px 0;border-bottom:1px solid #f0f0f0;font-size:14px;color:#222222;vertical-align:top;">
                  <?php echo esc_html( $value ); ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </table>
          </td>
        </tr>

        <!-- ── Message / Needs ── -->
        <tr>
          <td style="padding:24px 32px 0;">
            <p style="margin:0 0 10px;font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:1.2px;color:#888888;">
              <?php esc_html_e( 'Message / Needs', 'taylor-distributor-locator' ); ?>
            </p>
            <div style="background:#f8f8f8;border-left:3px solid #1a1a1a;padding:14px 16px;border-radius:0 4px 4px 0;font-size:14px;color:#333333;line-height:1.7;">
              <?php
              $msg = $customer_message ?: __( '(no message provided)', 'taylor-distributor-locator' );
              echo nl2br( esc_html( $msg ) );
              ?>
            </div>
          </td>
        </tr>

        <!-- ── Reply CTA ── -->
        <tr>
          <td style="padding:28px 32px 0;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="background-color:#1a1a1a;border-radius:4px;padding:12px 24px;">
                  <a href="mailto:<?php echo esc_attr( $customer_email ); ?>"
                     style="font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;display:inline-block;">
                    <?php
                    printf(
                        /* translators: %s: customer email */
                        esc_html__( 'Reply to %s', 'taylor-distributor-locator' ),
                        esc_html( $customer_email )
                    );
                    ?>
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ── Submission Details ── -->
        <tr>
          <td style="padding:24px 32px 0;">
            <p style="margin:0 0 10px;font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:1.2px;color:#888888;">
              <?php esc_html_e( 'Submission Details', 'taylor-distributor-locator' ); ?>
            </p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="border-top:2px solid #eeeeee;">
              <tr>
                <td style="padding:10px 12px 10px 0;border-bottom:1px solid #f0f0f0;width:34%;font-size:13px;color:#777777;">
                  <?php esc_html_e( 'Submitted (UTC)', 'taylor-distributor-locator' ); ?>
                </td>
                <td style="padding:10px 0;border-bottom:1px solid #f0f0f0;font-size:14px;color:#222222;">
                  <?php echo esc_html( $submitted_at ); ?>
                </td>
              </tr>
              <tr>
                <td style="padding:10px 12px 10px 0;font-size:13px;color:#777777;">
                  <?php esc_html_e( 'Entry', 'taylor-distributor-locator' ); ?>
                </td>
                <td style="padding:10px 0;font-size:14px;color:#222222;">
                  #<?php echo absint( $entry_id ); ?>
                  &nbsp;
                  <a href="<?php echo esc_url( $gf_entry_url ); ?>"
                     style="font-size:12px;color:#555555;">
                    <?php esc_html_e( '(view in admin)', 'taylor-distributor-locator' ); ?>
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ── Footer ── -->
        <tr>
          <td style="padding:28px 32px;border-top:1px solid #eeeeee;margin-top:28px;">
            <p style="margin:0;font-size:12px;color:#aaaaaa;text-align:center;line-height:1.6;">
              <?php
              printf(
                  /* translators: 1: site name, 2: site URL */
                  esc_html__( 'Sent automatically by the Distributor Locator on %1$s', 'taylor-distributor-locator' ),
                  esc_html( $site_name )
              );
              ?>
              <br>
              <a href="<?php echo esc_url( $site_url ); ?>"
                 style="color:#aaaaaa;text-decoration:none;">
                <?php echo esc_url( $site_url ); ?>
              </a>
            </p>
          </td>
        </tr>

      </table>
      <!--[if mso]></td></tr></table><![endif]-->

    </td>
  </tr>
</table>

</body>
</html>
