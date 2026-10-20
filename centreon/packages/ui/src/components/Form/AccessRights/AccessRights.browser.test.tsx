import { rs } from '@rstest/core';
import { interceptAPIRequest, Method, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createStore, Provider } from 'jotai';

import { SnackbarProvider, TestQueryProvider } from '../../..';
import { AccessRights } from './AccessRights';
import type { AccessRightInitialValues } from './models';
import {
  buildResult,
  emptyAccessRights,
  labels,
  roles,
  simpleAccessRights
} from './storiesData';

// The access rights live in a module-level Jotai store (`./Provider`), so
// they carry over from one test to the next: the tests below rely on that
// order.

interface InitializeProps {
  initialValues?: Array<AccessRightInitialValues>;
  loading?: boolean;
}

interface InitializeState {
  cancel: ReturnType<typeof rs.fn>;
  change: ReturnType<typeof rs.fn>;
  save: ReturnType<typeof rs.fn>;
}

const initialize = ({
  initialValues = simpleAccessRights,
  loading = false
}: InitializeProps): InitializeState => {
  const cancel = rs.fn();
  const save = rs.fn();
  const change = rs.fn();

  interceptAPIRequest({
    alias: 'getContacts',
    method: Method.GET,
    path: '**/contacts?**',
    response: buildResult(false)
  });
  interceptAPIRequest({
    alias: 'getContactGroups',
    method: Method.GET,
    path: '**/contact-groups?**',
    response: buildResult(true)
  });

  render(
    <SnackbarProvider>
      <TestQueryProvider>
        <Provider store={createStore()}>
          <AccessRights
            cancel={cancel}
            endpoints={{
              contact: '/contacts',
              contactGroup: '/contact-groups'
            }}
            initialValues={initialValues}
            labels={labels}
            loading={loading}
            onChange={change}
            roles={roles}
            submit={save}
          />
        </Provider>
      </TestQueryProvider>
    </SnackbarProvider>
  );

  return {
    cancel,
    change,
    save
  };
};

// The first element whose own text contains the given one.
const findFirstByText = async (text: string): Promise<HTMLElement> =>
  (
    await screen.findAllByText((_, element) =>
      Array.from(element?.childNodes ?? []).some(
        (node) =>
          node.nodeType === Node.TEXT_NODE &&
          (node.textContent ?? '').replace(/\s+/g, ' ').includes(text)
      )
    )
  )[0];

const queryFirstByText = (text: string): HTMLElement | undefined =>
  screen.queryAllByText((_, element) =>
    Array.from(element?.childNodes ?? []).some(
      (node) =>
        node.nodeType === Node.TEXT_NODE &&
        (node.textContent ?? '').replace(/\s+/g, ' ').includes(text)
    )
  )[0];

const getInput = (testId: string): HTMLInputElement =>
  screen
    .getAllByTestId(testId)
    .find((element) => element.tagName === 'INPUT') as HTMLInputElement;

// Clicks the select's combobox: MUI opens the select on its mouse down.
const openSelect = async (testId: string): Promise<void> => {
  const select = getInput(testId).parentElement as HTMLElement;

  await userEvent.click(within(select).getByRole('combobox'));
};

const selectRole = async (testId: string, role: string): Promise<void> => {
  await openSelect(testId);

  const listbox = (await screen.findAllByRole('listbox')).at(-1) as HTMLElement;

  await userEvent.click(
    listbox.querySelector(`li[data-value="${role}"]`) as HTMLElement
  );

  await waitFor(() => {
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });
};

const openAutocomplete = async (label: string): Promise<void> => {
  await userEvent.click(screen.getByLabelText(label));
};

const selectOption = async (name: string): Promise<void> => {
  const listbox = await screen.findByRole('listbox');

  await userEvent.click(await within(listbox).findByText(name));
};

describe('Access rights', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the access rights in loading state', async () => {
    initialize({ loading: true });

    expect(await findFirstByText('Share dashboard with')).toBeVisible();
    expect(await findFirstByText('Contact')).toBeVisible();
    expect(await findFirstByText('Contact group')).toBeVisible();
    expect(screen.getByLabelText('Add a contact')).toBeVisible();
    expect(getInput('add_role')).toBeDisabled();
    expect(screen.getByTestId('add')).toBeDisabled();
    expect(screen.getByLabelText('Cancel')).toBeVisible();
    expect(screen.getByLabelText('Save')).toBeVisible();

    // The contact type is selected, the role defaults to viewer, the list is
    // replaced by its skeleton and nothing can be saved.
    expect(screen.getByRole('radio', { name: 'Contact' })).toBeChecked();
    expect(getInput('add_role')).toHaveValue('viewer');
    expect(
      screen.queryByText(simpleAccessRights[0].name)
    ).not.toBeInTheDocument();
    expect(screen.getByLabelText('Save')).toBeDisabled();
  });

  it('displays the access rights with an empty list', async () => {
    initialize({ initialValues: emptyAccessRights });

    expect(await findFirstByText('The contact list is empty')).toBeVisible();

    // The list title, no stats and nothing to save.
    expect(screen.getByText('User rights')).toBeVisible();
    expect(screen.queryByText(/added|updated|removed/)).not.toBeInTheDocument();
    expect(screen.getByLabelText('Save')).toBeDisabled();
  });

  it('displays the access rights list', async () => {
    initialize({});

    for (const { name, email, isContactGroup, role } of simpleAccessRights) {
      expect(await findFirstByText(name)).toBeVisible();
      expect(await findFirstByText(email as string)).toBeVisible();

      const groupChip = document.querySelector(`[data-groupChip="${name}"]`);

      if (isContactGroup) {
        expect(groupChip).toBeVisible();
      } else {
        expect(groupChip).not.toBeInTheDocument();
      }

      expect(getInput(`role-${name}`)).toHaveValue(role);
      expect(screen.getByTestId(`remove-${name}`)).toBeVisible();
    }

    // No state chip, no stats and nothing to save.
    expect(document.querySelector('[data-state]')).not.toBeInTheDocument();
    expect(screen.queryByText(/added|updated|removed/)).not.toBeInTheDocument();
    expect(screen.getByLabelText('Save')).toBeDisabled();
  });

  it('displays a removed chip when the corresponding icon is clicked', async () => {
    initialize({});

    expect(await screen.findByTestId('remove-Erin Monahan')).toHaveAttribute(
      'data-removed',
      'false'
    );

    await userEvent.click(screen.getByTestId('remove-Erin Monahan'));

    await waitFor(() => {
      expect(screen.getByTestId('remove-Erin Monahan')).toHaveAttribute(
        'data-removed',
        'true'
      );
    });
    expect(await findFirstByText(labels.list.removed)).toBeVisible();

    // The removed contact's role is disabled, the stats count it and the
    // changes can be saved.
    expect(getInput('role-Erin Monahan')).toBeDisabled();
    expect(await screen.findByText('1 removed')).toBeVisible();
    expect(screen.getByLabelText('Save')).toBeEnabled();
  });

  it('restores the contact when the contact is removed and the corresponding icon is clicked', async () => {
    initialize({});

    await userEvent.click(await screen.findByTestId('remove-Erin Monahan'));

    await waitFor(() => {
      expect(screen.getByTestId('remove-Erin Monahan')).toHaveAttribute(
        'data-removed',
        'false'
      );
    });
    expect(queryFirstByText(labels.list.removed)).toBeUndefined();

    // The role is enabled again, no stats and nothing to save.
    expect(getInput('role-Erin Monahan')).toBeEnabled();
    expect(screen.queryByText(/removed/)).not.toBeInTheDocument();
    expect(screen.getByLabelText('Save')).toBeDisabled();
  });

  it('submits the new acces rights list without the removed contact', async () => {
    const { save } = initialize({});

    await userEvent.click(await screen.findByTestId('remove-Erin Monahan'));

    await waitFor(() => {
      expect(screen.getByTestId('remove-Erin Monahan')).toHaveAttribute(
        'data-removed',
        'true'
      );
    });
    expect(await findFirstByText(labels.list.removed)).toBeVisible();
    expect(await findFirstByText('1 removed')).toBeVisible();

    await userEvent.click(await findFirstByText(labels.actions.save));

    await waitFor(() => {
      expect(save).toHaveBeenCalled();
    });

    // The removed contact is not in the saved list.
    expect(save).toHaveBeenCalledWith(
      expect.not.arrayContaining([
        expect.objectContaining({ name: 'Erin Monahan' })
      ])
    );
  });

  it('submits the new acces rights list with the updated contact', async () => {
    const { save } = initialize({});

    await userEvent.click(await screen.findByTestId('remove-Erin Monahan'));
    await selectRole('role-Erin Monahan', 'viewer');

    expect(await findFirstByText(labels.list.updated)).toBeVisible();
    expect(await findFirstByText('1 updated')).toBeVisible();

    await userEvent.click(await findFirstByText(labels.actions.save));

    expect(save).toHaveBeenCalled();

    // The contact is back, with its new role.
    expect(getInput('role-Erin Monahan')).toHaveValue('viewer');
    expect(screen.getByTestId('remove-Erin Monahan')).toHaveAttribute(
      'data-removed',
      'false'
    );
  });

  it('removes the updated chip when the contact role is updated and its initial role is assigned back', async () => {
    initialize({});

    await selectRole('role-Erin Monahan', 'viewer');
    expect(await findFirstByText(labels.list.updated)).toBeVisible();

    await selectRole('role-Erin Monahan', 'editor');
    await waitFor(() => {
      expect(queryFirstByText(labels.list.updated)).toBeUndefined();
    });

    // The initial role is back and nothing to save.
    expect(getInput('role-Erin Monahan')).toHaveValue('editor');
    expect(screen.getByLabelText('Save')).toBeDisabled();
  });

  it('retrieves contacts when the contact radio is selected', async () => {
    initialize({});

    await userEvent.click(await findFirstByText(labels.add.contact));
    await openAutocomplete(labels.add.autocompleteContact);

    await waitForRequest('@getContacts');

    // The contacts are listed in the autocomplete.
    const listbox = await screen.findByRole('listbox');

    expect(await within(listbox).findByText('Entity 10')).toBeVisible();
    expect(within(listbox).getAllByRole('option')).toHaveLength(10);
  });

  it('retrieves contact groups when the contact group radio is selected', async () => {
    initialize({});

    await userEvent.click(await findFirstByText(labels.add.contactGroup));
    await openAutocomplete(labels.add.autocompleteContactGroup);

    await waitForRequest('@getContactGroups');

    // The contact group type is selected and the groups are listed in the
    // autocomplete.
    expect(screen.getByRole('radio', { name: 'Contact group' })).toBeChecked();

    const listbox = await screen.findByRole('listbox');

    expect(await within(listbox).findByText('Entity Group 10')).toBeVisible();
    expect(within(listbox).getAllByRole('option')).toHaveLength(10);
  });

  it('adds the contact as viewer when a contact is selected and the corresponding button is clicked', async () => {
    initialize({});

    await userEvent.click(await findFirstByText(labels.add.contact));
    await openAutocomplete(labels.add.autocompleteContact);

    await waitForRequest('@getContacts');

    await selectOption('Entity 10');

    await userEvent.click(screen.getByTestId('add'));

    expect(await findFirstByText('Entity 10')).toBeVisible();

    expect(getInput('role-Entity 10')).toHaveValue('viewer');
    expect(await findFirstByText(labels.list.added)).toBeVisible();

    // The autocomplete is cleared and the stats count the new contact.
    expect(screen.getByLabelText(labels.add.autocompleteContact)).toHaveValue(
      ''
    );
    expect(await screen.findByText('1 added')).toBeVisible();
  });

  it('adds the contact group as editor when a contact group is selected and the corresponding button is clicked', async () => {
    initialize({});

    await userEvent.click(await findFirstByText(labels.add.contactGroup));
    await openAutocomplete(labels.add.autocompleteContactGroup);

    await waitForRequest('@getContactGroups');

    await selectOption('Entity Group 10');
    await selectRole('add_role', 'editor');
    await userEvent.click(screen.getByTestId('add'));

    expect(await findFirstByText('Entity Group 10')).toBeVisible();

    expect(getInput('role-Entity Group 10')).toHaveValue('editor');
    expect(await findFirstByText(labels.list.added)).toBeVisible();
    expect(await findFirstByText('2 added')).toBeVisible();

    // The group has its group chip.
    expect(
      document.querySelector('[data-groupChip="Entity Group 10"]')
    ).toBeVisible();
  });

  it('displays statistics when a contact is added, a contact is updated and a contact is removed', async () => {
    initialize({});

    await userEvent.click(await findFirstByText(labels.add.contactGroup));
    await openAutocomplete(labels.add.autocompleteContactGroup);

    await waitForRequest('@getContactGroups');

    await selectOption('Entity Group 11');
    await selectRole('add_role', 'editor');
    await userEvent.click(screen.getByTestId('add'));

    expect(await findFirstByText('Entity Group 11')).toBeVisible();

    await selectRole('role-Erin Monahan', 'viewer');

    await userEvent.click(screen.getByTestId('remove-Maryann Ferry'));

    expect(
      await findFirstByText('3 added | 1 updated | 1 removed')
    ).toBeVisible();

    // The state chips of the changed contacts.
    expect(document.querySelectorAll('[data-state="added"]')).toHaveLength(3);
    expect(document.querySelectorAll('[data-state="updated"]')).toHaveLength(1);
    expect(document.querySelectorAll('[data-state="removed"]')).toHaveLength(1);
    expect(getInput('role-Entity Group 11')).toHaveValue('editor');
  });

  it('clears the autocomplete when a contact is selected and the contact type is changed', async () => {
    initialize({});

    await userEvent.click(await findFirstByText(labels.add.contact));
    await openAutocomplete(labels.add.autocompleteContact);

    await waitForRequest('@getContacts');

    await selectOption('Entity 11');
    await waitFor(() => {
      expect(screen.getByLabelText(labels.add.autocompleteContact)).toHaveValue(
        'Entity  11'
      );
    });

    await userEvent.click(await findFirstByText(labels.add.contactGroup));
    await waitFor(() => {
      expect(
        screen.getByLabelText(labels.add.autocompleteContactGroup)
      ).toHaveValue('');
    });

    // Nothing selected, so nothing can be added.
    expect(screen.getByTestId('add')).toBeDisabled();
    expect(getInput('add_role')).toBeDisabled();
  });

  it('removes the contact from the list when the contact has been to the list and the corresponding button is clicked', async () => {
    initialize({});

    await userEvent.click(await findFirstByText(labels.add.contact));
    await openAutocomplete(labels.add.autocompleteContact);

    await waitForRequest('@getContacts');

    await selectOption('Entity 11');

    await userEvent.click(screen.getByTestId('add'));

    expect(await findFirstByText('Entity 11')).toBeVisible();

    expect(await findFirstByText(labels.list.added)).toBeVisible();
    expect(await findFirstByText('4 added')).toBeVisible();

    await userEvent.click(screen.getByTestId('remove-Entity 11'));

    await waitFor(() => {
      expect(queryFirstByText('Entity 11')).toBeUndefined();
    });
    expect(queryFirstByText('4 added')).toBeUndefined();

    // The stats are back to the previous changes.
    expect(
      await findFirstByText('3 added | 1 updated | 1 removed')
    ).toBeVisible();
  });

  it('resets the role to "viewer" when the "editor" role is selected and a contact with "viewer" right is selected', async () => {
    initialize({});

    await openAutocomplete(labels.add.autocompleteContact);

    await waitForRequest('@getContacts');

    await selectOption('Entity 11');

    await selectRole('add_role', 'editor');

    await openAutocomplete(labels.add.autocompleteContact);

    await waitForRequest('@getContacts');

    await selectOption('Entity 12');

    await waitFor(() => {
      expect(getInput('add_role')).toHaveValue('viewer');
    });

    // The contact can be added.
    expect(screen.getByTestId('add')).toBeEnabled();
  });

  it('calls the change function when the corresponding prop is set and the form is updated', async () => {
    const { change } = initialize({});

    await userEvent.click(await findFirstByText(labels.add.contact));
    await openAutocomplete(labels.add.autocompleteContact);

    await waitForRequest('@getContacts');

    await selectOption('Entity 11');

    await userEvent.click(screen.getByTestId('add'));

    expect(await findFirstByText('Entity 11')).toBeVisible();

    expect(getInput('role-Entity 11')).toHaveValue('viewer');
    expect(await findFirstByText(labels.list.added)).toBeVisible();
    await waitFor(() => {
      expect(change).toHaveBeenCalledTimes(2);
    });
  });
});
