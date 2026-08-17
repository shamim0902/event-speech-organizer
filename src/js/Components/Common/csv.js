/**
 * Minimal RFC-4180 CSV helpers. Written by hand rather than pulled in as a
 * dependency: the plugin only needs parse + serialise, and the build has no
 * other runtime deps beyond Vue/Element.
 */

/**
 * Columns an import can populate, in the order they appear in the export.
 * `aliases` are additional header spellings that auto-map to the field.
 */
export const IMPORT_FIELDS = [
  { key: 'name', label: 'Name', required: true, aliases: ['fullname', 'speakername', 'applicant'] },
  { key: 'email', label: 'Email', required: true, aliases: ['emailaddress', 'mail'] },
  { key: 'phone', label: 'Phone', aliases: ['phonenumber', 'mobile', 'contact'] },
  { key: 'username', label: 'WordPress.org username', aliases: ['wp', 'wpusername', 'wordpressusername', 'wporg'] },
  { key: 'social', label: 'Social handles', aliases: ['socials', 'socialhandle', 'socialmedia', 'twitter'] },
  { key: 'comment', label: 'Bio', aliases: ['bio', 'biography', 'about', 'speakerbio'] },
  { key: 'topic', label: 'Topic title', aliases: ['talktitle', 'title', 'talk', 'session', 'sessiontitle'] },
  { key: 'type', label: 'Talk type', aliases: ['talktype', 'sessiontype', 'format'] },
  { key: 'description', label: 'Description', aliases: ['talkdescription', 'abstract', 'details'] },
  { key: 'cospeakers', label: 'Co-speakers', aliases: ['cospeaker', 'copresenters', 'panelists'] },
  { key: 'audience', label: 'Audience', aliases: ['targetaudience', 'audiencelevel'] },
  { key: 'experience', label: 'Experience', aliases: ['speakingexperience', 'priorexperience'] },
  { key: 'question', label: 'Question', aliases: ['questions', 'notes'] },
  { key: 'status', label: 'Status', aliases: ['applicationstatus', 'state'] },
  { key: 'date', label: 'Application date', aliases: ['applieddate', 'appliedat', 'applicationdate', 'submitted'] }
]

const normalize = value => String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '')

/**
 * Parse CSV text into an array of string arrays.
 * Handles quoted fields, embedded commas/newlines, doubled quotes and a BOM.
 */
export function parseCsv (text) {
  let input = String(text || '')

  if (input.charCodeAt(0) === 0xfeff) {
    input = input.slice(1)
  }

  const rows = []
  let row = []
  let field = ''
  let inQuotes = false
  let i = 0

  while (i < input.length) {
    const char = input[i]

    if (inQuotes) {
      if (char === '"') {
        if (input[i + 1] === '"') {
          field += '"'
          i += 2
          continue
        }
        inQuotes = false
        i++
        continue
      }
      field += char
      i++
      continue
    }

    if (char === '"') {
      inQuotes = true
      i++
      continue
    }

    if (char === ',') {
      row.push(field)
      field = ''
      i++
      continue
    }

    if (char === '\r') {
      i++
      continue
    }

    if (char === '\n') {
      row.push(field)
      rows.push(row)
      row = []
      field = ''
      i++
      continue
    }

    field += char
    i++
  }

  if (field !== '' || row.length) {
    row.push(field)
    rows.push(row)
  }

  // Drop rows that are entirely blank (trailing newlines, spacer rows).
  return rows.filter(cells => cells.some(cell => cell.trim() !== ''))
}

/**
 * Build a mapping of field key => column index by matching header names.
 * Unmatched fields map to -1 ("ignore this field").
 */
export function autoMapColumns (headers) {
  const normalized = headers.map(normalize)
  const mapping = {}

  IMPORT_FIELDS.forEach(field => {
    const candidates = [field.key, normalize(field.label)].concat(field.aliases || [])
    let index = -1

    for (let i = 0; i < candidates.length && index === -1; i++) {
      index = normalized.indexOf(normalize(candidates[i]))
    }

    mapping[field.key] = index
  })

  return mapping
}

/**
 * Turn parsed rows into column => value objects using the given mapping.
 */
export function applyMapping (rows, mapping) {
  return rows.map(cells => {
    const record = {}

    IMPORT_FIELDS.forEach(field => {
      const index = mapping[field.key]
      record[field.key] = index > -1 && cells[index] !== undefined ? String(cells[index]).trim() : ''
    })

    return record
  })
}

/**
 * Serialise records to CSV text, quoting every cell so the output round-trips
 * back through parseCsv().
 */
export function toCsv (fields, records) {
  const escape = value => '"' + String(value === null || value === undefined ? '' : value).replace(/"/g, '""') + '"'

  const lines = [fields.map(field => escape(field.label)).join(',')]

  records.forEach(record => {
    lines.push(fields.map(field => escape(record[field.key])).join(','))
  })

  return lines.join('\r\n') + '\r\n'
}
