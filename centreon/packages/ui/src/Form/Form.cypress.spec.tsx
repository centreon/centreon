import { faker } from '@faker-js/faker';
import { useFormikContext } from 'formik';
import { object } from 'yup';

import { Typography } from '@mui/material';

import { Button } from '../components';

import { Form } from './Form';
import { InputType } from './Inputs/models';

faker.seed(42);

const AddItem = ({ addItem }: { addItem: (item) => void }): JSX.Element => {
  const { values } = useFormikContext();
  const add = (): void => {
    addItem({
      alias: faker.company.name(),
      id: values.list.length,
      name: faker.person.firstName()
    });
  };

  return (
    <Button variant="ghost" onClick={add}>
      Add item
    </Button>
  );
};

const SortContent = ({
  name,
  alias
}: {
  alias: string;
  name: string;
}): JSX.Element => (
  <Typography>
    {name} ({alias})
  </Typography>
);

const initializeFormList = (): void => {
  cy.mount({
    Component: (
      <Form
        initialValues={{
          list: []
        }}
        inputs={[
          {
            fieldName: 'list',
            group: '',
            label: '',
            list: {
              AddItem,
              SortContent,
              addItemLabel: 'Add an item to the list',
              itemProps: ['id', 'name', 'alias'],
              sortLabel: 'Sort items'
            },
            type: InputType.List
          }
        ]}
        submit={cy.stub()}
        validationSchema={object()}
      />
    )
  });
};

describe('Form list', () => {
  beforeEach(initializeFormList);

  it('adds an element to the list', () => {
    cy.contains('Add an item to the list').should('be.visible');
    cy.contains('Sort items').should('be.visible');

    cy.contains('Add item').click();

    cy.findByLabelText('sort-0').should('be.visible');
    cy.findByLabelText('delete-0').should('be.visible');
    cy.contains('Lavinia (Wiegand LLC)').should('be.visible');

    cy.makeSnapshot();
  });

  it('sorts elements in the list', () => {
    cy.contains('Add an item to the list').should('be.visible');
    cy.contains('Sort items').should('be.visible');

    cy.contains('Add item').click();
    cy.contains('Add item').click();

    cy.findByLabelText('sort-0').should('be.visible');
    cy.findByLabelText('delete-0').should('be.visible');
    cy.contains('Sammie (Crist - Beer)').should('be.visible');
    cy.findByLabelText('sort-1').should('be.visible');
    cy.findByLabelText('delete-1').should('be.visible');
    cy.contains('Waino (Quigley Group)').should('be.visible');

    cy.moveSortableElementUsingAriaLabel({
      ariaLabel: 'sort-0',
      direction: 'down'
    });

    cy.contains('Waino (Quigley Group)').should('be.visible');
    cy.contains('Sammie (Crist - Beer)').should('be.visible');

    cy.makeSnapshot();
  });

  it('removes an element from the list', () => {
    cy.contains('Add an item to the list').should('be.visible');
    cy.contains('Sort items').should('be.visible');

    cy.contains('Add item').click();
    cy.contains('Add item').click();

    cy.findByLabelText('sort-0').should('be.visible');
    cy.findByLabelText('delete-0').should('be.visible');
    cy.contains('Elliott (Effertz, Deckow and Deckow)').should('be.visible');
    cy.findByLabelText('sort-1').should('be.visible');
    cy.findByLabelText('delete-1').should('be.visible');
    cy.contains('Leopoldo (Kemmer Inc)').should('be.visible');

    cy.findByLabelText('delete-0').click();

    cy.contains('Elliott (Effertz, Deckow and Deckow)').should('not.exist');

    cy.makeSnapshot();
  });
});

const initializeFormWithSections = (): void => {
  cy.mount({
    Component: (
      <Form
        isCollapsible
        initialValues={{
          list: []
        }}
        groups={[
          {
            name: 'First group',
            order: 1
          },
          {
            name: 'Third group',
            order: 3
          },
          {
            name: 'Second group',
            order: 2
          },
          {
            name: 'Fourth group',
            order: 4
          }
        ]}
        inputs={[
          {
            fieldName: 'First name',
            group: 'First group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'Divider',
            group: 'First group',
            label: 'Divider',
            type: InputType.Divider
          },
          {
            fieldName: 'Second name',
            group: 'First group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'Third name',
            group: 'First group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'Fourth name',
            group: 'First group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'Fifth name',
            group: 'First group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'Sixth name',
            group: 'First group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'Seventh name',
            group: 'First group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'Eighth name',
            group: 'First group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'Ninth name',
            group: 'First group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'First second group name',
            group: 'Second group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'First third group name',
            group: 'Third group',
            label: 'Name',
            type: InputType.Text
          },
          {
            fieldName: 'First fourth group name',
            group: 'Fourth group',
            label: 'Name',
            type: InputType.Text
          }
        ]}
        submit={cy.stub()}
        validationSchema={object()}
      />
    )
  });
};

const initializeFile = () => {
  cy.mount({
    Component: (
      <Form
        initialValues={{
          list: []
        }}
        inputs={[
          {
            fieldName: 'list',
            group: '',
            label: 'json',
            type: InputType.File,
            file: {
              accept: '.json'
            }
          }
        ]}
        submit={cy.stub()}
        validationSchema={object()}
      />
    )
  });
};

describe('File', () => {
  it('uploads a file when a file is selected', () => {
    initializeFile();

    cy.contains('Drop or select a file').should('be.visible');
    cy.findByLabelText('select a file').selectFile('package.json', {
      force: true
    });
    cy.contains('package.json').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Form with sections', () => {
  beforeEach(initializeFormWithSections);
  it('displays sections when correct amount of sections', () => {
    cy.contains('First group').should('be.visible');
    cy.contains('Second group').should('be.visible');
    cy.contains('Third group').should('be.visible');
    cy.contains('Fourth group').should('be.visible');
    cy.makeSnapshot();
  });

  it('scrolls correctly to section', () => {
    cy.window().then((win) => {
      const initialScrollY = win.scrollY;
      cy.contains('Third group').click().then(() => {
        cy.window().its('scrollY').should('be.greaterThan', initialScrollY);
        cy.makeSnapshot();
      });
    });
  });
});
