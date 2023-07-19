import { Provider, createStore } from 'jotai';

import { TestQueryProvider, Method, SnackbarProvider } from '@centreon/ui';

import {
  labelDelete,
  labelSave,
  labelDuplicate,
  labelActiveOrInactive,
  labelClosePanel,
  labelReduceInformationPanel,
  labelExpandInformationPanel,
  labelChangeName,
  labelNotificationName,
  labelRequired,
  labelSearchHostGroups,
  labelSearchServiceGroups,
  labelChooseAtLeastOneResource,
  labelChooseAtleastOneContactOrContactGroup,
  labelTimePeriod,
  labelSubject,
  labelMessageFieldShouldNotBeEmpty,
  labelDoYouWantToConfirmAction,
  labelConfirmEditNotification,
  labelSuccessfulEditNotification,
  labelThisNameAlreadyExists,
  labelDeleteNotification,
  labelDeleteNotificationWarning,
  labelNotificationSuccessfullyDeleted,
  labelCancel,
  labelPleaseEnterNameForDuplicatedNotification,
  labelDiscard,
  labelNotificationDuplicated
} from '../translatedLabels';
import { notificationsNamesAtom, panelWidthStorageAtom } from '../atom';
import { DeleteConfirmationDialog } from '../Actions/Delete';
import { DuplicationForm } from '../Actions/Duplicate';

import { notificationEndpoint } from './api/endpoints';
import { PanelMode } from './models';
import { editedNotificationIdAtom, panelModeAtom } from './atom';
import { listNotificationResponse } from './testUtils';

import Form from '.';

const store = createStore();
store.set(panelWidthStorageAtom, 800);

store.set(panelModeAtom, PanelMode.Edit);
store.set(editedNotificationIdAtom, 1);
store.set(notificationsNamesAtom, [
  { id: 1, name: 'Notifications 1' },
  { id: 2, name: 'Notifications 2' }
]);

const PanelWithQueryProvider = (): JSX.Element => {
  return (
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <TestQueryProvider>
          <SnackbarProvider>
            <>
              <Form marginBottom={0} />
              <DeleteConfirmationDialog />
              <DuplicationForm />
            </>
          </SnackbarProvider>
        </TestQueryProvider>
      </Provider>
    </div>
  );
};

const initialize = (): void => {
  cy.interceptAPIRequest({
    alias: 'getNotificationRequest',
    method: Method.GET,
    path: notificationEndpoint({ id: 1 }),
    response: listNotificationResponse
  });

  cy.interceptAPIRequest({
    alias: 'editNotificationRequest',
    method: Method.PUT,
    path: notificationEndpoint({ id: 1 }),
    response: { status: 'ok' }
  });

  cy.interceptAPIRequest({
    alias: 'deleteNotificationtRequest',
    method: Method.DELETE,
    path: notificationEndpoint({ id: 1 }),
    response: undefined,
    statusCode: 204
  });

  cy.interceptAPIRequest({
    alias: 'duplicateNotificationtRequest',
    method: Method.POST,
    path: notificationEndpoint({}),
    response: { status: 'ok' }
  });
  cy.mount({
    Component: <PanelWithQueryProvider />
  });

  cy.viewport('macbook-13');
};

describe('Edit Panel', () => {
  beforeEach(initialize);

  it('Ensures that the header section displays all the expected actions', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByLabelText(labelDeleteNotification).should('be.visible');
    cy.findByLabelText(labelSave).should('be.visible');
    cy.findByLabelText(labelDuplicate).should('be.visible');
    cy.findByLabelText(labelActiveOrInactive).should('be.visible');
    cy.findByLabelText(labelClosePanel).should('be.visible');

    cy.matchImageSnapshot();
  });

  it('Confirms that the notification name is properly rendered with the edited value and supports the capability for users to modify the name by interacting with the Edit icon', () => {
    cy.waitForRequest('@getNotificationRequest');

    const notificationName = 'Notifications 1';
    cy.findByText(notificationName).should('be.visible');
    cy.findByTestId(labelChangeName).click();
    cy.findByText(labelChangeName).should('not.exist');

    cy.findByLabelText(labelNotificationName).should(
      'have.value',
      notificationName
    );
    cy.findByLabelText(labelNotificationName).should('have.attr', 'required');

    cy.matchImageSnapshot();
  });

  it('Ensures that the form handles an empty name field correctly by showing an error message and disabling the Save button as a validation measure', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId(labelChangeName).click();

    cy.findByLabelText(labelNotificationName).clear();
    cy.clickOutside();

    cy.findByText(labelRequired).should('be.visible');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.matchImageSnapshot();
  });

  it('Ensures that the form handles an existing name field correctly by showing an error message and disabling the Save button as a validation measure', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId(labelChangeName).click();

    cy.findByLabelText(labelNotificationName).clear().type('Notifications 2');
    cy.clickOutside();

    cy.findByText(labelThisNameAlreadyExists).should('be.visible');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.matchImageSnapshot();
  });

  it('Confirms that the "Expand/Collapse" button triggers the desired expansion or collapse of the panel, providing users with the ability to control its visibility and size', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByText(labelReduceInformationPanel).should('be.visible');
    cy.findByTestId(labelReduceInformationPanel).click();
    cy.findByText(labelExpandInformationPanel).should('be.visible');
    cy.wait(50).then(() => {
      expect(localStorage.getItem('cloud-notifications-panel-width')).to.equal(
        '550'
      );
    });

    cy.findByTestId(labelReduceInformationPanel).click();
    cy.findByText(labelReduceInformationPanel).should('be.visible');
    cy.wait(50).then(() => {
      expect(localStorage.getItem('cloud-notifications-panel-width')).to.equal(
        '800'
      );
    });

    cy.matchImageSnapshot();
  });

  it("Ensures that the Save button's initial state is set to disabled", () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByLabelText(labelSave).should('be.disabled');

    cy.matchImageSnapshot();
  });

  it('Confirms that the Save button responds to field changes correctly, becoming enabled when a modification occurs and the form is error-free', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByLabelText(labelSave).should('be.disabled');
    cy.findByLabelText(labelActiveOrInactive).click();
    cy.findByLabelText(labelSave).should('not.be.disabled');

    cy.matchImageSnapshot();
  });

  it('Displays host group resources and events with the edited notification values', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByLabelText(labelSearchHostGroups).should('be.visible');
    cy.findByText('Firewall').should('be.visible');
    cy.findByText('Linux-Servers').should('be.visible');
    cy.findByText('Networks').should('be.visible');
    cy.findByText('Printers').should('be.visible');

    cy.findByTestId('Host groups events').within(() => {
      cy.findAllByRole('checkbox').should('have.length', 3);
      cy.findAllByRole('checkbox').eq(0).should('be.checked');
      cy.findAllByRole('checkbox').eq(1).should('be.checked');
      cy.findAllByRole('checkbox').eq(2).should('not.be.checked');
    });

    cy.matchImageSnapshot();
  });

  it('Ensures that the "Include Services" field presents the value of the edited notification', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId('include Services').within(() => {
      cy.findByRole('checkbox').should('be.checked');
    });

    cy.findByTestId('Extra events services').within(() => {
      cy.findAllByRole('checkbox').should('have.length', 4);
      cy.findAllByRole('checkbox').eq(0).should('be.checked');
      cy.findAllByRole('checkbox').eq(1).should('be.checked');
      cy.findAllByRole('checkbox').eq(2).should('not.be.checked');
      cy.findAllByRole('checkbox').eq(3).should('not.be.checked');
    });

    cy.matchImageSnapshot();
  });

  it('Confirms that the "Include Services" checkbox controls the enabling and checking status of all host group services checkboxes', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId('include Services').click();

    cy.findByTestId('Extra events services').within(() => {
      for (let i = 0; i < 4; i += 1) {
        cy.findAllByRole('checkbox').eq(i).should('not.be.checked');
        cy.findAllByRole('checkbox').eq(i).should('be.disabled');
      }
    });

    cy.matchImageSnapshot();
  });

  it('Ensures that when the "Host Groups" field is empty, all event checkboxes are unchecked and the "Include Services" field is not visible.', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId('include Services').should('be.visible');
    cy.findByTestId('Extra events services').should('be.visible');

    for (let i = 0; i < 4; i += 1) {
      cy.findAllByTestId('CancelIcon').eq(0).click();
    }

    cy.findByTestId('Host groups events').within(() => {
      for (let i = 0; i < 3; i += 1) {
        cy.findAllByRole('checkbox').eq(i).should('not.be.checked');
      }
    });

    cy.findByTestId('include Services').should('not.exist');
    cy.findByTestId('Extra events services').should('not.exist');

    cy.matchImageSnapshot();
  });

  it('Displays service groups and events fields with the edited notification values', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByLabelText(labelSearchServiceGroups).should('be.visible');
    cy.findByText('service1').should('be.visible');
    cy.findByText('service2').should('be.visible');
    cy.findByText('service3').should('be.visible');

    cy.findByTestId('Service groups events').within(() => {
      cy.findAllByRole('checkbox').should('have.length', 4);
      cy.findAllByRole('checkbox').eq(0).should('not.be.checked');
      cy.findAllByRole('checkbox').eq(1).should('be.checked');
      cy.findAllByRole('checkbox').eq(2).should('be.checked');
      cy.findAllByRole('checkbox').eq(2).should('be.checked');
    });

    cy.matchImageSnapshot();
  });

  it('Ensures that when the Service Groups field is empty, all associated events are disabled and unchecked', () => {
    cy.waitForRequest('@getNotificationRequest');

    for (let i = 0; i < 3; i += 1) {
      cy.findAllByTestId('CancelIcon').eq(4).click();
    }

    cy.findByTestId('Service groups events').within(() => {
      for (let i = 0; i < 4; i += 1) {
        cy.findAllByRole('checkbox').eq(i).should('not.be.checked');
        cy.findAllByRole('checkbox').eq(i).should('be.disabled');
      }
    });

    cy.matchImageSnapshot();
  });

  it('Validates that when both resource fields are empty, the user interface responds by displaying an error message and disabling the Save button', () => {
    cy.waitForRequest('@getNotificationRequest');

    for (let i = 0; i < 7; i += 1) {
      cy.findAllByTestId('CancelIcon').eq(0).click();
    }

    cy.findAllByText(labelChooseAtLeastOneResource).should('have.length', 2);
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.matchImageSnapshot();
  });

  it('Displays the Contacts field with edited notification contacts', () => {
    cy.waitForRequest('@getNotificationRequest');
    cy.get('[data-testid="Search contacts"]').as('fieldContacts');

    cy.get('@fieldContacts')
      .parent()
      .within(() => {
        cy.findByText('centreon-gorgone').should('be.visible');
        cy.findByText('Guest').should('be.visible');
      });

    cy.matchImageSnapshot();
  });

  it('Displays the Contact Groups field with edited notification contact groups', () => {
    cy.waitForRequest('@getNotificationRequest');
    cy.get('[data-testid="Search contact groups"]').as('fieldContactsGroups');

    cy.get('@fieldContactsGroups')
      .parent()
      .within(() => {
        cy.findByText('contact-group1').should('be.visible');
        cy.findByText('contact-group2').should('be.visible');
      });

    cy.matchImageSnapshot();
  });

  it('Validates that when the Contacts and Contact Groups fields are both empty, the user interface responds by displaying an error message and disabling the Save button', () => {
    cy.waitForRequest('@getNotificationRequest');

    for (let i = 0; i < 4; i += 1) {
      cy.findAllByTestId('CancelIcon').eq(7).click();
    }
    cy.clickOutside();

    cy.findAllByText(labelChooseAtleastOneContactOrContactGroup).should(
      'have.length',
      2
    );
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.matchImageSnapshot();
  });

  it('Ensures that the time period checkbox is checked and disabled, indicating its pre-selected status', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId(labelTimePeriod).should('exist');
    cy.findByTestId(labelTimePeriod).within(() => {
      cy.findByRole('checkbox').should('be.checked');
      cy.findByRole('checkbox').should('be.disabled');
    });

    cy.matchImageSnapshot();
  });

  it('Confirms that the three icons for notification channels are appropriately presented, with the email icon initially selected and the other icons disabled', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId('Email').within(() => {
      cy.findByRole('checkbox').should('be.checked');
    });

    cy.findByTestId('SMS').within(() => {
      cy.findByRole('checkbox').should('not.be.checked');
      cy.findByRole('checkbox').should('be.disabled');
    });

    cy.findByTestId('Slack').within(() => {
      cy.findByRole('checkbox').should('not.be.checked');
      cy.findByRole('checkbox').should('be.disabled');
    });

    cy.matchImageSnapshot();
  });

  it('Confirms that the Subject field is properly rendered with the edited notification subject', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByLabelText(labelSubject).should('have.value', 'Notification');

    cy.matchImageSnapshot();
  });

  it('Validates that when the Subject field is empty, the user interface responds by displaying an error message and disabling the Save button', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByLabelText(labelSubject).clear();
    cy.clickOutside();

    cy.findByText(labelRequired).should('be.visible');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.matchImageSnapshot();
  });

  it('Confirms that the Message field is properly rendered with the edited notification message', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId('EmailBody').contains('Bonjour');
    cy.findByTestId('EmailBody').contains('Cordialement');

    cy.matchImageSnapshot();
  });

  it('Validates that when the Message field is empty, the user interface responds by displaying an error message and disabling the Save button', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId('EmailBody').clear();
    cy.clickOutside();

    cy.findByText(labelMessageFieldShouldNotBeEmpty).should('be.visible');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.matchImageSnapshot();
  });
});

describe('Edit Panel : Confirm Dialog', () => {
  beforeEach(initialize);

  it('Confirms that the Save button triggers the display of a confirmation dialog, providing the user with an additional confirmation step before proceeding with the action', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByLabelText(labelSave).should('be.disabled');
    cy.findByLabelText(labelActiveOrInactive).click();
    cy.findByLabelText(labelSave).click();

    cy.findByText(labelDoYouWantToConfirmAction);
    cy.findByText(labelConfirmEditNotification);

    cy.matchImageSnapshot();
  });

  it('Confirms that the Confirm button triggers the sending of a PUT request', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByLabelText(labelActiveOrInactive).click();
    cy.findByLabelText(labelSave).click();

    cy.findByLabelText('Confirm').click();

    cy.waitForRequest('@editNotificationRequest').then(({ request }) => {
      expect(JSON.parse(request.body).is_activated).to.equal(true);
    });

    cy.findByText(labelSuccessfulEditNotification).should('be.visible');

    cy.matchImageSnapshot();
  });
});

describe('Edit Panel: Delete button', () => {
  beforeEach(initialize);

  it('displays a confirmation dialog containing the notification name upon clicking the Delete button', () => {
    cy.waitForRequest('@getNotificationRequest');

    const message = `${labelDelete} « Notifications 1 ».`;
    cy.findByTestId(labelDeleteNotification).click();
    cy.findByText(message);
    cy.findByText(labelDeleteNotification);
    cy.findByText(labelDeleteNotificationWarning);
    cy.findByText(labelCancel).click();

    cy.matchImageSnapshot();
  });
  it('displays a success message after successful deletion', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId(labelDeleteNotification).click();
    cy.findByLabelText(labelDelete).click();

    cy.waitForRequest('@deleteNotificationtRequest');
    cy.waitForRequest('@getNotificationRequest');

    cy.findByText(labelNotificationSuccessfullyDeleted);

    cy.matchImageSnapshot();
  });
  it('displays an error message upon failed deletion', () => {
    cy.interceptAPIRequest({
      alias: 'deleteNotificationtRequest',
      method: Method.DELETE,
      path: notificationEndpoint({ id: 1 }),
      response: {
        code: '500',
        message: 'internal server error'
      },
      statusCode: 500
    });

    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId(labelDeleteNotification).click();
    cy.findByLabelText(labelDelete).click();
    cy.waitForRequest('@deleteNotificationtRequest');

    cy.findByText('internal server error');

    cy.matchImageSnapshot();
  });
});

describe('Edit Panel: Duplicate button', () => {
  beforeEach(initialize);

  it('disables the Duplicate button when changes are made', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId(labelDuplicate).should('not.be.disabled');

    cy.findAllByTestId('CancelIcon').eq(0).click();

    cy.findByTestId(labelDuplicate).should('be.disabled');

    cy.matchImageSnapshot();
  });

  it('displays confirmation dialog with new notification name field on Duplicate button click', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId(labelDuplicate).click();

    cy.findByText(labelPleaseEnterNameForDuplicatedNotification).should(
      'be.visible'
    );
    cy.findByLabelText(labelNotificationName).should('be.visible');
    cy.findByText(labelDuplicate).should('be.disabled');
    cy.findByText(labelDiscard).click();

    cy.matchImageSnapshot();
  });

  it('validates that new name is required and not already taken', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId(labelDuplicate).click();
    cy.findByLabelText(labelNotificationName).should('have.attr', 'required');
    cy.findByLabelText(labelNotificationName).type('Notifications 2');
    cy.clickOutside();
    cy.findByText(labelThisNameAlreadyExists);

    cy.findByLabelText(labelNotificationName).clear();
    cy.clickOutside();
    cy.findByText(labelRequired);

    cy.findByText(labelDiscard).click();

    cy.matchImageSnapshot();
  });

  it('disables the confirm button if the name is empty or already exists', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findByTestId(labelDuplicate).click();
    cy.findByLabelText(labelNotificationName).type('Notifications 2');
    cy.findByTestId('Confirm').should('be.disabled');

    cy.findByLabelText(labelNotificationName).clear();
    cy.findByTestId('Confirm').should('be.disabled');

    cy.findByText(labelDiscard).click();

    cy.matchImageSnapshot();
  });

  it('displays a success message upon successful duplication', () => {
    cy.waitForRequest('@getNotificationRequest');

    cy.findAllByTestId(labelDuplicate).click();

    cy.findByLabelText(labelNotificationName).type('New name');
    cy.findByTestId('Confirm').click();

    cy.waitForRequest('@duplicateNotificationtRequest');

    cy.findByText(labelNotificationDuplicated);

    cy.matchImageSnapshot();
  });

  it('displays an error message upon failed duplication request', () => {
    const errorMessage = 'internal server error';

    cy.interceptAPIRequest({
      alias: 'duplicateNotificationtRequest',
      method: Method.POST,
      path: notificationEndpoint({}),
      response: {
        code: '500',
        message: errorMessage
      },
      statusCode: 500
    });
    cy.waitForRequest('@getNotificationRequest');

    cy.findAllByTestId(labelDuplicate).click();

    cy.findByLabelText(labelNotificationName).type('New name');
    cy.findByTestId('Confirm').click();

    cy.waitForRequest('@duplicateNotificationtRequest');

    cy.findByText(errorMessage).should('be.visible');

    cy.matchImageSnapshot();
  });
});
