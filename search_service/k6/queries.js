/**
 * Query variants for GET /api/search load testing.
 * Each entry is an object of query-string params (k6 http.params).
 */
export const searchQueries = [
  // browse
  { sort: 'new', page: '1' },
  { sort: 'new', page: '1', per_page: '20' },

  // text search
  { q: 'phone' },
  { q: 'jacket' },
  { q: 'laptop' },
  { q: 'shirt', sort: 'relevance' },

  // category
  { category_id: '1' },
  { category_id: '2' },
  { category_id: ['1', '2'] },

  // price range
  { min_price: '100', max_price: '1000' },
  { min_price: '0', max_price: '500' },
  { min_price: '1000', sort: 'price_asc' },

  // stock
  { in_stock: '1' },
  { in_stock: '0' },

  // attributes
  { 'attr[Color]': 'Black' },
  { 'attr[Size]': 'M' },

  // combo
  { q: 'phone', category_id: '1', in_stock: '1', sort: 'price_asc' },
  { q: 'jacket', min_price: '50', max_price: '2000', in_stock: '1' },
  { category_id: '3', 'attr[Color]': 'Black', sort: 'price_desc' },

  // pagination
  { page: '2', per_page: '20', sort: 'new' },
  { page: '3', per_page: '10', q: 'phone' },
];

export function pickQuery(iteration) {
  return searchQueries[iteration % searchQueries.length];
}
