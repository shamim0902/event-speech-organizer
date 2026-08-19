/**
 * Search/sort state for the applicant lists, shared between FilterNav
 * (which renders the inputs, next to the status pills) and Speaker (which
 * renders the filtered table). Both components put this object in data(),
 * so Vue makes it reactive and they stay in sync by reference.
 */
export default {
  search: '',
  sortBy: 'newest'
}
