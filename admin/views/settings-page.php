<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$selected_roles = (array) ( $settings['target_roles'] ?? array() );
$environment    = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
$prod_override  = defined( 'ULS_ALLOW_PRODUCTION_QUICK_LOGIN' ) && constant( 'ULS_ALLOW_PRODUCTION_QUICK_LOGIN' );
?>
<div class="wrap uls-settings-wrap">
	<div class="uls-settings-hero">
		<div>
			<h1><?php esc_html_e( 'User Login Switch', 'user-login-switch' ); ?></h1>
			<p><?php esc_html_e( 'Secure one-click admin switching with role controls and audit visibility.', 'user-login-switch' ); ?></p>
		</div>
		<span class="uls-env-badge"><?php echo esc_html( sprintf( __( 'Environment: %s', 'user-login-switch' ), $environment ) ); ?></span>
	</div>

	<form method="post" action="options.php" class="uls-settings-form">
		<?php settings_fields( 'uls_settings_group' ); ?>

		<div class="uls-settings-grid">
			<section class="uls-card">
				<h2><?php esc_html_e( 'Core controls', 'user-login-switch' ); ?></h2>
				<label class="uls-check-row"><input type="checkbox" name="uls_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> /> <?php esc_html_e( 'Enable user switching', 'user-login-switch' ); ?></label>
				<p>
					<label for="uls-timeout"><?php esc_html_e( 'Session timeout (minutes)', 'user-login-switch' ); ?></label>
					<input id="uls-timeout" type="number" min="5" max="1440" name="uls_settings[timeout_minutes]" value="<?php echo esc_attr( (int) $settings['timeout_minutes'] ); ?>" />
				</p>
				<p><span class="uls-code-tag"><?php esc_html_e( 'Administrator only (fixed initiator role)', 'user-login-switch' ); ?></span></p>
			</section>

			<section class="uls-card">
				<h2><?php esc_html_e( 'Guest quick login', 'user-login-switch' ); ?></h2>
				<label class="uls-check-row"><input type="checkbox" name="uls_settings[enable_guest_quick_login]" value="1" <?php checked( ! empty( $settings['enable_guest_quick_login'] ) ); ?> /> <?php esc_html_e( 'Enable one-time quick login links for logged-out admins', 'user-login-switch' ); ?></label>
				<p class="description"><?php echo esc_html( sprintf( __( 'Current environment: %s. Quick login is allowed by default only on local/development/staging.', 'user-login-switch' ), $environment ) ); ?></p>
				<?php if ( 'production' === $environment ) : ?>
					<p class="uls-note uls-note--danger"><strong><?php esc_html_e( 'Strict production safety mode is active.', 'user-login-switch' ); ?></strong> <?php esc_html_e( 'Guest quick login remains blocked unless ULS_ALLOW_PRODUCTION_QUICK_LOGIN is set to true in wp-config.php.', 'user-login-switch' ); ?></p>
				<?php endif; ?>
				<?php if ( 'production' === $environment && $prod_override ) : ?>
					<p class="uls-note uls-note--warn"><strong><?php esc_html_e( 'Production override detected.', 'user-login-switch' ); ?></strong> <?php esc_html_e( 'Use with caution and short token TTL.', 'user-login-switch' ); ?></p>
				<?php endif; ?>
				<p>
					<label for="uls-quick-ttl"><?php esc_html_e( 'Link TTL (minutes)', 'user-login-switch' ); ?></label>
					<input id="uls-quick-ttl" type="number" min="1" max="60" name="uls_settings[guest_quick_login_ttl]" value="<?php echo esc_attr( (int) $settings['guest_quick_login_ttl'] ); ?>" />
				</p>
				<p class="description"><code>wp uls quick-login --user=admin</code></p>
			</section>

			<section class="uls-card uls-card--wide">
				<h2><?php esc_html_e( 'Allowed target roles', 'user-login-switch' ); ?></h2>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Allowed target roles', 'user-login-switch' ); ?></legend>
					<label class="uls-check-row"><input type="checkbox" class="uls-all-roles" <?php checked( empty( $selected_roles ) ); ?> /> <?php esc_html_e( 'All roles', 'user-login-switch' ); ?></label>
					<div class="uls-role-grid">
						<?php foreach ( $all_roles as $role_key => $role_data ) : ?>
							<label class="uls-check-row uls-check-row--tile">
								<input type="checkbox" class="uls-role-box" name="uls_settings[target_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $selected_roles, true ) ); ?> />
								<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'If no role is selected, all roles are allowed.', 'user-login-switch' ); ?></p>
				</fieldset>
			</section>

			<section class="uls-card">
				<h2><?php esc_html_e( 'Interface', 'user-login-switch' ); ?></h2>
				<label class="uls-check-row"><input type="checkbox" name="uls_settings[show_admin_bar]" value="1" <?php checked( ! empty( $settings['show_admin_bar'] ) ); ?> /> <?php esc_html_e( 'Admin bar return menu', 'user-login-switch' ); ?></label>
				<label class="uls-check-row"><input type="checkbox" name="uls_settings[show_admin_notice]" value="1" <?php checked( ! empty( $settings['show_admin_notice'] ) ); ?> /> <?php esc_html_e( 'Admin switched notice', 'user-login-switch' ); ?></label>
				<label class="uls-check-row"><input type="checkbox" name="uls_settings[show_frontend_widget]" value="1" <?php checked( ! empty( $settings['show_frontend_widget'] ) ); ?> /> <?php esc_html_e( 'Frontend switch widget', 'user-login-switch' ); ?></label>
				<p>
					<label for="uls-widget-position"><?php esc_html_e( 'Widget position', 'user-login-switch' ); ?></label>
					<select id="uls-widget-position" name="uls_settings[widget_position]">
						<option value="left-center" <?php selected( 'left-center', $settings['widget_position'] ?? 'left-center' ); ?>><?php esc_html_e( 'Left Center', 'user-login-switch' ); ?></option>
						<option value="right-center" <?php selected( 'right-center', $settings['widget_position'] ?? '' ); ?>><?php esc_html_e( 'Right Center', 'user-login-switch' ); ?></option>
						<option value="left-bottom" <?php selected( 'left-bottom', $settings['widget_position'] ?? '' ); ?>><?php esc_html_e( 'Left Bottom', 'user-login-switch' ); ?></option>
						<option value="right-bottom" <?php selected( 'right-bottom', $settings['widget_position'] ?? '' ); ?>><?php esc_html_e( 'Right Bottom', 'user-login-switch' ); ?></option>
					</select>
				</p>
				<p>
					<label for="uls-style-preset"><?php esc_html_e( 'Style preset', 'user-login-switch' ); ?></label>
					<select id="uls-style-preset" name="uls_settings[style_preset]">
						<option value="default" <?php selected( 'default', $settings['style_preset'] ); ?>><?php esc_html_e( 'Default', 'user-login-switch' ); ?></option>
						<option value="compact" <?php selected( 'compact', $settings['style_preset'] ); ?>><?php esc_html_e( 'Compact', 'user-login-switch' ); ?></option>
						<option value="minimal" <?php selected( 'minimal', $settings['style_preset'] ); ?>><?php esc_html_e( 'Minimal', 'user-login-switch' ); ?></option>
					</select>
				</p>
			</section>

			<section class="uls-card">
				<h2><?php esc_html_e( 'Audit logging', 'user-login-switch' ); ?></h2>
				<label class="uls-check-row"><input type="checkbox" name="uls_settings[enable_audit_log]" value="1" <?php checked( ! empty( $settings['enable_audit_log'] ) ); ?> /> <?php esc_html_e( 'Enable audit logs', 'user-login-switch' ); ?></label>
				<p>
					<label for="uls-retention-days"><?php esc_html_e( 'Retention days', 'user-login-switch' ); ?></label>
					<input id="uls-retention-days" type="number" min="1" max="3650" name="uls_settings[log_retention_days]" value="<?php echo esc_attr( (int) $settings['log_retention_days'] ); ?>" />
				</p>
			</section>
		</div>

		<div class="uls-actions">
			<?php submit_button( __( 'Save Settings', 'user-login-switch' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var allRoles = document.querySelector('.uls-all-roles');
  var roleBoxes = document.querySelectorAll('.uls-role-box');
  if (!allRoles || !roleBoxes.length) {
    return;
  }

  function updateFromAll() {
    if (allRoles.checked) {
      roleBoxes.forEach(function (box) { box.checked = false; });
    }
  }

  function updateAllFromRoles() {
    var anyChecked = false;
    roleBoxes.forEach(function (box) {
      if (box.checked) {
        anyChecked = true;
      }
    });
    allRoles.checked = !anyChecked;
  }

  allRoles.addEventListener('change', updateFromAll);
  roleBoxes.forEach(function (box) {
    box.addEventListener('change', updateAllFromRoles);
  });
});
</script>
