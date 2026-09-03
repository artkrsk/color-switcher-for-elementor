import { execSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Seeds the site the specs run against with dev/seed/demo-page.php — the same
 * fixture the wp.org Live Preview blueprint inlines, so a page that stops
 * rendering breaks the suite and the shop window on one signal.
 *
 * No login: every spec is a frontend visitor.
 */
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')

/** test-workspace is the repo root, mounted by .wp-env.json. */
const SEED = '/var/www/html/test-workspace/dev/seed/demo-page.php'

/**
 * The cross-plugin page, kept out of demo-page.php because that fixture is
 * also the wp.org blueprint's, which ships this plugin alone.
 */
const HORIZONTAL_SEED = '/var/www/html/test-workspace/dev/seed/horizontal-page.php'

/** The dark-mode journey needs a page that carries the toggle. */
const TOGGLE_SEED = '/var/www/html/test-workspace/dev/seed/toggle-page.php'

/** Alt images: the only tier that proves the generated CSS actually applies. */
const ALT_IMAGES_SEED = '/var/www/html/test-workspace/dev/seed/alt-images-page.php'

const wp = (command: string): string =>
  execSync(`pnpm exec wp-env run cli -- ${command}`, {
    cwd: ROOT,
    timeout: 120000
  }).toString()

export default function globalSetup(): void {
  wp("wp rewrite structure '/%postname%/' --hard")
  wp('wp rewrite flush')

  const seeded = wp(`wp eval-file ${SEED} --user=1`)
  console.log(`[e2e] ${seeded.trim()}`)

  const horizontal = wp(`wp eval-file ${HORIZONTAL_SEED} --user=1`)
  console.log(`[e2e] ${horizontal.trim()}`)

  const toggle = wp(`wp eval-file ${TOGGLE_SEED} --user=1`)
  console.log(`[e2e] ${toggle.trim()}`)

  const altImages = wp(`wp eval-file ${ALT_IMAGES_SEED} --user=1`)
  console.log(`[e2e] ${altImages.trim()}`)

  // Elementor's one-time activation redirect would otherwise hijack the first
  // navigation of the run.
  try {
    wp('wp transient delete elementor_activation_redirect')
  } catch {
    // Absent on an already-onboarded site, which is the normal case.
  }
}
