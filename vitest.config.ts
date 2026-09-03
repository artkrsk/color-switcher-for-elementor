import { createVitestConfig } from '@arts/wp-plugin-tooling/vitest'
import { defineConfig } from 'vitest/config'

export default defineConfig(createVitestConfig({ defineKey: '__ARTS_COLOR_SWITCHER_VERSION__' }))
