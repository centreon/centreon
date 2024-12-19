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
    cy.contains('Christelle (Schinner - Wiegand)').should('be.visible');

    cy.makeSnapshot();
  });

  it('sorts elements in the list', () => {
    cy.contains('Add an item to the list').should('be.visible');
    cy.contains('Sort items').should('be.visible');

    cy.contains('Add item').click();
    cy.contains('Add item').click();

    cy.findByLabelText('sort-0').should('be.visible');
    cy.findByLabelText('delete-0').should('be.visible');
    cy.contains('Carley (Satterfield, Miller and Metz)').should('be.visible');
    cy.findByLabelText('sort-1').should('be.visible');
    cy.findByLabelText('delete-1').should('be.visible');
    cy.contains('Anderson (Crist - Bradtke)').should('be.visible');

    cy.moveSortableElementUsingAriaLabel({
      ariaLabel: 'sort-0',
      direction: 'down'
    });

    cy.contains('Carley (Satterfield, Miller and Metz)').should('be.visible');
    cy.contains('Anderson (Crist - Bradtke)').should('be.visible');

    cy.makeSnapshot();
  });

  it('removes an element from the list', () => {
    cy.contains('Add an item to the list').should('be.visible');
    cy.contains('Sort items').should('be.visible');

    cy.contains('Add item').click();
    cy.contains('Add item').click();

    cy.findByLabelText('sort-0').should('be.visible');
    cy.findByLabelText('delete-0').should('be.visible');
    cy.contains('Lea (Streich - Hartmann)').should('be.visible');
    cy.findByLabelText('sort-1').should('be.visible');
    cy.findByLabelText('delete-1').should('be.visible');
    cy.contains('Akeem (Quigley LLC)').should('be.visible');

    cy.findByLabelText('delete-0').click();

    cy.contains('Lea (Streich - Hartmann)').should('not.exist');

    cy.makeSnapshot();
  });
});

const initializeFile = (): void => {
  cy.mount({
    Component: (
      <Form
        initialValues={{
          list: []
        }}
        inputs={[
          {
            fieldName: 'file',
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
