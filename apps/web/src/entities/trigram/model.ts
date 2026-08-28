/** One of the eight trigrams as returned by `GET /api/trigrams` (SPEC-049). The `direction` is
 * the Later Heaven (King Wen) compass placement; the string attribute values (name, image,
 * element, familyMember, direction) come from the API in English and are shown as-is. */
export interface Trigram {
  id: string
  name: string
  chineseName: string
  pinyin: string
  symbol: string
  element: string
  familyMember: string
  direction: string
  image: string
}
