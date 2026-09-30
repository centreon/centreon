import EditIcon from '@mui/icons-material/Edit';
import VisibilityIcon from '@mui/icons-material/Visibility';

import { useState } from 'react';

import { TestQueryProvider } from '../..';
import buildListingEndpoint from '../../api/buildListingEndpoint';
import type { SelectEntry } from '../../InputField/Select';
import SingleConnectedAutocompleteField from '../../InputField/Select/Autocomplete/Connected/Single';
import TextField from '../../InputField/Text';
import type { RenderRowParams, RowParams, SortableRowAction } from './models';
import { type Props, SortableEntriesList } from './SortableEntriesList';

type Stub = ReturnType<typeof cy.stub>;

const ControlledList = <T,>({
  initialValues,
  onChangeSpy,
  ...props
}: Omit<Props<T>, 'values' | 'onChange'> & {
  initialValues: Array<T>;
  onChangeSpy: Stub;
}): JSX.Element => {
  const [values, setValues] = useState(initialValues);

  return (
    <SortableEntriesList<T>
      {...props}
      onChange={(nextValues): void => {
        onChangeSpy(nextValues);
        setValues(nextValues);
      }}
      values={values}
    />
  );
};

const getLastValues = <T,>(onChange: Stub): Array<T> =>
  onChange.lastCall.args[0];

const getRows = (): Cypress.Chainable => cy.findAllByRole('listitem');

const endpoint = '/configuration/hosts/templates';

const options: Array<SelectEntry> = [
  { id: 1, name: 'generic-active-host' },
  { id: 2, name: 'generic-passive-host' },
  { id: 3, name: 'linux-server-standard' }
];

type TemplateRow = SelectEntry | null;

const renderTemplateRow = ({
  setValue,
  value,
  values
}: RenderRowParams<TemplateRow>): JSX.Element => {
  const selectedElsewhere = values
    .map((template) => template?.id)
    .filter((id) => id !== value?.id);

  return (
    <SingleConnectedAutocompleteField
      baseEndpoint=""
      field="name"
      fullWidth
      getEndpoint={(parameters): string =>
        buildListingEndpoint({ baseEndpoint: endpoint, parameters })
      }
      getOptionDisabled={(option: SelectEntry): boolean =>
        selectedElsewhere.includes(option.id)
      }
      label="Template"
      onChange={(_event, template): void => setValue(template as TemplateRow)}
      value={value}
    />
  );
};

const initializeTemplates = ({
  draggable = true,
  templates = [options[0], options[1]]
}: {
  draggable?: boolean;
  templates?: Array<TemplateRow>;
} = {}): { onChange: Stub; onEdit: Stub } => {
  const onChange = cy.stub();
  const onEdit = cy.stub();

  cy.intercept('GET', `**${endpoint}**`, {
    body: {
      meta: { limit: 10, page: 1, total: options.length },
      result: options
    },
    statusCode: 200
  }).as('getTemplates');

  cy.mount({
    Component: (
      <TestQueryProvider>
        <div className="p-4">
          <ControlledList<TemplateRow>
            actions={({
              index,
              value
            }: RowParams<TemplateRow>): Array<SortableRowAction> => [
              {
                icon: <EditIcon />,
                id: 'edit-template',
                label: 'Edit template',
                onClick: () => onEdit(value, index)
              }
            ]}
            createValue={(): TemplateRow => null}
            draggable={draggable}
            initialValues={templates}
            label="Templates"
            onChangeSpy={onChange}
            renderRow={renderTemplateRow}
          />
        </div>
      </TestQueryProvider>
    )
  });

  return { onChange, onEdit };
};

describe('SortableEntriesList - single input rows', () => {
  it('displays one row per value with its value, its actions, a delete button and a trailing drag handle', () => {
    initializeTemplates();

    cy.findByRole('list', { name: 'Templates' }).should('exist');
    getRows().should('have.length', 2);
    cy.get('[data-testid="Template"]')
      .eq(0)
      .should('have.value', options[0].name);
    cy.get('[data-testid="Template"]')
      .eq(1)
      .should('have.value', options[1].name);
    cy.findAllByTestId('edit-template').should('have.length', 2);
    cy.findAllByTestId('delete-row').should('have.length', 2);

    getRows()
      .eq(0)
      .find('button')
      .last()
      .should('have.attr', 'data-testid', 'drag-handle');

    cy.makeSnapshot();
  });

  it('reports the plain values, without any internal id, when an option is selected', () => {
    const { onChange } = initializeTemplates();

    cy.get('[data-testid="Template"]').eq(1).click();
    cy.wait('@getTemplates');
    cy.findByRole('option', { name: options[2].name }).click();

    cy.get('[data-testid="Template"]')
      .eq(1)
      .should('have.value', options[2].name);
    cy.wrap(onChange).should(() => {
      expect(getLastValues(onChange)).to.deep.equal([options[0], options[2]]);
    });
  });

  it('lets a row disable the options already selected in sibling rows', () => {
    initializeTemplates();

    cy.get('[data-testid="Template"]').eq(1).click();
    cy.wait('@getTemplates');

    cy.findByRole('option', { name: options[0].name }).should(
      'have.attr',
      'aria-disabled',
      'true'
    );
    cy.findByRole('option', { name: options[2].name }).should(
      'not.have.attr',
      'aria-disabled',
      'true'
    );
  });

  it('appends a row built with createValue and focuses its first input', () => {
    const { onChange } = initializeTemplates();

    cy.findByTestId('Add').click();

    getRows().should('have.length', 3);
    cy.get('[data-testid="Template"]').eq(2).should('have.value', '');
    cy.focused().should('have.attr', 'data-testid', 'Template');
    cy.focused()
      .closest('li')
      .should(($row) => {
        expect($row.index()).to.equal(2);
      });
    cy.wrap(onChange).should(() => {
      expect(getLastValues(onChange)).to.deep.equal([
        options[0],
        options[1],
        null
      ]);
    });
  });

  it('removes a row and moves the focus to the delete button of the row taking its place', () => {
    const { onChange } = initializeTemplates({
      templates: [options[0], options[1], options[2]]
    });

    cy.findAllByTestId('delete-row').eq(0).click();

    getRows().should('have.length', 2);
    cy.wrap(onChange).should(() => {
      expect(getLastValues(onChange)).to.deep.equal([options[1], options[2]]);
    });
    cy.focused()
      .should('have.attr', 'data-testid', 'delete-row')
      .closest('li')
      .find('[data-testid="Template"]')
      .should('have.value', options[1].name);
  });

  it('moves the focus to the previous row when the last row is removed, then to the add button when the list becomes empty', () => {
    initializeTemplates();

    cy.findAllByTestId('delete-row').eq(1).click();
    cy.focused()
      .closest('li')
      .find('[data-testid="Template"]')
      .should('have.value', options[0].name);

    cy.findAllByTestId('delete-row').eq(0).click();
    getRows().should('not.exist');
    cy.focused().should('have.attr', 'data-testid', 'Add');
  });

  it('reorders the rows with the keyboard through the drag handle', () => {
    const { onChange } = initializeTemplates();

    cy.moveSortableElement({
      direction: 'down',
      element: cy.findAllByTestId('drag-handle').eq(0)
    });

    cy.get('[data-testid="Template"]')
      .eq(0)
      .should('have.value', options[1].name);
    cy.get('[data-testid="Template"]')
      .eq(1)
      .should('have.value', options[0].name);
    cy.wrap(onChange).should(() => {
      expect(getLastValues(onChange)).to.deep.equal([options[1], options[0]]);
    });
  });

  it('passes the up-to-date row value and index to the actions after a reorder', () => {
    const { onEdit } = initializeTemplates();

    cy.moveSortableElement({
      direction: 'down',
      element: cy.findAllByTestId('drag-handle').eq(0)
    });
    cy.get('[data-testid="Template"]')
      .eq(0)
      .should('have.value', options[1].name);

    cy.findAllByTestId('edit-template').eq(1).click();
    cy.wrap(onEdit).should('have.been.calledWith', options[0], 1);
  });

  it('hides the drag handle when the list is not draggable', () => {
    initializeTemplates({ draggable: false });

    cy.findAllByTestId('delete-row').should('have.length', 2);
    cy.findByTestId('drag-handle').should('not.exist');
  });

  it('lets the inputs take the remaining width and keeps the row buttons at a 32px pitch', () => {
    initializeTemplates();

    cy.get('[data-row-fields]')
      .eq(0)
      .next()
      .find('button')
      .should('have.length', 3)
      .then(($buttons) => {
        const lefts = $buttons
          .toArray()
          .map((button) => button.getBoundingClientRect().left);

        lefts.slice(1).forEach((left, index) => {
          expect(left - lefts[index]).to.equal(32);
        });
      });

    cy.get('[data-row-fields]')
      .eq(0)
      .invoke('outerWidth')
      .should('be.greaterThan', 500);
  });
});

interface MacroRow {
  isPassword: boolean;
  name: string;
  origin: 'direct' | 'fromTpl';
  value: string;
}

const macros: Array<MacroRow> = [
  { isPassword: false, name: 'MACRO_A', origin: 'direct', value: 'a' },
  { isPassword: true, name: 'MACRO_B', origin: 'fromTpl', value: 'b' }
];

const emptyMacro: MacroRow = {
  isPassword: false,
  name: '',
  origin: 'direct',
  value: ''
};

const renderMacroRow = ({
  setField,
  value: macro
}: RenderRowParams<MacroRow>): JSX.Element => (
  <div className="flex gap-2">
    <TextField
      dataTestId="macro-name"
      label="Name"
      onChange={(event): void => setField('name', event.target.value)}
      value={macro.name}
    />
    <TextField
      dataTestId="macro-value"
      label="Value"
      onChange={(event): void => setField('value', event.target.value)}
      type={macro.isPassword ? 'password' : 'text'}
      value={macro.value}
    />
  </div>
);

const MacrosList = ({
  copyOnChange,
  onChangeSpy
}: {
  copyOnChange: boolean;
  onChangeSpy: Stub;
}): JSX.Element => {
  const [values, setValues] = useState(macros);

  const change = (nextValues: Array<MacroRow>): void => {
    onChangeSpy(nextValues);
    // Simulates a parent that stores a copy (new array and objects), like a
    // form library that clones its state.
    setValues(
      copyOnChange ? nextValues.map((macro) => ({ ...macro })) : nextValues
    );
  };

  return (
    <>
      <button
        data-testid="reset"
        onClick={(): void => setValues([emptyMacro])}
        type="button"
      >
        Reset
      </button>
      <SortableEntriesList<MacroRow>
        actions={({ index, value }) => [
          {
            icon: <VisibilityIcon />,
            id: 'toggle-password',
            label: 'Toggle password',
            onClick: () =>
              change(
                values.map((macro, macroIndex) =>
                  macroIndex === index
                    ? { ...macro, isPassword: !value.isPassword }
                    : macro
                )
              )
          }
        ]}
        createValue={(): MacroRow => emptyMacro}
        getRowClassName={({ value }) => `origin-${value.origin}`}
        onChange={change}
        renderRow={renderMacroRow}
        values={values}
      />
    </>
  );
};

const initializeMacros = ({
  copyOnChange = false
}: {
  copyOnChange?: boolean;
} = {}): { onChange: Stub } => {
  const onChange = cy.stub();

  cy.mount({
    Component: <MacrosList copyOnChange={copyOnChange} onChangeSpy={onChange} />
  });

  return { onChange };
};

describe('SortableEntriesList - multiple input rows', () => {
  it('renders every input of every row', () => {
    initializeMacros();

    cy.get('input[data-testid="macro-name"]').should('have.length', 2);
    cy.get('input[data-testid="macro-value"]').should('have.length', 2);
    cy.get('input[data-testid="macro-value"]')
      .eq(1)
      .should('have.attr', 'type', 'password');
  });

  it('keeps the focus in the edited input while typing and reports a structured value', () => {
    const { onChange } = initializeMacros();

    cy.get('input[data-testid="macro-name"]').eq(0).clear();
    cy.get('input[data-testid="macro-name"]').eq(0).type('HOST_IP');
    cy.focused().should('have.value', 'HOST_IP');

    cy.get('input[data-testid="macro-value"]').eq(0).clear();
    cy.get('input[data-testid="macro-value"]').eq(0).type('10.0.0.1');
    cy.focused().should('have.value', '10.0.0.1');

    cy.wrap(onChange).should(() => {
      expect(getLastValues(onChange)).to.deep.equal([
        {
          isPassword: false,
          name: 'HOST_IP',
          origin: 'direct',
          value: '10.0.0.1'
        },
        macros[1]
      ]);
    });
  });

  it('keeps the focus when a new row is typed into right after being added', () => {
    const { onChange } = initializeMacros();

    cy.findByTestId('Add').click();
    cy.focused().should('have.attr', 'data-testid', 'macro-name').type('NEW');
    cy.get('input[data-testid="macro-value"]').eq(2).type('value');

    cy.wrap(onChange).should(() => {
      expect(getLastValues<MacroRow>(onChange)[2]).to.deep.equal({
        ...emptyMacro,
        name: 'NEW',
        value: 'value'
      });
    });
  });

  it('keeps the rows and the focus stable when the parent stores a copy of the values', () => {
    const { onChange } = initializeMacros({ copyOnChange: true });

    cy.get('input[data-testid="macro-name"]').eq(1).clear();
    cy.get('input[data-testid="macro-name"]').eq(1).type('RENAMED');
    cy.focused().should('have.value', 'RENAMED');

    cy.findByTestId('Add').click();
    cy.focused().should('have.attr', 'data-testid', 'macro-name').type('NEW');

    cy.wrap(onChange).should(() => {
      expect(
        getLastValues<MacroRow>(onChange).map(({ name }) => name)
      ).to.deep.equal(['MACRO_A', 'RENAMED', 'NEW']);
    });
  });

  it('renders the new values when the parent replaces them', () => {
    initializeMacros();

    cy.findByTestId('reset').click();

    getRows().should('have.length', 1);
    cy.get('input[data-testid="macro-name"]').should('have.value', '');
  });

  it('lets a row action change its own row', () => {
    initializeMacros();

    cy.findAllByTestId('toggle-password').eq(0).click();

    cy.get('input[data-testid="macro-value"]')
      .eq(0)
      .should('have.attr', 'type', 'password');
  });

  it('applies the class returned by getRowClassName to each row', () => {
    initializeMacros();

    getRows().eq(0).should('have.class', 'origin-direct');
    getRows().eq(1).should('have.class', 'origin-fromTpl');
  });
});
