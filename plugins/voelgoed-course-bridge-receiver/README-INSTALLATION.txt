Voelgoed Course Bridge - Receiver v1.0.0
Install on: leer.voelgoed.co.za

Purpose
-------
This plugin receives signed access requests from winkel.voelgoed.co.za, creates/fetches the learner by billing email, and grants or revokes LearnDash access.

Required wp-config.php constants on leer
---------------------------------------
define("VG_COURSE_BRIDGE_ALLOWED_SOURCE", "winkel.voelgoed.co.za");
define("VG_COURSE_BRIDGE_SHARED_SECRET", "same-long-random-secret-as-winkel");
define("VG_COURSE_BRIDGE_LOGIN_URL", "https://leer.voelgoed.co.za/my-rekening/");
define("VG_COURSE_BRIDGE_ADMIN_EMAIL", "online@carpediem.co.za");

Optional but recommended receiver whitelist
------------------------------------------
This prevents a valid signed request from granting the wrong LearnDash ID.
Only add IDs that should be grantable by the bridge.

define("VG_COURSE_BRIDGE_ALLOWED_ENTITLEMENTS", [
    "learndash_group" => [123],
    "learndash_course" => [],
]);

Important: use a long random secret, not a UUIDv7, before going live. Recommended example:
openssl rand -hex 32

REST endpoint
-------------
https://leer.voelgoed.co.za/wp-json/voelgoed-course-bridge/v1/grant-access

How it works
------------
- Validates X-VG-Bridge-* HMAC headers.
- Rejects stale timestamps older than 5 minutes.
- Consumes nonces to prevent replay.
- Uses a receiver log table to enforce idempotency.
- Creates a new WordPress user if the billing email does not exist.
- New users get the customer role if the role exists; otherwise subscriber.
- Existing users keep their existing role.
- LearnDash Group grants use ld_update_group_access().
- LearnDash Course grants use ld_update_course_access().
- New users receive an email with a password setup link.
- Existing users receive an access email with the login URL.

Admin logs
----------
Tools > Course Bridge Grants
