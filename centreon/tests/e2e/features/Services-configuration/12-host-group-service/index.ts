import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import serviceCategories from '../../../fixtures/services/category.json';
import data from '../../../fixtures/services/host_group.json';
import servicesData from '../../../fixtures/services/service.json';
import htmldata from './data.json';

const services = {
  serviceCritical: {
    host: 'host3',
    name: 'service3',
    template: 'SNMP-Linux-Load-Average'
  },
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' },
  serviceWarning: {
    host: 'host2',
    name: 'service2',
    template: 'SNMP-Linux-Memory'
  }
};

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod&action=list*'
  }).as('getTimePeriods');
});

after(() => {
  cy.stopContainers();
});

Given('a user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('some service groups are configured', () => {
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceOk.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceOk.name,
      template: services.serviceOk.template
    })
    .addServiceGroup({
      hostsAndServices: [[services.serviceOk.host, services.serviceOk.name]],
      name: servicesData.service_group.service2.name
    })
    .applyPollerConfiguration();

  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceCritical.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceCritical.host,
      maxCheckAttempts: 1,
      name: services.serviceWarning.name,
      template: services.serviceWarning.template
    })
    .addServiceGroup({
      hostsAndServices: [
        [services.serviceCritical.host, services.serviceWarning.name]
      ],
      name: servicesData.service_group.service1.name
    })
    .applyPollerConfiguration();
});

Given('some service categories are configured', () => {
  cy.addSubjectViaApiV2(
    serviceCategories.default,
    '/centreon/api/latest/configuration/services/categories'
  );
});

When(
  'the user goes to Configuration > Services > Services by host group',
  () => {
    cy.navigateTo({
      page: 'Services by host group',
      rootItemNumber: 3,
      subMenu: 'Services'
    });
    cy.wait('@getTimeZone');
  }
);

When('the user Add a new host group service', () => {
  cy.waitForElementInIframe('#main-content', 'a:contains("Add")');
  cy.getIframeBody().contains('a', 'Add').eq(0).click();
  cy.wait('@getTimeZone');
  cy.createOrUpdateHostGroupService(
    {
      ...data.default,
      actionUrl: data.default.actionurl,
      atlIcon: data.default.atlicon,
      checkCommand: data.default.checkcommand,
      checkPeriod: data.default.checkperiod,
      contactGroups: data.default.contactgroups,
      firstNotificationDelay: data.default.firstnotdelay,
      freshnessThreshold: data.default.freshnessthreshold,
      geoCoords: data.default.geocoords,
      geoCoordsTruncated: data.hostgroupservice.geoCoordsTruncated,
      hostGroups: data.default.hostgroups,
      macroName: data.default.macroname,
      macroValue: data.default.macrovalue,
      maxCheckAttempts: data.default.maxcheckattempts,
      normalCheckInterval: data.default.normalcheckinterval,
      noteUrl: data.default.noteurl,
      notificationInterval: data.default.notinterval,
      notificationPeriod: data.default.notificationperiod,
      recoveryNotificationDelay: data.default.recoverynotdelay,
      retryCheckInterval: data.default.retrycheckinterval,
      serviceCategories: data.default.servicecategories,
      serviceGroups: data.default.servicegroups,
      serviceTrap: data.default.servicetrap
    },
    false,
    htmldata.dataForCreation.map((elt) => ({
      ...elt,
      valueOrIndex: String(elt.valueOrIndex)
    }))
  );
});

Then('the host group service is added to the listing page', () => {
  cy.getIframeBody().contains('a', data.default.name).should('exist');
});

Given('a host group service is configured', () => {
  cy.navigateTo({
    page: 'Services by host group',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'a:contains("Add")');
  cy.getIframeBody().contains('a', data.default.name).should('exist');
});

When('the user changes the properties of the host group service', () => {
  cy.getIframeBody().contains('a', data.default.name).click();
  cy.createOrUpdateHostGroupService(
    {
      ...data.hostgroupservice,
      actionUrl: data.hostgroupservice.actionurl,
      atlIcon: data.hostgroupservice.atlicon,
      checkCommand: data.hostgroupservice.checkcommand,
      checkPeriod: data.hostgroupservice.checkperiod,
      contactGroups: data.hostgroupservice.contactgroups,
      firstNotificationDelay: data.hostgroupservice.firstnotdelay,
      freshnessThreshold: data.hostgroupservice.freshnessthreshold,
      geoCoords: data.hostgroupservice.geocoords,
      geoCoordsTruncated: data.hostgroupservice.geoCoordsTruncated,
      hostGroups: data.hostgroupservice.hostgroups,
      macroName: data.hostgroupservice.macroname,
      macroValue: data.hostgroupservice.macrovalue,
      maxCheckAttempts: data.hostgroupservice.maxcheckattempts,
      normalCheckInterval: data.hostgroupservice.normalcheckinterval,
      noteUrl: data.hostgroupservice.noteurl,
      notificationInterval: data.hostgroupservice.notinterval,
      notificationPeriod: data.hostgroupservice.notificationperiod,
      recoveryNotificationDelay: data.hostgroupservice.recoverynotdelay,
      retryCheckInterval: data.hostgroupservice.retrycheckinterval,
      serviceCategories: data.hostgroupservice.servicecategories,
      serviceGroups: data.hostgroupservice.servicegroups,
      serviceTrap: data.hostgroupservice.servicetrap
    },
    true,
    htmldata.dataForUpdate
  );
});

Then('the properties are updated', () => {
  cy.checkValuesOfHostGroupService(data.hostgroupservice.name, {
    ...data.hostgroupservice,
    actionUrl: data.hostgroupservice.actionurl,
    atlIcon: data.hostgroupservice.atlicon,
    checkCommand: data.hostgroupservice.checkcommand,
    checkPeriod: data.hostgroupservice.checkperiod,
    contactGroups: data.hostgroupservice.contactgroups,
    firstNotificationDelay: data.hostgroupservice.firstnotdelay,
    freshnessThreshold: data.hostgroupservice.freshnessthreshold,
    geoCoords: data.hostgroupservice.geocoords,
    geoCoordsTruncated: data.hostgroupservice.geoCoordsTruncated,
    hostGroups: data.hostgroupservice.hostgroups,
    macroName: data.hostgroupservice.macroname,
    macroValue: data.hostgroupservice.macrovalue,
    maxCheckAttempts: data.hostgroupservice.maxcheckattempts,
    normalCheckInterval: data.hostgroupservice.normalcheckinterval,
    noteUrl: data.hostgroupservice.noteurl,
    notificationInterval: data.hostgroupservice.notinterval,
    notificationPeriod: data.hostgroupservice.notificationperiod,
    recoveryNotificationDelay: data.hostgroupservice.recoverynotdelay,
    retryCheckInterval: data.hostgroupservice.retrycheckinterval,
    serviceCategories: data.hostgroupservice.servicecategories,
    serviceGroups: data.hostgroupservice.servicegroups,
    serviceTrap: data.hostgroupservice.servicetrap
  });
});

When('the user duplicates the host group service', () => {
  cy.navigateTo({
    page: 'Services by host group',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.checkFirstRowFromListing('hostgroups');
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the new duplicated host group service has the same properties', () => {
  cy.checkValuesOfHostGroupService(`${data.hostgroupservice.name}_1`, {
    ...data.hostgroupservice,
    actionUrl: data.hostgroupservice.actionurl,
    atlIcon: data.hostgroupservice.atlicon,
    checkCommand: data.hostgroupservice.checkcommand,
    checkPeriod: data.hostgroupservice.checkperiod,
    contactGroups: data.hostgroupservice.contactgroups,
    firstNotificationDelay: data.hostgroupservice.firstnotdelay,
    freshnessThreshold: data.hostgroupservice.freshnessthreshold,
    geoCoords: data.hostgroupservice.geocoords,
    geoCoordsTruncated: data.hostgroupservice.geoCoordsTruncated,
    hostGroups: data.hostgroupservice.hostgroups,
    macroName: data.hostgroupservice.macroname,
    macroValue: data.hostgroupservice.macrovalue,
    maxCheckAttempts: data.hostgroupservice.maxcheckattempts,
    normalCheckInterval: data.hostgroupservice.normalcheckinterval,
    noteUrl: data.hostgroupservice.noteurl,
    notificationInterval: data.hostgroupservice.notinterval,
    notificationPeriod: data.hostgroupservice.notificationperiod,
    recoveryNotificationDelay: data.hostgroupservice.recoverynotdelay,
    retryCheckInterval: data.hostgroupservice.retrycheckinterval,
    serviceCategories: data.hostgroupservice.servicecategories,
    serviceGroups: data.hostgroupservice.servicegroups,
    serviceTrap: data.hostgroupservice.servicetrap
  });
});

When('the user deletes the host group service', () => {
  cy.navigateTo({
    page: 'Services by host group',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.checkFirstRowFromListing('hostgroups');
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted host group service is not displayed in the list', () => {
  cy.getIframeBody().find('a[href*="service_id=29"]').should('not.exist');
});
