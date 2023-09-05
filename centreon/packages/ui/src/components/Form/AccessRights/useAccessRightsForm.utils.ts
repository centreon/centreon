import { ContactAccessRightStateResource } from './AccessRights.resource';

export const sortOnContactName = (
  a: ContactAccessRightStateResource,
  b: ContactAccessRightStateResource
): number =>
  a.contactAccessRight.contact?.name.localeCompare(
    b.contactAccessRight.contact?.name ?? ''
  ) || 0;

export const sortOnAddedStateFirstAndContactName = (
  a: ContactAccessRightStateResource,
  b: ContactAccessRightStateResource
): number => {
  if (a.state === 'added' && b.state !== 'added') {
    return -1;
  }
  if (a.state !== 'added' && b.state === 'added') {
    return 1;
  }

  return (
    a.contactAccessRight.contact?.name.localeCompare(
      b.contactAccessRight.contact?.name ?? ''
    ) || 0
  );
};

export const createInitialState = (
  values
): Array<ContactAccessRightStateResource> =>
  values
    ?.map(
      (contactAccessRight) =>
        ({
          contactAccessRight,
          state: 'unchanged',
          stateHistory: []
        } as ContactAccessRightStateResource)
    )
    .sort(sortOnAddedStateFirstAndContactName);
