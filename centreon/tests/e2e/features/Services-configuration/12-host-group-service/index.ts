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
      hostGroups: data.default.hostgroups,
      checkCommand: data.default.checkcommand,
      macroName: data.default.macroname,
      macroValue: data.default.macrovalue,
      checkPeriod: data.default.checkperiod,
      maxCheckAttempts: data.default.maxcheckattempts,
      normalCheckInterval: data.default.normalcheckinterval,
      retryCheckInterval: data.default.retrycheckinterval,
      contactGroups: data.default.contactgroups,
      notificationInterval: data.default.notinterval,
      notificationPeriod: data.default.notificationperiod,
      firstNotificationDelay: data.default.firstnotdelay,
      recoveryNotificationDelay: data.default.recoverynotdelay,
      serviceGroups: data.default.servicegroups,
      serviceTrap: data.default.servicetrap,
      freshnessThreshold: data.default.freshnessthreshold,
      serviceCategories: data.default.servicecategories,
      noteUrl: data.default.noteurl,
      actionUrl: data.default.actionurl,
      atlIcon: data.default.atlicon,
      geoCoords: data.default.geocoords,
      geoCoordsTruncated: data.hostgroupservice.geoCoordsTruncated
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
      hostGroups: data.hostgroupservice.hostgroups,
      checkCommand: data.hostgroupservice.checkcommand,
      macroName: data.hostgroupservice.macroname,
      macroValue: data.hostgroupservice.macrovalue,
      checkPeriod: data.hostgroupservice.checkperiod,
      maxCheckAttempts: data.hostgroupservice.maxcheckattempts,
      normalCheckInterval: data.hostgroupservice.normalcheckinterval,
      retryCheckInterval: data.hostgroupservice.retrycheckinterval,
      contactGroups: data.hostgroupservice.contactgroups,
      notificationInterval: data.hostgroupservice.notinterval,
      notificationPeriod: data.hostgroupservice.notificationperiod,
      firstNotificationDelay: data.hostgroupservice.firstnotdelay,
      recoveryNotificationDelay: data.hostgroupservice.recoverynotdelay,
      serviceGroups: data.hostgroupservice.servicegroups,
      serviceTrap: data.hostgroupservice.servicetrap,
      freshnessThreshold: data.hostgroupservice.freshnessthreshold,
      serviceCategories: data.hostgroupservice.servicecategories,
      noteUrl: data.hostgroupservice.noteurl,
      actionUrl: data.hostgroupservice.actionurl,
      atlIcon: data.hostgroupservice.atlicon,
      geoCoords: data.hostgroupservice.geocoords,
      geoCoordsTruncated: data.hostgroupservice.geoCoordsTruncated
    },
    true,
    htmldata.dataForUpdate
  );
});

Then('the properties are updated', () => {
  cy.checkValuesOfHostGroupService(data.hostgroupservice.name, {
    ...data.hostgroupservice,
    hostGroups: data.hostgroupservice.hostgroups,
    checkCommand: data.hostgroupservice.checkcommand,
    macroName: data.hostgroupservice.macroname,
    macroValue: data.hostgroupservice.macrovalue,
    checkPeriod: data.hostgroupservice.checkperiod,
    maxCheckAttempts: data.hostgroupservice.maxcheckattempts,
    normalCheckInterval: data.hostgroupservice.normalcheckinterval,
    retryCheckInterval: data.hostgroupservice.retrycheckinterval,
    contactGroups: data.hostgroupservice.contactgroups,
    notificationInterval: data.hostgroupservice.notinterval,
    notificationPeriod: data.hostgroupservice.notificationperiod,
    firstNotificationDelay: data.hostgroupservice.firstnotdelay,
    recoveryNotificationDelay: data.hostgroupservice.recoverynotdelay,
    serviceGroups: data.hostgroupservice.servicegroups,
    serviceTrap: data.hostgroupservice.servicetrap,
    freshnessThreshold: data.hostgroupservice.freshnessthreshold,
    serviceCategories: data.hostgroupservice.servicecategories,
    noteUrl: data.hostgroupservice.noteurl,
    actionUrl: data.hostgroupservice.actionurl,
    atlIcon: data.hostgroupservice.atlicon,
    geoCoords: data.hostgroupservice.geocoords,
    geoCoordsTruncated: data.hostgroupservice.geoCoordsTruncated
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
    hostGroups: data.hostgroupservice.hostgroups,
    checkCommand: data.hostgroupservice.checkcommand,
    macroName: data.hostgroupservice.macroname,
    macroValue: data.hostgroupservice.macrovalue,
    checkPeriod: data.hostgroupservice.checkperiod,
    maxCheckAttempts: data.hostgroupservice.maxcheckattempts,
    normalCheckInterval: data.hostgroupservice.normalcheckinterval,
    retryCheckInterval: data.hostgroupservice.retrycheckinterval,
    contactGroups: data.hostgroupservice.contactgroups,
    notificationInterval: data.hostgroupservice.notinterval,
    notificationPeriod: data.hostgroupservice.notificationperiod,
    firstNotificationDelay: data.hostgroupservice.firstnotdelay,
    recoveryNotificationDelay: data.hostgroupservice.recoverynotdelay,
    serviceGroups: data.hostgroupservice.servicegroups,
    serviceTrap: data.hostgroupservice.servicetrap,
    freshnessThreshold: data.hostgroupservice.freshnessthreshold,
    serviceCategories: data.hostgroupservice.servicecategories,
    noteUrl: data.hostgroupservice.noteurl,
    actionUrl: data.hostgroupservice.actionurl,
    atlIcon: data.hostgroupservice.atlicon,
    geoCoords: data.hostgroupservice.geocoords,
    geoCoordsTruncated: data.hostgroupservice.geoCoordsTruncated
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
