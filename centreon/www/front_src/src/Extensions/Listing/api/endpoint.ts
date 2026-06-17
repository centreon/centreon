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
  criteriaSearch: Criteria | undefined;
  criteriaStatus: Criteria | undefined;
  criteriaTypes: Criteria | undefined;
}

const baseEndpoint = './api/internal.php?object=centreon_module&';

const buildEndPoint = ({ action, id, type }: Parameter): string => {
  return `${baseEndpoint}action=${action}&id=${id}&type=${type}`;
};

const buildExtensionEndPoint = ({
  action,
  criteriaSearch,
  criteriaStatus,
  criteriaTypes
}: ParameterWithFilter): string => {
  let params = `${baseEndpoint}action=${action}`;

  const searchValue = criteriaSearch?.value as string;
  if (searchValue) {
    params += `&search=${encodeURIComponent(searchValue)}`;
  }

  if (criteriaTypes?.value) {
    const typeValues = criteriaTypes.value as Array<SelectEntry>;
    typeValues.forEach(({ id }) => {
      params += `&types[]=${(id as string).toLowerCase()}`;
    });
  }

  if (!criteriaStatus?.value) {
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
