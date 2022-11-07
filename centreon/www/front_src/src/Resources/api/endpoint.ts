<<<<<<< HEAD
import { baseEndpoint } from '../../api/endpoint';
=======
const baseEndpoint = './api/latest';
>>>>>>> centreon/dev-21.10.x

const monitoringEndpoint = `${baseEndpoint}/monitoring`;
const resourcesEndpoint = `${monitoringEndpoint}/resources`;
const userEndpoint =
  './api/internal.php?object=centreon_topcounter&action=user';

<<<<<<< HEAD
export { monitoringEndpoint, resourcesEndpoint, userEndpoint };
=======
export { baseEndpoint, monitoringEndpoint, resourcesEndpoint, userEndpoint };
>>>>>>> centreon/dev-21.10.x
