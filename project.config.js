import process from 'node:process'

export default {
  slug: 'color-switcher-for-elementor',
  versionConstant: 'ARTS_COLOR_SWITCHER_PLUGIN_VERSION',
  defineKey: '__ARTS_COLOR_SWITCHER_VERSION__',
  esbuildTarget: 'es2022',
  entry: { ts: './src/ts/boot.ts', sass: './src/styles/index.scss' },
  bundles: [{ name: 'color-switcher-for-elementor-editor', entry: './src/ts/editor/index.ts' }],
  bannerLines: [],
  zip: { budgetMb: 0.5 },
  paths: { php: './src/php', plugin: './src/wordpress-plugin', dist: './dist' },
  // Machine-specific: the Local site's plugin dir, from the gitignored .env (DEV_TARGET)
  devTarget: process.env.DEV_TARGET ?? null,
  vendor: { autoloaderOnly: true, autoloaderSuffix: null },
  // The Playground preview lands on the frontend: the effect is the scroll,
  // and the editor canvas cannot demonstrate it in a first-run screenshot.
  //
  // Deliberately NOT demo-page.php — that one is the mechanism fixture the e2e
  // suite pins (element ids, zone counts, a 50% scrub handle). This is the shop
  // window, free to look like a real page. Header Engine supplies the sticky
  // header: sticky on a container is Elementor Pro, so it is a requirement here
  // rather than a flourish.
  blueprint: {
    seed: './dev/seed/preview-page.php',
    landing: 'front',
    extraPlugins: ['artem-semkin-header-engine-for-elementor']
  }
}
