/**
 * Today's date as YYYY-MM-DD in the user's LOCAL timezone.
 *
 * Note: new Date().toISOString().slice(0, 10) returns the UTC date instead,
 * which is still "yesterday" for an Italian user just after midnight.
 */
export function todayAsLocalDateString(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}
