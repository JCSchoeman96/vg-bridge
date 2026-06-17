Voelgoed Course Bridge - Sender v1.0.0
Install on: winkel.voelgoed.co.za

Purpose
-------
This plugin sends a signed access request to leer.voelgoed.co.za after WooCommerce confirms that an order has been paid. It is designed for one paid order buyer email = one LearnDash access, regardless of item quantity.

Required wp-config.php constants on winkel
-----------------------------------------
define("VG_COURSE_BRIDGE_REMOTE_URL", "https://leer.voelgoed.co.za/wp-json/voelgoed-course-bridge/v1/grant-access");
define("VG_COURSE_BRIDGE_SHARED_SECRET", "replace-with-long-random-secret");
define("VG_COURSE_BRIDGE_SOURCE_SITE", "winkel.voelgoed.co.za");
define("VG_COURSE_BRIDGE_ADMIN_EMAIL", "online@carpediem.co.za");

Important: use a long random secret, not a UUIDv7, before going live. Recommended example:
openssl rand -hex 32

How to configure a bundle product
---------------------------------
1. Go to Products > Edit product on winkel.voelgoed.co.za.
2. Open the Product data box.
3. Open the Course Bridge tab.
4. Tick "Enable Course Bridge access".
5. Choose LearnDash Group or LearnDash Course.
6. Enter the remote LearnDash Group/Course ID from leer.voelgoed.co.za.
7. Add an Access label, for example "21-Day Course".
8. Update the product.

How it runs
-----------
Primary hook: woocommerce_payment_complete
Fallback hook: woocommerce_order_status_processing
Full refund handling: when WooCommerce records a full refund, a revoke request is sent to leer.voelgoed.co.za.

Admin logs
----------
WooCommerce > Course Bridge

Notes
-----
- Paystack should keep updating WooCommerce orders through the normal Paystack WooCommerce webhook.
- This bridge should not receive Paystack webhooks directly.
- The plugin adds order notes on successful grants/revokes.
