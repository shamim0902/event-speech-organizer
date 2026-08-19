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

export function wpProfileUrl (username) {
  username = username || ''

  if (username.indexOf('@') > -1) {
    return 'https://profiles.wordpress.org/' + username.split('@')[1]
  } else if (username.indexOf('https://') > -1) {
    return username
  }
  return 'https://profiles.wordpress.org/' + username
}
