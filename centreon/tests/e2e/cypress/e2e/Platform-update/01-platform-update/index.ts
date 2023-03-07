import { Given } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkIfSystemUserRoot,
  setDatabaseUserRootDefaultCredentials,
  setUserAdminDefaultCredentials
} from '../common';

Given('an admin user with valid non-default credentials', () => {
  setUserAdminDefaultCredentials();
});

Given('a database root user with valid non-default credentials', () => {
  setDatabaseUserRootDefaultCredentials();
});

Given('a system user root', () => {
  checkIfSystemUserRoot();
});
