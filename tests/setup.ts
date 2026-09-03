import { vi } from 'vitest'

// Ship-path by default: dev-only branches stay off; tests that cover them
// flip DEV back on within their own scope.
vi.stubEnv('DEV', false)
