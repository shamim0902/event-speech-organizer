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
  // `names` is the field name Fluent Forms always gives its input_name element.
  { key: 'name', label: 'Name', required: true, aliases: ['names', 'fullname', 'speakername', 'applicant'] },
  { key: 'email', label: 'Email', required: true, aliases: ['emailaddress', 'mail'] },
  { key: 'phone', label: 'Phone', aliases: ['phonenumber', 'mobile', 'contact'] },
  { key: 'username', label: 'WordPress.org username', aliases: ['wp', 'wpusername', 'wordpressusername', 'wporg'] },
  { key: 'social', label: 'Social handles', aliases: ['socials', 'socialhandle', 'socialmedia', 'twitter', 'website', 'url', 'link'] },
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
 * Describe CSV headers as generic source columns, so CSV and Fluent Forms
 * share one mapping UI. `value` is the column index for CSV and the flattened
 * response key for Fluent Forms.
 */
export function columnsFromHeaders (headers, firstRow) {
  return headers.map((header, index) => {
    const sample = firstRow && firstRow[index] !== undefined ? String(firstRow[index]).trim() : ''

    return {
      value: index,
      label: header || 'Column ' + (index + 1),
      sample: sample.length > 60 ? sample.slice(0, 60) + '…' : sample
    }
  })
}

/**
 * Match source columns onto applicant fields by name. Compares each column's
 * label *and* its raw value against every field's key, label and aliases, so
 * it works for CSV headers ("Talk Title") and Fluent Forms keys ("names")
 * alike. Unmatched fields map to '' — the "ignore" sentinel. Note '' is used
 * rather than -1 because CSV column index 0 is itself a valid target.
 */
export function autoMapColumns (sourceColumns) {
  const mapping = {}
  const taken = {}

  IMPORT_FIELDS.forEach(field => {
    const candidates = [field.key, field.label].concat(field.aliases || []).map(normalize)
    let match = ''

    for (let i = 0; i < sourceColumns.length && match === ''; i++) {
      const column = sourceColumns[i]

      if (taken[String(column.value)]) {
        continue
      }

      const identifiers = [normalize(column.label), normalize(column.value)]

      if (identifiers.some(id => id !== '' && candidates.indexOf(id) > -1)) {
        match = column.value
        taken[String(column.value)] = true
      }
    }

    mapping[field.key] = match
  })

  return mapping
}

/**
 * Turn parsed CSV rows into column => value objects using the given mapping.
 */
export function applyMapping (rows, mapping) {
  return rows.map(cells => {
    const record = {}

    IMPORT_FIELDS.forEach(field => {
      const index = mapping[field.key]
      const hasValue = index !== '' && index !== undefined && cells[index] !== undefined
      record[field.key] = hasValue ? String(cells[index]).trim() : ''
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
