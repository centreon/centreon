import pluralize from 'pluralize';

import { capitalize } from '@mui/material';

export const getResourceTypeName = (resourceType: string): string => {
  const [firstPart, secondPart] = resourceType.split('-');

  if (!secondPart) {
    return pluralize(firstPart);
  }

  return pluralize(`${capitalize(firstPart)} ${secondPart}`);
};

export const formatResourceTypeToCriterias = (resourceType: string): string => {
  const [firstPart, secondPart] = resourceType.split('-');

  if (!secondPart) {
    return pluralize(firstPart);
  }

  return `${firstPart}_${pluralize(secondPart)}`;
};
