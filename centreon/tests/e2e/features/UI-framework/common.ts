interface HostGroupRow {
  hg_activate: string;
  hg_alias: string;
  hg_id: number;
  hg_name: string;
}

const buildHostGroupsByNameQuery = (names: Array<string>): string =>
  `SELECT hg_id, hg_name, hg_alias, hg_activate
    FROM hostgroup
    WHERE hg_name IN (${names.map((name) => `'${name}'`).join(', ')})
    ORDER BY hg_name`;

// "rbt-ui-hg-01" to "rbt-ui-hg-12" from the first and last names
const buildNameRange = (first: string, last: string): Array<string> => {
  const prefix = first.replace(/\d+$/, '');
  const start = Number(first.slice(prefix.length));
  const end = Number(last.slice(prefix.length));
  const width = first.length - prefix.length;

  return Array.from(
    { length: end - start + 1 },
    (_, index) => `${prefix}${String(start + index).padStart(width, '0')}`
  );
};

// Host group listing request: search and sort are JSON in the query string
const decodeListingUrl = (url: string): string =>
  decodeURIComponent(url).replace(/\+/g, ' ');

export {
  buildHostGroupsByNameQuery,
  buildNameRange,
  decodeListingUrl,
  type HostGroupRow
};
