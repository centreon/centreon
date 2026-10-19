import { Typography } from '@mui/material';

import { faker } from '@faker-js/faker';
import { useFormikContext } from 'formik';
import { useState } from 'react';
import { object } from 'yup';

import { Button } from '../components';
import { Form } from './Form';
import { InputType } from './Inputs/models';

const noop = (): void => undefined;

const AddItem = ({ addItem }: { addItem: (item) => void }): JSX.Element => {
  const { values } = useFormikContext<{ list: Array<unknown> }>();

  return (
    <Button
      onClick={() =>
        addItem({ alias: faker.company.name(), id: values.list.length, name: faker.person.firstName() })
      }
      variant="ghost"
    >
      Add item
    </Button>
  );
};

const SortContent = (item: object): JSX.Element => {
  const { alias, name } = item as { alias: string; name: string };

  return (
    <Typography>
      {name} ({alias})
    </Typography>
  );
};

// Seeded on each mount: the added items are the same in every test.
export const List = (): JSX.Element => {
  useState(() => faker.seed(42));

  return (
    <Form
      initialValues={{ list: [] }}
      inputs={[
        {
          fieldName: 'list',
          group: '',
          label: '',
          list: {
            AddItem,
            addItemLabel: 'Add an item to the list',
            itemProps: ['id', 'name', 'alias'],
            SortContent,
            sortLabel: 'Sort items'
          },
          type: InputType.List
        }
      ]}
      submit={noop}
      validationSchema={object()}
    />
  );
};

export const File = (): JSX.Element => (
  <Form
    initialValues={{ list: [] }}
    inputs={[{ fieldName: 'list', file: { accept: '.json' } as never, group: '', label: 'json', type: InputType.File }]}
    submit={noop}
    validationSchema={object()}
  />
);

const names = ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth', 'Seventh', 'Eighth', 'Ninth'];

export const Sections = (): JSX.Element => (
  <Form
    groups={[
      { name: 'First group', order: 1 },
      { name: 'Third group', order: 3 },
      { name: 'Second group', order: 2 },
      { name: 'Fourth group', order: 4 }
    ]}
    initialValues={{ list: [] }}
    inputs={[
      ...names.flatMap((name, index) => [
        { fieldName: `${name} name`, group: 'First group', label: 'Name', type: InputType.Text },
        ...(index === 0
          ? [{ fieldName: 'Divider', group: 'First group', label: 'Divider', type: InputType.Divider }]
          : [])
      ]),
      ...['Second', 'Third', 'Fourth'].map((group) => ({
        fieldName: `First ${group.toLowerCase()} group name`,
        group: `${group} group`,
        label: 'Name',
        type: InputType.Text
      }))
    ]}
    isCollapsible
    submit={noop}
    validationSchema={object()}
  />
);
