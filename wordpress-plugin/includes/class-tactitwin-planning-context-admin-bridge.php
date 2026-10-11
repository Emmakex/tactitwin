<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TactiTwin_Planning_Context_Admin_Bridge {
    public static function register(): void {
        add_action( 'admin_footer', array( self::class, 'hide_legacy_staff_brief_target' ), 20 );
    }

    public static function hide_legacy_staff_brief_target(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $page = sanitize_key( (string) ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'tactitwin-staff-brief' !== $page ) {
            return;
        }
        ?>
        <script>
        (function () {
            var shared = document.getElementById('tt-planning-context');
            var legacy = document.getElementById('tt-target-match');
            if (!shared || !legacy) return;

            legacy.value = '0';
            legacy.disabled = true;
            var row = legacy.closest('tr');
            if (row) row.style.display = 'none';
        }());
        </script>
        <?php
    }
}
