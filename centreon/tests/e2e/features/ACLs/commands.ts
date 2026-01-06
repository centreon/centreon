interface LinkMenuToGroupProps {
  aclGroupName: string;
  aclMenuName: string;
}

Cypress.Commands.add(
  'addAclMenuToAclGroup',
  ({ aclGroupName, aclMenuName }: LinkMenuToGroupProps) => {
    return cy.executeActionViaClapi({
      bodyContent: {
        action: 'ADDMENU',
        object: 'ACLGROUP',
        values: `${aclGroupName};${aclMenuName}`
      }
    });
  }
);

interface Credentials {
  login: string;
  password: string;
}

Cypress.Commands.add(
  'loginByCredentials',
  ({ login, password }: Credentials) => {
    return cy
      .request({
        body: {
          login,
          password
        },
        method: 'POST',
        url: '/centreon/authentication/providers/configurations/local'
      })
      .visit(`${Cypress.config().baseUrl}`)
      .wait('@getNavigationList');
  }
);

type Action =
  | 'top_counter'
  | 'poller_stats'
  | 'poller_listing'
  | 'create_edit_poller_cfg'
  | 'delete_poller_cfg'
  | 'generate_cfg'
  | 'generate_trap'
  | 'all_engine'
  | 'global_shutdown'
  | 'global_restart'
  | 'global_notifications'
  | 'global_service_checks'
  | 'global_service_passive_checks'
  | 'global_host_checks'
  | 'global_host_passive_checks'
  | 'global_event_handler'
  | 'global_flap_detection'
  | 'global_service_obsess'
  | 'global_host_obsess'
  | 'global_perf_data'
  | 'all_service'
  | 'service_checks'
  | 'service_notifications'
  | 'service_acknowledgement'
  | 'service_disacknowledgement'
  | 'service_schedule_check'
  | 'service_schedule_forced_check'
  | 'service_schedule_downtime'
  | 'service_comment'
  | 'service_event_handler'
  | 'service_flap_detection'
  | 'service_passive_checks'
  | 'service_submit_result'
  | 'service_display_command'
  | 'all_host'
  | 'host_checks'
  | 'host_notifications'
  | 'host_acknowledgement'
  | 'host_disacknowledgement'
  | 'host_schedule_check'
  | 'host_schedule_forced_check'
  | 'host_schedule_downtime'
  | 'host_comment'
  | 'host_event_handler'
  | 'host_flap_detection'
  | 'host_checks_for_services'
  | 'host_notifications_for_services'
  | 'host_submit_result'
  | 'manage_tokens';

type AclActionType = {
  aclGroups: Array<string>;
  actions: Array<Action>;
  description: string;
  name: string;
};

Cypress.Commands.add(
  'executeActionOnIframe',
  (
    textToFind: string,
    action: (body: JQuery<HTMLElement>) => void,
    retryAttempts: number,
    retryDelay: number
  ) => {
    const attempt = ($iframe): Promise<boolean> => {
      return new Cypress.Promise((resolve) => {
        const $body = $iframe.contents().find('body');
        const containsText = $body.text().includes(textToFind);
        if (containsText) {
          action($body);
          resolve(true);
        } else {
          resolve(false);
        }
      });
    };

    const attemptWithRetry = (attemptNumber): Cypress.Chainable => {
      cy.wrap(`Attempt number ${attemptNumber}`);
      if (attemptNumber > retryAttempts) {
        throw new Error(`The ${textToFind} not found in the iframe body`);
      }

      return cy.get('iframe#main-content').then(($iframe) => {
        return attempt($iframe).then((found) => {
          if (!found) {
            return new Cypress.Promise((resolve) => {
              setTimeout(() => {
                resolve(attemptWithRetry(attemptNumber + 1));
              }, retryDelay);
            });
          }
        });
      });
    };

    cy.wrap(null).then(() => attemptWithRetry(1));
  }
);

interface LinkActionToGroupProps {
  aclActionName: string;
  aclGroupName: string;
}

Cypress.Commands.add(
  'addAclActionToAclGroup',
  ({ aclGroupName, aclActionName }: LinkActionToGroupProps) => {
    return cy.executeActionViaClapi({
      bodyContent: {
        action: 'ADDACTION',
        object: 'ACLGROUP',
        values: `${aclGroupName};${aclActionName}`
      }
    });
  }
);

interface LinkResourceToGroupProps {
  aclGroupName: string;
  aclResourceName: string;
}

Cypress.Commands.add(
  'addAclResourceToAclGroup',
  ({ aclGroupName, aclResourceName }: LinkResourceToGroupProps) => {
    return cy.executeActionViaClapi({
      bodyContent: {
        action: 'ADDRESOURCE',
        object: 'ACLGROUP',
        values: `${aclGroupName};${aclResourceName}`
      }
    });
  }
);

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      addAclActionToAclGroup: (
        props: LinkActionToGroupProps
      ) => Cypress.Chainable;
      addAclMenuToAclGroup: (props: LinkMenuToGroupProps) => Cypress.Chainable;
      addAclResourceToAclGroup: (
        props: LinkResourceToGroupProps
      ) => Cypress.Chainable;
      executeActionOnIframe: (
        textToFind: string,
        action: (body: JQuery<HTMLElement>) => void,
        retryAttempts: number,
        retryDelay: number
      ) => Cypress.Chainable;
      loginByCredentials: (props: Credentials) => Cypress.Chainable;
    }
  }
}

export type { AclActionType, Action };
