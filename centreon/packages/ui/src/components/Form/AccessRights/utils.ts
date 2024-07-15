import { omit } from 'ramda';

import { AccessRight, AccessRightInitialValues } from './models';

export const formatValue = (
  accessRight: AccessRight
): AccessRightInitialValues => {
  return omit(['isAdded', 'isUpdated', 'isRemoved'], accessRight);
};

export const formatValueForSubmition = (
  accessRight: AccessRight
): AccessRightInitialValues => {
  return {
    ...formatValue(accessRight),
    id: Number((accessRight.id as string).split('_')[1])
  };
};
