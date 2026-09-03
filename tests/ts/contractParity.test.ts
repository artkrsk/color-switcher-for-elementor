import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

/**
 * README's Integration contract table is the promise; src/ts/constants/*
 * is the code. This holds one to the other: every surface the table names must
 * be declared in the module, and the contract version must agree.
 *
 * One-directional by design — the module may hold internals the table does
 * not advertise, but nothing advertised may be missing or renamed.
 */
const README = readFileSync('README.md', 'utf8')
// The constants barrel is the module side of the contract.
const CONTRACT_TS = ['ATTRIBUTES', 'CLASSES', 'CONTRACT', 'EVENTS', 'PREFERENCE', 'VARIABLES']
  .map((file) => readFileSync(`src/ts/constants/${file}.ts`, 'utf8'))
  .join('\n')

/** Inline-code spans from the contract table's first column. */
const documentedSurfaces = (): string[] => {
  const table = README.split('## Integration contract')[1]?.split('\n## ')[0] ?? ''

  return table
    .split('\n')
    .filter((line) => line.startsWith('| `'))
    .map((line) => line.split('`')[1])
    .filter((name): name is string => !!name)
}

describe('README contract table ↔ contract module', () => {
  it('documents every surface the module exports a name for', () => {
    const surfaces = documentedSurfaces()

    expect(surfaces).toEqual([
      'data-arts-cs',
      'data-arts-cs-baseline',
      'data-arts-cs-pref',
      '--arts-cs-p',
      '--arts-cs-duration',
      'arts-cs:change',
      'arts-cs-morphing',
      'window.ArtsColorSwitcher',
      'arts_cs_pref',
      '[arts_color_switcher_toggle]'
    ])
  })

  it('declares each documented surface in contract.ts', () => {
    for (const surface of documentedSurfaces()) {
      // The global is a shape, not a name; the shortcode is PHP's, below.
      if (surface.startsWith('window.') || surface.startsWith('[')) {
        continue
      }

      expect(CONTRACT_TS).toContain(`'${surface}'`)
    }
  })

  /** The one documented surface with no TS side at all. */
  it('names the shortcode as PHP registers it', () => {
    const php = readFileSync('src/php/Managers/Toggle.php', 'utf8')
    const registered = php.match(/const SHORTCODE\s*=\s*'([^']+)'/)?.[1]

    expect(registered).toBeTruthy()
    expect(documentedSurfaces()).toContain(`[${registered}]`)
  })

  it('documents every method of the public API', () => {
    for (const method of ['refresh()', 'set(theme)', 'getTheme()', 'getProgress()', 'destroy()']) {
      expect(README).toContain(method)
    }
  })

  it('agrees with the module on the contract version', () => {
    const readmeVersion = README.match(/Contract version: \*\*(\d+)\*\*/)?.[1]
    const moduleVersion = CONTRACT_TS.match(/export const CONTRACT = (\d+)/)?.[1]

    expect(readmeVersion).toBeTruthy()
    expect(readmeVersion).toBe(moduleVersion)
  })
})
