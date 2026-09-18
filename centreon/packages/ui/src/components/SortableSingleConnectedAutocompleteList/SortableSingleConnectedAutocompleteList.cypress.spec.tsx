import ContentCopyIcon from '@mui/icons-material/ContentCopy';

import { Method, TestQueryProvider } from '../..';
import buildListingEndpoint from '../../api/buildListingEndpoint';
import type { SelectEntry } from '../../InputField/Select';
import type { SortableAutocompleteEntry } from './models';
import { getSortableAutocompleteEntryValues } from './models';
import { SortableSingleConnectedAutocompleteList } from './SortableSingleConnectedAutocompleteList';

const endpoint = '/configuration/hosts/templates';

const options: Array<SelectEntry> = [
  { id: 1, name: 'generic-active-host' },
  { id: 2, name: 'generic-passive-host' },
  { id: 3, name: 'linux-server-standard' }
];

const items: Array<SortableAutocompleteEntry> = [
  { id: 'entry-1', value: options[0] },
  { id: 'entry-2', value: options[1] }
];

const initialize = ({
  withActions = true
}: {
  withActions?: boolean;
} = {}): unknown => {
  const onChange = cy.stub();
  const onDuplicate = cy.stub();

  cy.interceptAPIRequest({
    alias: 'getTemplates',
    method: Method.GET,
    path: `${endpoint}**`,
    response: {
      meta: { limit: 10, page: 1, total: options.length },
      result: options
    }
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <SortableSingleConnectedAutocompleteList
          actions={
            withActions
              ? [
                  {
                    icon: <ContentCopyIcon fontSize="small" />,
                    id: 'duplicate',
                    label: 'Duplicate',
                    onClick: onDuplicate
                  }
                ]
              : []
          }
          baseEndpoint=""
          field="name"
          getEndpoint={(parameters): string =>
            buildListingEndpoint({ baseEndpoint: endpoint, parameters })
          }
          items={items}
          onChange={onChange}
          selectorLabel="Template"
        />
      </TestQueryProvider>
    )
  });

  return { onChange, onDuplicate };
};

describe('SortableSingleConnectedAutocompleteList', () => {
  it('displays one row per item, each with its selected option, a delete action and a drag handle', () => {
    initialize();

    cy.get('[data-testid="Template"]').should('have.length', 2);
    cy.get('[data-testid="Template"]')
      .eq(0)
      .should('have.value', options[0].name);
    cy.get('[data-testid="Template"]')
      .eq(1)
      .should('have.value', options[1].name);

    cy.findAllByTestId('delete-row').should('have.length', 2);
    cy.findAllByTestId('drag-handle').should('have.length', 2);

    cy.makeSnapshot();
  });

  it('displays the given custom actions next to every row', () => {
    initialize();

    cy.findAllByTestId('duplicate').should('have.length', 2);
  });

  it('always displays the built-in delete action and drag handle, even without custom actions', () => {
    initialize({ withActions: false });

    cy.findAllByTestId('delete-row').should('have.length', 2);
    cy.findAllByTestId('drag-handle').should('have.length', 2);
    cy.get('[data-testid="duplicate"]').should('not.exist');
  });

  it('calls a custom action onClick with the corresponding row and index', () => {
    const { onDuplicate } = initialize();

    cy.findAllByTestId('duplicate')
      .eq(1)
      .click()
      .then(() => {
        expect(onDuplicate).to.have.been.calledWith(items[1], 1);
      });
  });

  it('removes the row when its built-in delete action is clicked', () => {
    const { onChange } = initialize();

    cy.findAllByTestId('delete-row')
      .eq(0)
      .click()
      .then(() => {
        expect(onChange).to.have.been.calledWith([items[1]]);
      });
  });

  it('fetches and displays the options returned by the given endpoint', () => {
    initialize();

    cy.get('[data-testid="Template"]').eq(0).click();
    cy.waitForRequest('@getTemplates');

    cy.findByRole('presentation').within(() => {
      options.forEach((option) => {
        cy.contains(option.name);
      });
    });
  });

  it('reports the updated list when a row selection changes', () => {
    const { onChange } = initialize();

    cy.get('[data-testid="Template"]').eq(0).click();
    cy.waitForRequest('@getTemplates');

    cy.findByRole('presentation').within(() => {
      cy.contains(options[2].name).click();
    });

    cy.wrap(null).then(() => {
      expect(onChange).to.have.been.calledWith([
        { id: items[0].id, value: options[2] },
        items[1]
      ]);
    });
  });

  it('appends a new, empty row when the add button is clicked', () => {
    const { onChange } = initialize();

    cy.findByTestId('Add').click();

    cy.wrap(null).then(() => {
      const [lastCallArgs] = onChange.lastCall.args;
      const addedEntry = lastCallArgs[lastCallArgs.length - 1];

      expect(lastCallArgs).to.have.length(3);
      expect(addedEntry.value).to.equal(null);
    });
  });
});

describe('getSortableAutocompleteEntryValues', () => {
  it('returns the value of every entry that has one, in order', () => {
    expect(
      getSortableAutocompleteEntryValues([
        { id: 'entry-1', value: options[0] },
        { id: 'entry-2', value: options[1] }
      ])
    ).to.deep.equal([options[0], options[1]]);
  });

  it('drops still-empty entries', () => {
    expect(
      getSortableAutocompleteEntryValues([
        { id: 'entry-1', value: options[0] },
        { id: 'entry-2', value: null }
      ])
    ).to.deep.equal([options[0]]);
  });

  it('returns an empty array when every entry is empty', () => {
    expect(
      getSortableAutocompleteEntryValues([{ id: 'entry-1', value: null }])
    ).to.deep.equal([]);
  });
});
