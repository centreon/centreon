import { Typography } from '@mui/material';

import { faker } from '@faker-js/faker';
import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useFormikContext } from 'formik';
import { object } from 'yup';

import type { SelectEntry } from '..';
import { Button } from '../components';
import { Form } from './Form';
import { type InputProps, InputType } from './Inputs/models';

faker.seed(42);

interface Item {
  alias: string;
  id: number;
  name: string;
}

interface ListValues {
  list: Array<Item>;
}

const AddItem = ({
  addItem
}: {
  addItem: (item: SelectEntry) => void;
}): JSX.Element => {
  const { values } = useFormikContext<ListValues>();
  const add = (): void => {
    addItem({
      alias: faker.company.name(),
      id: values.list.length,
      name: faker.person.firstName()
    } as SelectEntry);
  };

  return (
    <Button onClick={add} variant="ghost">
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
  render(
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
            addItemLabel: 'Add an item to the list',
            itemProps: ['id', 'name', 'alias'],
            SortContent: SortContent as React.ComponentType<object>,
            sortLabel: 'Sort items'
          },
          type: InputType.List
        }
      ]}
      submit={rs.fn()}
      validationSchema={object()}
    />
  );
};

// A keyboard drag of the handle: space to pick the item up, an arrow to move
// it one place, space to drop it. dnd-kit handles a key once the previous one
// took effect, so each step is awaited: the item pressed once picked up, the
// other item making way after the arrow, no item pressed once dropped.
const moveSortableElementUsingAriaLabel = async ({
  ariaLabel,
  direction
}: {
  ariaLabel: string;
  direction: 'down' | 'up';
}): Promise<void> => {
  const handle = screen.getByLabelText(ariaLabel);
  const item = handle.closest('[aria-roledescription]') as HTMLElement;
  const isShifted = (element: Element): boolean =>
    /translate3d\(0px, -?[1-9]/.test((element as HTMLElement).style.transform);

  handle.focus();
  await userEvent.keyboard(' ');
  await waitFor(() => {
    expect(item).toHaveAttribute('aria-pressed', 'true');
  });

  await userEvent.keyboard(direction === 'down' ? '{ArrowDown}' : '{ArrowUp}');
  await waitFor(() => {
    expect(
      Array.from(document.querySelectorAll('[aria-roledescription]')).some(
        (other) => other !== item && isShifted(other)
      )
    ).toBe(true);
  });

  await userEvent.keyboard(' ');
  await waitFor(() => {
    expect(document.querySelector('[aria-pressed="true"]')).toBeNull();
  });
};

const getItemTexts = (): Array<string | null> =>
  Array.from(document.querySelectorAll('[aria-label^="sort-"]')).map(
    (handle) => handle.parentElement?.textContent ?? null
  );

const getSaveButton = (): HTMLElement => screen.getByLabelText('save button');

const expectFormButtons = (): void => {
  expect(screen.getByRole('button', { name: 'Reset' })).toBeVisible();
  expect(getSaveButton()).toBeVisible();
  expect(getSaveButton()).toHaveTextContent('Save');
};

describe('Form list', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });
  beforeEach(initializeFormList);

  it('adds an element to the list', async () => {
    expect(await screen.findByText('Add an item to the list')).toBeVisible();
    expect(screen.getByText('Sort items')).toBeVisible();

    await userEvent.click(screen.getByText('Add item'));

    expect(await screen.findByLabelText('sort-0')).toBeVisible();
    expect(screen.getByLabelText('delete-0')).toBeVisible();
    expect(await screen.findByText('Lavinia (Wiegand LLC)')).toBeVisible();

    expect(getItemTexts()).toEqual(['Lavinia (Wiegand LLC)']);
    expectFormButtons();
    await waitFor(() => {
      expect(getSaveButton()).toBeEnabled();
    });
  });

  it('sorts elements in the list', async () => {
    expect(await screen.findByText('Add an item to the list')).toBeVisible();
    expect(screen.getByText('Sort items')).toBeVisible();

    await userEvent.click(screen.getByText('Add item'));
    await userEvent.click(screen.getByText('Add item'));

    expect(await screen.findByLabelText('sort-0')).toBeVisible();
    expect(screen.getByLabelText('delete-0')).toBeVisible();
    expect(await screen.findByText('Sammie (Crist - Beer)')).toBeVisible();
    expect(await screen.findByLabelText('sort-1')).toBeVisible();
    expect(screen.getByLabelText('delete-1')).toBeVisible();
    expect(await screen.findByText('Waino (Quigley Group)')).toBeVisible();

    expect(getItemTexts()).toEqual([
      'Sammie (Crist - Beer)',
      'Waino (Quigley Group)'
    ]);

    await moveSortableElementUsingAriaLabel({
      ariaLabel: 'sort-0',
      direction: 'down'
    });

    expect(await screen.findByText('Waino (Quigley Group)')).toBeVisible();
    expect(screen.getAllByText('Sammie (Crist - Beer)')[0]).toBeVisible();

    // The moved item is below the other one.
    await waitFor(() => {
      expect(getItemTexts()).toEqual([
        'Waino (Quigley Group)',
        'Sammie (Crist - Beer)'
      ]);
    });
    expectFormButtons();
  });

  it('removes an element from the list', async () => {
    expect(await screen.findByText('Add an item to the list')).toBeVisible();
    expect(screen.getByText('Sort items')).toBeVisible();

    await userEvent.click(screen.getByText('Add item'));
    await userEvent.click(screen.getByText('Add item'));

    expect(await screen.findByLabelText('sort-0')).toBeVisible();
    expect(screen.getByLabelText('delete-0')).toBeVisible();
    expect(
      await screen.findByText('Elliott (Effertz, Deckow and Deckow)')
    ).toBeVisible();
    expect(await screen.findByLabelText('sort-1')).toBeVisible();
    expect(screen.getByLabelText('delete-1')).toBeVisible();
    expect(await screen.findByText('Leopoldo (Kemmer Inc)')).toBeVisible();

    await userEvent.click(screen.getByLabelText('delete-0'));

    await waitFor(() => {
      expect(
        screen.queryByText('Elliott (Effertz, Deckow and Deckow)')
      ).not.toBeInTheDocument();
    });

    expect(getItemTexts()).toEqual(['Leopoldo (Kemmer Inc)']);
    expectFormButtons();
  });
});

// The rstest viewport (1000px tall) fits the whole form: the spacer keeps the
// page scrollable for the scroll-to-section test.
const initializeFormWithSections = (): void => {
  render(
    <>
      <Form
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
        initialValues={{
          list: []
        }}
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
        isCollapsible
        submit={rs.fn()}
        validationSchema={object()}
      />
      <div style={{ height: 1000 }} />
    </>
  );
};

const initializeFile = (): void => {
  render(
    <Form
      initialValues={{
        list: []
      }}
      inputs={[
        {
          fieldName: 'list',
          // No custom drop zone content: the default one is shown.
          file: {
            accept: '.json'
          } as InputProps['file'],
          group: '',
          label: 'json',
          type: InputType.File
        }
      ]}
      submit={rs.fn()}
      validationSchema={object()}
    />
  );
};

describe('File', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('uploads a file when a file is selected', async () => {
    initializeFile();

    expect(await screen.findByText('Drop or select a file')).toBeVisible();
    await userEvent.upload(
      screen.getByLabelText('select a file'),
      new File(['{}'], 'package.json', { type: 'application/json' })
    );
    expect(await screen.findByText('package.json')).toBeVisible();

    // The field label above the drop zone, and the selected
    // file listed with a file icon.
    expect(screen.getByRole('heading', { name: 'json' })).toBeVisible();
    expect(
      within(
        screen.getByText('package.json').parentElement as HTMLElement
      ).getByTestId('DescriptionOutlinedIcon')
    ).toBeVisible();
  });
});

describe('Form with sections', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });
  beforeEach(initializeFormWithSections);

  it('displays sections when correct amount of sections', async () => {
    expect((await screen.findAllByText('First group'))[0]).toBeVisible();
    expect(screen.getAllByText('Second group')[0]).toBeVisible();
    expect(screen.getAllByText('Third group')[0]).toBeVisible();
    expect(screen.getAllByText('Fourth group')[0]).toBeVisible();

    // The section tabs sorted by group order, the first one
    // selected, and the first group expanded with its nine fields.
    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual([
      'First group',
      'Second group',
      'Third group',
      'Fourth group'
    ]);
    expect(screen.getByRole('tab', { name: 'First group' })).toHaveAttribute(
      'aria-selected',
      'true'
    );
    expect(
      screen
        .getAllByLabelText('Name')
        .filter((input) => input.tagName === 'INPUT')
        .filter(
          (input) =>
            input.checkVisibility() &&
            getComputedStyle(input).visibility !== 'hidden'
        )
    ).toHaveLength(9);
  });

  it('scrolls correctly to section', async () => {
    const initialScrollY = window.scrollY;

    await userEvent.click(
      await screen.findByRole('tab', { name: 'Third group' })
    );

    await waitFor(() => {
      expect(window.scrollY).toBeGreaterThan(initialScrollY);
    });

    expect(screen.getByRole('tab', { name: 'Third group' })).toHaveAttribute(
      'aria-selected',
      'true'
    );
  });
});
