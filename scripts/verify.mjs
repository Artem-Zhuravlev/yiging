#!/usr/bin/env node
// Cross-platform verification pipeline: runs frontend + backend
// lint/typecheck/test/build and fails fast on the first broken step.
// Invoked via `npm run verify` at the repo root.

import { spawnSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const rootDir = path.dirname(path.dirname(fileURLToPath(import.meta.url)))

const npmCmd = 'npm'
const composerCmd = 'composer'

const steps = [
  { label: 'web: lint', cwd: 'apps/web', cmd: npmCmd, args: ['run', 'lint'] },
  { label: 'web: typecheck', cwd: 'apps/web', cmd: npmCmd, args: ['run', 'typecheck'] },
  { label: 'web: test', cwd: 'apps/web', cmd: npmCmd, args: ['run', 'test'] },
  { label: 'web: build', cwd: 'apps/web', cmd: npmCmd, args: ['run', 'build'] },
  { label: 'api: lint (php-cs-fixer)', cwd: 'apps/api', cmd: composerCmd, args: ['lint'] },
  { label: 'api: static analysis (phpstan)', cwd: 'apps/api', cmd: composerCmd, args: ['stan'] },
  { label: 'api: test (phpunit)', cwd: 'apps/api', cmd: composerCmd, args: ['test'] },
  { label: 'yijing-core: test (phpunit)', cwd: 'packages/yijing-core', cmd: composerCmd, args: ['test'] },
]

console.log(`Running ${steps.length} verification steps...\n`)

for (const step of steps) {
  const cwd = path.join(rootDir, step.cwd)

  if (!existsSync(cwd)) {
    console.error(`✗ ${step.label} — directory not found: ${step.cwd}`)
    process.exit(1)
  }

  console.log(`→ ${step.label}`)
  const result = spawnSync(step.cmd, step.args, { cwd, stdio: 'inherit', shell: true })

  if (result.error) {
    console.error(`\n✗ ${step.label} failed to start: ${result.error.message}`)
    process.exit(1)
  }

  if (result.status !== 0) {
    console.error(`\n✗ ${step.label} failed. Verification stopped.`)
    process.exit(result.status ?? 1)
  }
}

console.log('\n✓ All verification steps passed.')
