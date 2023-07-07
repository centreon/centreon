import { faker } from '@faker-js/faker';

import {
  ContactAccessRightResource,
  ContactGroupResource,
  ContactResource,
  RoleResource
} from '../AccessRights.resource';

export const rolesMock = (): Array<RoleResource> => [
  { role: 'viewer' },
  { role: 'editor' }
];

export const contactMock = (): ContactResource => ({
  email: faker.internet.email(),
  id: faker.string.uuid(),
  name: faker.person.fullName()
});

export const contactsMock = (length: number): Array<ContactResource> =>
  [...Array(length).keys()].map(contactMock);

export const contactGroupMock = (): ContactGroupResource => ({
  id: faker.string.uuid(),
  name: faker.company.name()
});
export const contactGroupsMock = (
  length: number
): Array<ContactGroupResource> =>
  [...Array(length).keys()].map(contactGroupMock);

export const contactsAndGroupsMock = (
  length: number
): Array<ContactResource | ContactGroupResource> =>
  [...Array(length).keys()].map(
    () =>
      faker.helpers.maybe(contactGroupMock, { probability: 0.2 }) ??
      contactMock()
  );

export const contactAccessRightsMock = (
  length: number
): Array<ContactAccessRightResource> =>
  [...Array(length).keys()].map(() => ({
    contact:
      faker.helpers.maybe(contactGroupMock, { probability: 0.2 }) ??
      contactMock(),
    role: faker.helpers.arrayElement(rolesMock().map(({ role }) => role))
  }));
