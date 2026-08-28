import type { Consultation } from '../../entities/consultation/model'

type Translate = (key: string, named?: Record<string, unknown>) => string

/**
 * Renders one consultation's own data as Markdown for pasting into a notebook (SPEC-051).
 * Pure — takes the i18n `t` so section labels are localised without this module depending on
 * vue-i18n. Deliberately excludes any AI interpretation (never persisted; SPEC-008).
 */
export function consultationToMarkdown(consultation: Consultation, t: Translate): string {
  const date = (iso: string): string => new Date(iso).toLocaleDateString()
  const lines: string[] = []

  lines.push(`# ${consultation.question}`, '')
  lines.push(`${consultation.method} · ${date(consultation.createdAt)}`, '')

  const p = consultation.primaryHexagram
  const r = consultation.resultingHexagram
  lines.push(`**${t('markdown.primary')}:** ${p.kingWenNumber}. ${p.chineseName} (${p.pinyin})`)
  lines.push(`**${t('markdown.resulting')}:** ${r.kingWenNumber}. ${r.chineseName} (${r.pinyin})`)
  lines.push(
    consultation.changingLinePositions.length > 0
      ? `**${t('markdown.changingLines')}:** ${consultation.changingLinePositions.join(', ')}`
      : `**${t('markdown.changingLines')}:** ${t('markdown.noChangingLines')}`,
  )

  if (consultation.notes.length > 0) {
    lines.push('', `## ${t('markdown.notes')}`, '')
    for (const note of consultation.notes) {
      lines.push(`- **${t(`markdown.noteLabel.${note.label}`)}:** ${note.text}`)
    }
  }

  if (consultation.tags.length > 0) {
    lines.push('', `**${t('markdown.tags')}:** ${consultation.tags.join(', ')}`)
  }

  const contextRows: [string, string | null][] = [
    [t('markdown.contextField.context'), consultation.context],
    [t('markdown.contextField.whatHappenedBefore'), consultation.whatHappenedBefore],
    [t('markdown.contextField.whatUserWantsToUnderstand'), consultation.whatUserWantsToUnderstand],
    [t('markdown.contextField.backgroundInformation'), consultation.backgroundInformation],
    [t('markdown.contextField.initialInterpretation'), consultation.initialInterpretation],
  ]
  const filledContext = contextRows.filter((row): row is [string, string] => Boolean(row[1] && row[1].trim()))
  if (filledContext.length > 0) {
    lines.push('', `## ${t('markdown.context')}`, '')
    for (const [label, value] of filledContext) {
      lines.push(`- **${label}:** ${value}`)
    }
  }

  const outcome = consultation.outcome
  if (outcome) {
    lines.push('', `## ${t('markdown.outcome')}`, '')
    const outcomeRows: [string, string | null][] = [
      [t('markdown.whatHappened'), outcome.whatActuallyHappened],
      [t('markdown.outcomeField'), outcome.outcome],
      [t('markdown.reflection'), outcome.reflection],
    ]
    for (const [label, value] of outcomeRows) {
      if (value && value.trim()) lines.push(`- **${label}:** ${value}`)
    }
    lines.push('', `_${t('markdown.recorded', { date: date(outcome.recordedAt) })}_`)
  }

  const followUps = consultation.followUps
  if (consultation.followUpTo || followUps.length > 0) {
    lines.push('', `## ${t('markdown.followUp')}`, '')
    if (consultation.followUpTo) {
      lines.push(t('markdown.followsUpOn', { question: consultation.followUpTo.question }), '')
    }
    for (const followUp of followUps) {
      lines.push(`- ${followUp.question}`)
    }
  }

  lines.push('', '---', '', `_${t('markdown.exportedOn', { date: new Date().toLocaleDateString() })}_`)

  return lines.join('\n')
}

/** A filename-safe slug from the question, ~40 chars, falling back to `consultation`. */
export function slugifyForFilename(question: string): string {
  const slug = question
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 40)
    .replace(/-$/, '')

  return slug || 'consultation'
}
