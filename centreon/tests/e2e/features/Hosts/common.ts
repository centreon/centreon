const buildCountHostServicesFromTemplateQuery = (
  hostName: string,
  serviceTemplate: string
): string =>
  'SELECT COUNT(*) AS total FROM host_service_relation hsr ' +
  'JOIN host h ON h.host_id = hsr.host_host_id ' +
  'JOIN service s ON s.service_id = hsr.service_service_id ' +
  'JOIN service st ON st.service_id = s.service_template_model_stm_id ' +
  `WHERE h.host_name = '${hostName}' ` +
  `AND st.service_description = '${serviceTemplate}'`;

const buildHostAliasQuery = (hostName: string): string =>
  `SELECT host_alias FROM host WHERE host_name = '${hostName}' AND host_register = '1'`;

const buildCountHostsQuery = (): string =>
  `SELECT COUNT(*) AS total FROM host WHERE host_register = '1'`;

// The legacy listings remove the markup of a search before using it
// (HtmlAnalyzer::sanitizeAndRemoveTags): the listing filters on that text
const removeMarkup = (text: string): string => text.replace(/<[^>]*>/g, '');

// Element a markup search would create if it were interpreted, e.g. <b>test</b>
const markupElementOf = (
  text: string
): { tag: string; content: string } | null => {
  const match = /^<(\w+)[^>]*>(.*)<\/\1>$/.exec(text.trim());

  return match ? { content: match[2], tag: match[1] } : null;
};

// Messages a legacy page prints when its request fails
const legacyErrorPatterns = [
  /SQLSTATE/,
  /Fatal error/i,
  /Uncaught/,
  /Warning:/,
  /An error occurred/i
];

export {
  buildCountHostServicesFromTemplateQuery,
  buildCountHostsQuery,
  buildHostAliasQuery,
  legacyErrorPatterns,
  markupElementOf,
  removeMarkup
};
