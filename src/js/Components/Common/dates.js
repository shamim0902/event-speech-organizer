/**
 * Date formatting for applicant timestamps, which arrive from MySQL as
 * 'YYYY-MM-DD HH:MM:SS'.
 *
 * Parsing is done by hand rather than with `new Date(string)` — that form is
 * only reliably supported for the ISO 'T' variant, and Safari rejects the
 * space-separated one outright.
 */

const MONTHS = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
]

export function parseDate (value) {
  if (!value) {
    return null
  }

  const parts = String(value).match(
    /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/
  )

  if (!parts) {
    return null
  }

  const date = new Date(
    Number(parts[1]),
    Number(parts[2]) - 1,
    Number(parts[3]),
    Number(parts[4] || 0),
    Number(parts[5] || 0),
    Number(parts[6] || 0)
  )

  return isNaN(date.getTime()) ? null : date
}

/**
 * '19 Aug 2026', dropping the year while it is the current one — the year is
 * noise on a list that is mostly this season's applications.
 */
export function formatDate (value) {
  const date = parseDate(value)

  if (!date) {
    return value || ''
  }

  const label = date.getDate() + ' ' + MONTHS[date.getMonth()]

  return date.getFullYear() === new Date().getFullYear()
    ? label
    : label + ' ' + date.getFullYear()
}

export function formatTime (value) {
  const date = parseDate(value)

  if (!date) {
    return ''
  }

  return pad(date.getHours()) + ':' + pad(date.getMinutes())
}

/**
 * 'Today', 'Yesterday', '5 days ago' — only for the recent past, where it
 * beats reading a date. Older entries return an empty string.
 */
export function formatRelative (value) {
  const date = parseDate(value)

  if (!date) {
    return ''
  }

  const startOfDay = d => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime()
  const days = Math.round((startOfDay(new Date()) - startOfDay(date)) / 86400000)

  if (days < 0) {
    return ''
  }
  if (days === 0) {
    return 'Today'
  }
  if (days === 1) {
    return 'Yesterday'
  }
  if (days < 30) {
    return days + ' days ago'
  }

  return ''
}

/**
 * Long form for tooltips and detail pages: '19 Aug 2026 at 10:56'.
 */
export function formatDateTime (value) {
  const date = parseDate(value)

  if (!date) {
    return value || ''
  }

  return (
    date.getDate() + ' ' + MONTHS[date.getMonth()] + ' ' + date.getFullYear() +
    ' at ' + formatTime(value)
  )
}

function pad (number) {
  return number < 10 ? '0' + number : String(number)
}
