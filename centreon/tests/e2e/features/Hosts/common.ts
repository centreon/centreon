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

export { buildCountHostServicesFromTemplateQuery };
