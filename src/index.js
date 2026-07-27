/**
 * Build entry for community-resources-hub.
 *
 * The plugin registers classic frontend runtime assets from explicit source
 * paths rather than a single generated site bundle, but wp-scripts still
 * expects a top-level src entry when build/start runs without explicit entry
 * arguments.
 *
 * Keeping this file as the canonical build entry prevents the noisy
 * "No entry file discovered in the \"src\" directory." warning while still
 * letting webpack validate the plugin-owned module graph.
 */

import './calendar/index.js';
import './member-directory/index.js';
import './shared/dialog.js';
import './video-slider/index.js';
import '../blocks/opportunity-hub/src/view/index.js';
