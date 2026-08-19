var md5 = require('md5')

/**
 * Helpers shared by the applicant table and the single applicant page.
 */

export function gravatarUrl (email, size) {
  email = email || 'john.doe@example.com'
  size = size >= 1 && size <= 2048 ? size : 80

  return (
    'https://secure.gravatar.com/avatar/' +
    md5(email.toLowerCase().trim()) +
    '?size=' + size + '&default=mm&rating=g'
  )
}

/**
 * Applicant ids assigned to any slot of an event, as strings. Slots may also
 * hold free-typed speaker names; those simply never match an id.
 *
 * @param request the caller's $get mixin method
 */
export function loadAssignedSpeakerIds (request, eventId) {
  return request({
    action: 'event_speech_organizer_admin_ajax',
    route: 'get_slots',
    event_id: eventId
  }).then(response => {
    const ids = []

    const slots = (response && response.data) || []
    slots.forEach(slot => {
      (slot.speakers || []).forEach(speaker => ids.push(String(speaker)))
    })

    return ids
  })
}

export function wpProfileUrl (username) {
  username = username || ''

  if (username.indexOf('@') > -1) {
    return 'https://profiles.wordpress.org/' + username.split('@')[1]
  } else if (username.indexOf('https://') > -1) {
    return username
  }
  return 'https://profiles.wordpress.org/' + username
}
