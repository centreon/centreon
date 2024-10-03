import { faker } from '@faker-js/faker';

import { SelectEntry } from '../../..';
import { Listing } from '../../../api/models';

import { AccessRightInitialValues, Labels } from './models';

faker.seed(42);

export const defaultAccessRights: Array<AccessRightInitialValues> = Array(10)
  .fill(0)
  .map((_, idx) => ({
    email: faker.internet.email(),
    id: idx,
    isContactGroup: idx % 5 === 0,
    name: faker.person.fullName(),
    role: idx % 2 === 0 ? 'viewer' : 'editor'
  }));

export const simpleAccessRights: Array<AccessRightInitialValues> = Array(6)
  .fill(0)
  .map((_, idx) => ({
    email: faker.internet.email(),
    id: idx,
    isContactGroup: idx % 5 === 0,
    name: faker.person.fullName(),
    role: idx % 2 === 0 ? 'viewer' : 'editor'
  }));

export const accessRightsWithStates = [
  {
    email: faker.internet.email(),
    id: 1,
    isAdded: false,
    isContactGroup: true,
    isRemoved: false,
    isUpdated: false,
    name: faker.person.fullName(),
    role: 'viewer'
  },
  {
    email: faker.internet.email(),
    id: 2,
    isAdded: true,
    isContactGroup: true,
    isRemoved: false,
    isUpdated: false,
    name: faker.person.fullName(),
    role: 'editor'
  },
  {
    email: faker.internet.email(),
    id: 3,
    isAdded: false,
    isContactGroup: false,
    isRemoved: false,
    isUpdated: true,
    name: faker.person.fullName(),
    role: 'viewer'
  },
  {
    email: faker.internet.email(),
    id: 4,
    isAdded: false,
    isContactGroup: false,
    isRemoved: true,
    isUpdated: false,
    name: faker.person.fullName(),
    role: 'editor'
  },
  {
    email: faker.internet.email(),
    id: 5,
    isAdded: false,
    isContactGroup: true,
    isRemoved: true,
    isUpdated: true,
    name: faker.person.fullName(),
    role: 'viewer'
  }
];

export const emptyAccessRights = [];

const buildEntities = (from, isGroup): Array<SelectEntry> => {
  return Array(10)
    .fill(0)
    .map((_, index) => ({
      email: isGroup ? undefined : faker.internet.email(),
      id: 1000 + index,
      most_permissive_role: index % 3 === 0 ? 'editor' : 'viewer',
      name: `Entity ${isGroup ? 'Group' : ''} ${from + index}`
    }));
};

export const buildResult = (isGroup): Listing<SelectEntry> => ({
  meta: {
    limit: 10,
    page: 1,
    total: 10
  },
  result: buildEntities(10, isGroup)
});

export const labels: Labels = {
  actions: {
    cancel: 'Cancel',
    save: 'Save'
  },
  add: {
    autocompleteContact: 'Add a contact',
    autocompleteContactGroup: 'Add a contact group',
    contact: 'Contact',
    contactGroup: 'Contact group',
    title: 'Share dashboard with'
  },
  list: {
    added: 'Added',
    empty: 'The contact list is empty',
    group: 'Group',
    removed: 'Removed',
    title: 'User rights',
    updated: 'Updated'
  }
};

export const roles = [
  {
    id: 'viewer',
    name: 'Viewer'
  },
  {
    id: 'editor',
    name: 'Editor'
  }
];

export const removedAccessRights = [
  {
    email: 'Adrienne.Kassulke-Rutherford@gmail.com',
    id: 1,
    isContactGroup: false,
    name: 'Linda Schultz',
    role: 'editor'
  },
  {
    email: 'Merle7@hotmail.com',
    id: 2,
    isContactGroup: false,
    name: 'Lewis Buckridge PhD',
    role: 'viewer'
  },
  {
    email: 'Linda.Harris37@hotmail.com',
    id: 3,
    isContactGroup: false,
    name: "Jodi O'Reilly",
    role: 'editor'
  },
  {
    email: 'Louvenia.Torphy@yahoo.com',
    id: 4,
    isContactGroup: false,
    name: 'Mildred Ratke-Stanton',
    role: 'viewer'
  },
  {
    email: 'Kelli.Russel4@hotmail.com',
    id: 5,
    isContactGroup: true,
    name: 'Rudolph Brown',
    role: 'editor'
  }
];

export const updatedAccessRights = [
  {
    email: 'Kylie_Wintheiser54@hotmail.com',
    id: 0,
    isContactGroup: true,
    name: 'Kathy Schmitt',
    role: 'editor'
  },
  {
    email: 'Adrienne.Kassulke-Rutherford@gmail.com',
    id: 1,
    isContactGroup: false,
    name: 'Linda Schultz',
    role: 'editor'
  },
  {
    email: 'Merle7@hotmail.com',
    id: 2,
    isContactGroup: false,
    name: 'Lewis Buckridge PhD',
    role: 'viewer'
  },
  {
    email: 'Linda.Harris37@hotmail.com',
    id: 3,
    isContactGroup: false,
    name: "Jodi O'Reilly",
    role: 'editor'
  },
  {
    email: 'Louvenia.Torphy@yahoo.com',
    id: 4,
    isContactGroup: false,
    name: 'Mildred Ratke-Stanton',
    role: 'viewer'
  },
  {
    email: 'Kelli.Russel4@hotmail.com',
    id: 5,
    isContactGroup: true,
    name: 'Rudolph Brown',
    role: 'editor'
  }
];
