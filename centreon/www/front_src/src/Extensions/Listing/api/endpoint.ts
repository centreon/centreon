import { find, propEq } from 'ramda';

import type { SelectEntry } from '@centreon/ui';

import { Criteria } from '../../Filter/Criterias/models';

interface Parameter {
  action: string;
  id: string;
  type: string;
}

interface ParameterWithFilter {
  action: string;
  criteriaStatus: Criteria | undefined;
}

const baseEndpoint = './api/internal.php?object=centreon_module&';

const buildEndPoint = ({ action, id, type }: Parameter): string => {
  return `${baseEndpoint}action=${action}&id=${id}&type=${type}`;
};

const buildExtensionEndPoint = ({
  action,
  criteriaStatus
}: ParameterWithFilter): string => {
  let params = `${baseEndpoint}action=${action}`;

  if (!criteriaStatus || !criteriaStatus.value) {
    return params;
  }

  const values = criteriaStatus.value as Array<SelectEntry>;

  const installed = !!find(propEq('INSTALLED', 'id'), values);
  const uninstalled = !!find(propEq('UNINSTALLED', 'id'), values);
  const upToDate = !!find(propEq('UPTODATE', 'id'), values);
  const outdated = !!find(propEq('OUTDATED', 'id'), values);

  if (!upToDate && outdated) {
    params += '&updated=false';
  } else if (upToDate && !outdated) {
    params += '&updated=true';
  }

  if (!installed && uninstalled) {
    params += '&installed=false';
  } else if (installed && !uninstalled) {
    params += '&installed=true';
  }

  return params;
};

export { buildEndPoint, buildExtensionEndPoint };
