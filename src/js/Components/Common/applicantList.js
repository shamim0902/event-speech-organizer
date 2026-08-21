/**
 * Ordering rules for applicant lists, shared by the table (Speaker.vue) and
 * the single applicant page's prev/next navigation — so stepping through
 * applicants follows exactly the order the table showed.
 */

const STATUS_ORDER = { approved: 0, waiting: 1, rejected: 2 }

// Which statuses each list route displays. `null` means "everything".
const SECTION_STATUSES = {
  applicants: null,
  selected: ['approved'],
  waiting: ['waiting'],
  rejected: ['rejected']
}

export function statusesForSection (section) {
  return Object.prototype.hasOwnProperty.call(SECTION_STATUSES, section)
    ? SECTION_STATUSES[section]
    : null
}

export function filterAndSort (applicants, listState, statuses) {
  const query = (listState.search || '').trim().toLowerCase()

  const list = applicants.filter(applicant => {
    if (statuses && statuses.indexOf(applicant.status) === -1) {
      return false
    }
    if (!query) {
      return true
    }
    return ['name', 'email', 'topic'].some(field => {
      return (applicant[field] || '').toLowerCase().indexOf(query) > -1
    })
  })

  if (listState.sortBy === 'name') {
    list.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
  } else if (listState.sortBy === 'status') {
    list.sort((a, b) => {
      const rankA = STATUS_ORDER[a.status] !== undefined ? STATUS_ORDER[a.status] : 3
      const rankB = STATUS_ORDER[b.status] !== undefined ? STATUS_ORDER[b.status] : 3
      return rankA - rankB
    })
  } else {
    list.sort((a, b) => Number(b.id) - Number(a.id))
  }

  return list
}
