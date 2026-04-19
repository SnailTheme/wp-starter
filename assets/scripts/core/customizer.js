/**
 * File customizer.js.
 * global wp
 *
 * Core Customizer preview script.
 *
 * This file is loaded from /core/customizer.php and belongs to the reusable
 * core layer. Add project-specific Customizer preview behavior in a separate
 * project-owned script rather than editing this file.
 *
 * Current purpose:
 * - Provides a stable script handle and file path for future core Customizer
 *   preview behavior.
 * - Creates a small namespace that future core code can extend without relying
 *   on globals created by a generated theme.
 *
 * @package ST_WP_Core
 */

window.stCoreCustomizer = window.stCoreCustomizer || {};
