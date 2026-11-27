export const INTERCEPTORS = {
  api: {
    navigation_list:
      '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  },
  awie: {
    generateExport: '/centreon/modules/centreon-awie/core/generateExport.php',
    submitExport: '/centreon/modules/centreon-awie/core/submitExport.php'
  }
};
