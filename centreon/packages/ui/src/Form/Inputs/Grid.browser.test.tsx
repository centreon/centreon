import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen } from '@testing-library/react';
import { object } from 'yup';

import { Form } from '../Form';
import { type InputProps, InputType } from './models';

// The line sits half a gap (32px) left of an item.
const halfGap = 16;

const getField = (
  name: string,
  extra: Partial<InputProps> = {}
): Omit<InputProps, 'group'> => ({
  fieldName: name,
  label: name,
  type: InputType.Text,
  ...extra
});

interface InitializeProps {
  columnSeparator?: boolean;
  // The tracks of the grid: what makes the number of columns follow the width.
  columns: string;
  fields: Array<Omit<InputProps, 'group'>>;
  width: number;
}

const initialize = ({
  columns,
  columnSeparator,
  fields,
  width
}: InitializeProps): void => {
  render(
    <div style={{ padding: 40, width }}>
      <Form
        initialValues={{}}
        inputs={[
          {
            fieldName: 'layout',
            grid: {
              columnSeparator,
              columns: fields,
              gridTemplateColumns: columns
            },
            group: '',
            label: 'layout',
            type: InputType.Grid
          }
        ]}
        submit={rs.fn()}
        validationSchema={object()}
      />
    </div>
  );
};

const getItems = (): Array<HTMLElement> =>
  Array.from(document.querySelectorAll('[data-column-separator]'));

// A line is seen when what is painted at its place is the item that draws it:
// outside the grid, where it is clipped, there is something else.
const getVisibleLines = (): Array<boolean> =>
  getItems().map((item) => {
    const { left, top } = item.getBoundingClientRect();

    return document.elementFromPoint(left - halfGap + 0.5, top + 10) === item;
  });

const threeFields = ['one', 'two', 'three'].map((name) => getField(name));

describe('Grid column separator', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('draws nothing unless asked to', async () => {
    initialize({
      columns: 'repeat(2, 1fr)',
      fields: threeFields,
      width: 800
    });

    await screen.findByLabelText('one');

    expect(document.querySelectorAll('[data-column-separator]')).toHaveLength(
      0
    );
  });

  it('draws a dashed line between two columns, not left of the first', async () => {
    initialize({
      columnSeparator: true,
      columns: 'repeat(2, 1fr)',
      fields: [getField('one'), getField('two')],
      width: 800
    });

    await screen.findByLabelText('one');

    expect(getVisibleLines()).toEqual([false, true]);

    const line = getComputedStyle(getItems()[1], '::before');

    expect(line.borderLeftStyle).toBe('dashed');
    expect(line.borderLeftWidth).toBe('1px');
  });

  it('draws nothing when the layout has a single column', async () => {
    initialize({
      columnSeparator: true,
      columns: 'repeat(auto-fill, minmax(500px, 1fr))',
      fields: [getField('one'), getField('two')],
      width: 400
    });

    await screen.findByLabelText('one');

    expect(getVisibleLines()).toEqual([false, false]);
  });

  it('follows the number of columns the layout shows', async () => {
    initialize({
      columnSeparator: true,
      columns: 'repeat(auto-fill, minmax(300px, 1fr))',
      fields: threeFields,
      width: 1100
    });

    await screen.findByLabelText('one');

    expect(getVisibleLines()).toEqual([false, true, true]);
  });

  it('draws the lines of every row when the items wrap', async () => {
    initialize({
      columnSeparator: true,
      columns: 'repeat(3, 1fr)',
      fields: ['a', 'b', 'c', 'd', 'e'].map((name) => getField(name)),
      width: 1100
    });

    await screen.findByLabelText('a');

    expect(getVisibleLines()).toEqual([false, true, true, false, true]);
  });

  it('spans the height of the row, not of the item', async () => {
    initialize({
      columnSeparator: true,
      columns: 'repeat(2, 1fr)',
      fields: [
        getField('short'),
        getField('tall', { text: { multilineRows: 6 } })
      ],
      width: 800
    });

    await screen.findByLabelText('short');

    const [short, tall] = getItems().map(
      (item) => item.getBoundingClientRect().height
    );

    expect(tall).toBeGreaterThan(100);
    expect(short).toBeCloseTo(tall, 0);
  });

  it('leaves no line for a hidden item and follows the items left', async () => {
    initialize({
      columnSeparator: true,
      columns: 'repeat(2, 1fr)',
      fields: [
        getField('one'),
        getField('hidden', { hideInput: () => true }),
        getField('three')
      ],
      width: 800
    });

    await screen.findByLabelText('one');

    expect(getItems()).toHaveLength(2);
    expect(getVisibleLines()).toEqual([false, true]);
  });
});
