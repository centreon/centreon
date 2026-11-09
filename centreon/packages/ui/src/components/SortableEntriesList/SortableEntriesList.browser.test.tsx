import DescriptionOutlinedIcon from '@mui/icons-material/DescriptionOutlined';
import EditIcon from '@mui/icons-material/Edit';
import VisibilityIcon from '@mui/icons-material/Visibility';

import { rs } from '@rstest/core';
import { interceptAPIRequest, Method, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';

import { TestQueryProvider } from '../..';
import buildListingEndpoint from '../../api/buildListingEndpoint';
import type { SelectEntry } from '../../InputField/Select';
import SingleConnectedAutocompleteField from '../../InputField/Select/Autocomplete/Connected/Single';
import TextField from '../../InputField/Text';
import type { RenderRowParams, RowParams, SortableRowAction } from './models';
import { type Props, SortableEntriesList } from './SortableEntriesList';

type Spy = ReturnType<typeof rs.fn>;

const ControlledList = <T,>({
  initialValues,
  onChangeSpy,
  ...props
}: Omit<Props<T>, 'values' | 'onChange'> & {
  initialValues: Array<T>;
  onChangeSpy: Spy;
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

const getLastValues = <T,>(onChange: Spy): Array<T> =>
  onChange.mock.lastCall?.[0] as Array<T>;

const getRows = (): Array<HTMLElement> => screen.queryAllByRole('listitem');

const getFocused = (): HTMLElement => document.activeElement as HTMLElement;

const getRect = (element: Element): DOMRect => element.getBoundingClientRect();

const getInputs = (testId: string): Array<HTMLInputElement> =>
  Array.from(document.querySelectorAll(`input[data-testid="${testId}"]`));

const getRowButtons = (rowIndex: number): Array<HTMLButtonElement> =>
  Array.from(
    document
      .querySelectorAll('[data-row-fields]')
      [rowIndex].nextElementSibling?.querySelectorAll('button') ?? []
  );

interface MoveSortableElementProps {
  direction: 'up' | 'down';
  handle: HTMLElement;
  times?: number;
}

// The rows other than the dragged one that make way for it: as many as the
// dragged row moved past.
const countShiftedRows = (row: HTMLElement): number =>
  Array.from(row.parentElement?.children ?? []).filter(
    (sibling) =>
      sibling !== row &&
      /translate3d\(0px, -?[1-9]/.test((sibling as HTMLElement).style.transform)
  ).length;

// A keyboard drag: space to pick the row up, the arrows to move it,
// space to drop it. dnd-kit handles a key once the previous one took effect,
// so each step is awaited: the handle pressed once the row is picked up, one
// more row making way after each arrow, the handle released once dropped.
const moveSortableElement = async ({
  handle,
  direction,
  times = 1
}: MoveSortableElementProps): Promise<void> => {
  const row = handle.closest('li') as HTMLElement;
  const key = direction === 'down' ? '{ArrowDown}' : '{ArrowUp}';

  handle.focus();
  await userEvent.keyboard(' ');
  await waitFor(() => {
    expect(handle).toHaveAttribute('aria-pressed', 'true');
  });

  for (let move = 1; move <= times; move += 1) {
    await userEvent.keyboard(key);
    await waitFor(() => {
      expect(countShiftedRows(row)).toBe(move);
    });
  }

  await userEvent.keyboard(' ');
  await waitFor(() => {
    expect(handle).not.toHaveAttribute('aria-pressed', 'true');
  });
};

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

const getTemplateInputs = (): Array<HTMLInputElement> => getInputs('Template');

interface InitializeTemplatesProps {
  draggable?: boolean;
  templates?: Array<TemplateRow>;
}

const initializeTemplates = ({
  draggable = true,
  templates = [options[0], options[1]]
}: InitializeTemplatesProps = {}): { onChange: Spy; onEdit: Spy } => {
  const onChange = rs.fn();
  const onEdit = rs.fn();

  interceptAPIRequest({
    alias: 'getTemplates',
    method: Method.GET,
    path: `**${endpoint}?**`,
    response: {
      meta: { limit: 10, page: 1, total: options.length },
      result: options
    }
  });

  render(
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
  );

  return { onChange, onEdit };
};

describe('SortableEntriesList - single input rows', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays one row per value with its value, its actions, a delete button and a trailing drag handle', async () => {
    initializeTemplates();

    expect(
      await screen.findByRole('list', { name: 'Templates' })
    ).toBeInTheDocument();
    expect(getRows()).toHaveLength(2);
    await waitFor(() => {
      expect(getTemplateInputs()[0]).toHaveValue(options[0].name);
    });
    expect(getTemplateInputs()[1]).toHaveValue(options[1].name);
    expect(screen.getAllByTestId('edit-template')).toHaveLength(2);
    expect(screen.getAllByTestId('delete-row')).toHaveLength(2);

    const firstRowButtons = getRows()[0].querySelectorAll('button');

    expect(firstRowButtons[firstRowButtons.length - 1]).toHaveAttribute(
      'data-testid',
      'drag-handle'
    );

    // The buttons of each row in order (edit, delete, drag handle), the
    // delete button in red, and the add button below the rows.
    getRows().forEach((_row, rowIndex) => {
      expect(
        getRowButtons(rowIndex).map((button) =>
          button.getAttribute('data-testid')
        )
      ).toEqual(['edit-template', 'delete-row', 'drag-handle']);
    });

    const [red, green, blue] = (
      getComputedStyle(screen.getAllByTestId('delete-row')[0]).color.match(
        /\d+/g
      ) ?? []
    ).map(Number);

    expect(red).toBeGreaterThan(green + 100);
    expect(red).toBeGreaterThan(blue + 100);

    const addButton = screen.getByTestId('Add');

    expect(addButton).toBeVisible();
    expect(getRect(addButton).top).toBeGreaterThanOrEqual(
      getRect(getRows()[1]).bottom
    );
  });

  it('reports the plain values, without any internal id, when an option is selected', async () => {
    const { onChange } = initializeTemplates();

    await userEvent.click(getTemplateInputs()[1]);
    await waitForRequest('@getTemplates');
    await userEvent.click(
      await screen.findByRole('option', { name: options[2].name })
    );

    await waitFor(() => {
      expect(getTemplateInputs()[1]).toHaveValue(options[2].name);
    });
    await waitFor(() => {
      expect(getLastValues(onChange)).toEqual([options[0], options[2]]);
    });
  });

  it('lets a row disable the options already selected in sibling rows', async () => {
    initializeTemplates();

    await userEvent.click(getTemplateInputs()[1]);
    await waitForRequest('@getTemplates');

    expect(
      await screen.findByRole('option', { name: options[0].name })
    ).toHaveAttribute('aria-disabled', 'true');
    expect(
      screen.getByRole('option', { name: options[2].name })
    ).not.toHaveAttribute('aria-disabled', 'true');
  });

  it('appends a row built with createValue and focuses its first input', async () => {
    const { onChange } = initializeTemplates();

    await userEvent.click(await screen.findByTestId('Add'));

    await waitFor(() => {
      expect(getRows()).toHaveLength(3);
    });
    expect(getTemplateInputs()[2]).toHaveValue('');
    await waitFor(() => {
      expect(getFocused()).toHaveAttribute('data-testid', 'Template');
    });

    const focusedRow = getFocused().closest('li') as HTMLElement;

    expect(
      Array.from(focusedRow.parentElement?.children ?? []).indexOf(focusedRow)
    ).toBe(2);
    await waitFor(() => {
      expect(getLastValues(onChange)).toEqual([options[0], options[1], null]);
    });
  });

  it('removes a row and moves the focus to the first input of the row taking its place', async () => {
    const { onChange } = initializeTemplates({
      templates: [options[0], options[1], options[2]]
    });

    await userEvent.click((await screen.findAllByTestId('delete-row'))[0]);

    await waitFor(() => {
      expect(getRows()).toHaveLength(2);
    });
    await waitFor(() => {
      expect(getLastValues(onChange)).toEqual([options[1], options[2]]);
    });
    await waitFor(() => {
      expect(getFocused()).toHaveAttribute('data-testid', 'Template');
    });
    expect(getFocused()).toHaveValue(options[1].name);
  });

  it('moves the focus to the previous row when the last row is removed, then to the add button when the list becomes empty', async () => {
    initializeTemplates();

    await userEvent.click((await screen.findAllByTestId('delete-row'))[1]);
    await waitFor(() => {
      expect(
        getFocused()
          .closest('li')
          ?.querySelector('input[data-testid="Template"]')
      ).toHaveValue(options[0].name);
    });

    await userEvent.click(screen.getAllByTestId('delete-row')[0]);
    await waitFor(() => {
      expect(getRows()).toHaveLength(0);
    });
    await waitFor(() => {
      expect(getFocused()).toHaveAttribute('data-testid', 'Add');
    });
  });

  it('reorders the rows with the keyboard through the drag handle', async () => {
    const { onChange } = initializeTemplates();

    await moveSortableElement({
      direction: 'down',
      handle: (await screen.findAllByTestId('drag-handle'))[0]
    });

    await waitFor(() => {
      expect(getTemplateInputs()[0]).toHaveValue(options[1].name);
    });
    expect(getTemplateInputs()[1]).toHaveValue(options[0].name);
    await waitFor(() => {
      expect(getLastValues(onChange)).toEqual([options[1], options[0]]);
    });
  });

  it('passes the up-to-date row value and index to the actions after a reorder', async () => {
    const { onEdit } = initializeTemplates();

    await moveSortableElement({
      direction: 'down',
      handle: (await screen.findAllByTestId('drag-handle'))[0]
    });
    await waitFor(() => {
      expect(getTemplateInputs()[0]).toHaveValue(options[1].name);
    });

    await userEvent.click(screen.getAllByTestId('edit-template')[1]);
    await waitFor(() => {
      expect(onEdit).toHaveBeenCalledWith(options[0], 1);
    });
  });

  it('hides the drag handle when the list is not draggable', async () => {
    initializeTemplates({ draggable: false });

    expect(await screen.findAllByTestId('delete-row')).toHaveLength(2);
    expect(screen.queryByTestId('drag-handle')).not.toBeInTheDocument();
  });

  it('lets the inputs take the remaining width and keeps the row buttons at a 32px pitch', async () => {
    initializeTemplates();

    await screen.findAllByTestId('drag-handle');

    const buttons = getRowButtons(0);

    expect(buttons).toHaveLength(3);

    const lefts = buttons.map((button) => getRect(button).left);

    lefts.slice(1).forEach((left, index) => {
      expect(left - lefts[index]).toBe(32);
    });

    const rowFields = document.querySelectorAll('[data-row-fields]')[0];

    expect(getRect(rowFields).width).toBeGreaterThan(500);
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
  <div className="grid grid-cols-2 gap-2 @max-[600px]:grid-cols-1">
    <TextField
      dataTestId="macro-name"
      fullWidth
      label="Name"
      onChange={(event): void => setField('name', event.target.value)}
      value={macro.name}
    />
    <TextField
      dataTestId="macro-value"
      fullWidth
      label="Value"
      onChange={(event): void => setField('value', event.target.value)}
      type={macro.isPassword ? 'password' : 'text'}
      value={macro.value}
    />
  </div>
);

interface MacrosListProps {
  copyOnChange: boolean;
  onChangeSpy: Spy;
}

const MacrosList = ({
  copyOnChange,
  onChangeSpy
}: MacrosListProps): JSX.Element => {
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
} = {}): { onChange: Spy } => {
  const onChange = rs.fn();

  render(<MacrosList copyOnChange={copyOnChange} onChangeSpy={onChange} />);

  return { onChange };
};

describe('SortableEntriesList - multiple input rows', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('renders every input of every row', async () => {
    initializeMacros();

    await waitFor(() => {
      expect(getInputs('macro-name')).toHaveLength(2);
    });
    expect(getInputs('macro-value')).toHaveLength(2);
    expect(getInputs('macro-value')[1]).toHaveAttribute('type', 'password');
  });

  it('keeps the focus in the edited input while typing and reports a structured value', async () => {
    const { onChange } = initializeMacros();

    await waitFor(() => {
      expect(getInputs('macro-name')).toHaveLength(2);
    });
    await userEvent.clear(getInputs('macro-name')[0]);
    await userEvent.type(getInputs('macro-name')[0], 'HOST_IP');
    await waitFor(() => {
      expect(getFocused()).toHaveValue('HOST_IP');
    });

    await userEvent.clear(getInputs('macro-value')[0]);
    await userEvent.type(getInputs('macro-value')[0], '10.0.0.1');
    await waitFor(() => {
      expect(getFocused()).toHaveValue('10.0.0.1');
    });

    await waitFor(() => {
      expect(getLastValues(onChange)).toEqual([
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

  it('keeps the focus when a new row is typed into right after being added', async () => {
    const { onChange } = initializeMacros();

    await userEvent.click(await screen.findByTestId('Add'));
    await waitFor(() => {
      expect(getFocused()).toHaveAttribute('data-testid', 'macro-name');
    });
    await userEvent.type(getFocused(), 'NEW');
    await userEvent.type(getInputs('macro-value')[2], 'value');

    await waitFor(() => {
      expect(getLastValues<MacroRow>(onChange)[2]).toEqual({
        ...emptyMacro,
        name: 'NEW',
        value: 'value'
      });
    });
  });

  it('keeps the rows and the focus stable when the parent stores a copy of the values', async () => {
    const { onChange } = initializeMacros({ copyOnChange: true });

    await waitFor(() => {
      expect(getInputs('macro-name')).toHaveLength(2);
    });
    await userEvent.clear(getInputs('macro-name')[1]);
    await userEvent.type(getInputs('macro-name')[1], 'RENAMED');
    await waitFor(() => {
      expect(getFocused()).toHaveValue('RENAMED');
    });

    await userEvent.click(screen.getByTestId('Add'));
    await waitFor(() => {
      expect(getFocused()).toHaveAttribute('data-testid', 'macro-name');
    });
    await userEvent.type(getFocused(), 'NEW');

    await waitFor(() => {
      expect(getLastValues<MacroRow>(onChange).map(({ name }) => name)).toEqual(
        ['MACRO_A', 'RENAMED', 'NEW']
      );
    });
  });

  it('renders the new values when the parent replaces them', async () => {
    initializeMacros();

    await userEvent.click(await screen.findByTestId('reset'));

    await waitFor(() => {
      expect(getRows()).toHaveLength(1);
    });
    expect(getInputs('macro-name')[0]).toHaveValue('');
  });

  it('lets a row action change its own row', async () => {
    initializeMacros();

    await userEvent.click((await screen.findAllByTestId('toggle-password'))[0]);

    await waitFor(() => {
      expect(getInputs('macro-value')[0]).toHaveAttribute('type', 'password');
    });
  });

  it('applies the class returned by getRowClassName to each row', async () => {
    initializeMacros();

    await screen.findAllByRole('listitem');

    expect(getRows()[0]).toHaveClass('origin-direct');
    expect(getRows()[1]).toHaveClass('origin-fromTpl');
  });
});

const buildMacros = (count: number): Array<MacroRow> =>
  Array.from({ length: count }, (_, index) => ({
    ...emptyMacro,
    name: `MACRO_${index + 1}`
  }));

const panelWidths = [1400, 900, 720, 560];

interface ResizablePanelProps {
  count: number;
  maxVisibleRows?: number;
  onChangeSpy: Spy;
}

// The panel width is state, so changing it resizes the mounted list at runtime.
const ResizablePanel = ({
  count,
  maxVisibleRows,
  onChangeSpy
}: ResizablePanelProps): JSX.Element => {
  const [width, setWidth] = useState(panelWidths[0]);

  return (
    <div className="p-4">
      {panelWidths.map((panelWidth) => (
        <button
          data-testid={`width-${panelWidth}`}
          key={panelWidth}
          onClick={(): void => setWidth(panelWidth)}
          type="button"
        >
          {panelWidth}
        </button>
      ))}
      <div data-testid="panel" style={{ width }}>
        <ControlledList<MacroRow>
          actions={(): Array<SortableRowAction> => [
            {
              icon: <VisibilityIcon />,
              id: 'toggle-password',
              label: 'Toggle password',
              onClick: () => undefined
            },
            {
              icon: <DescriptionOutlinedIcon />,
              id: 'macro-description',
              label: 'Description',
              onClick: () => undefined
            }
          ]}
          createValue={(): MacroRow => emptyMacro}
          initialValues={buildMacros(count)}
          maxVisibleRows={maxVisibleRows}
          onChangeSpy={onChangeSpy}
          renderRow={renderMacroRow}
        />
      </div>
    </div>
  );
};

interface InitializePanelProps {
  count: number;
  maxVisibleRows?: number;
}

// The viewport is 1280x1000 and the panel widths are explicit, so the 1400px
// panel overflows the page: the list lays out against the panel, not the
// viewport.
const initializePanel = ({
  count,
  maxVisibleRows
}: InitializePanelProps): { onChange: Spy } => {
  const onChange = rs.fn();

  render(
    <ResizablePanel
      count={count}
      maxVisibleRows={maxVisibleRows}
      onChangeSpy={onChange}
    />
  );

  return { onChange };
};

const getScrollArea = (): Promise<HTMLElement> =>
  screen.findByTestId('sortable-entries-scroll');

const getLastNames = (onChange: Spy): Array<string> =>
  getLastValues<MacroRow>(onChange).map(({ name }) => name);

const getMaskImage = (element: HTMLElement): string =>
  getComputedStyle(element).maskImage;

// The list is capped from a ResizeObserver callback. A check that it is not
// capped first waits for an observer of its own, delivered after the list's,
// then a frame for the page to apply the cap.
const waitForResizeObservers = (element: HTMLElement): Promise<void> =>
  new Promise((resolve) => {
    const observer = new ResizeObserver(() => {
      observer.disconnect();
      requestAnimationFrame(() => setTimeout(resolve, 0));
    });

    observer.observe(element);
  });

describe('SortableEntriesList - capped list', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('shows maxVisibleRows rows plus half of the next one, and keeps the add button outside the scroll area', async () => {
    initializePanel({ count: 15, maxVisibleRows: 10 });

    const scrollArea = await getScrollArea();

    await waitFor(() => {
      const rows = scrollArea.querySelectorAll('li');
      const visibleBottom = getRect(scrollArea).bottom;

      expect(scrollArea.scrollHeight).toBeGreaterThan(scrollArea.clientHeight);
      expect(getRect(rows[9]).bottom).toBeLessThanOrEqual(visibleBottom);
      expect(
        Math.abs(
          visibleBottom - getRect(rows[10]).top - getRect(rows[10]).height / 2
        )
      ).toBeLessThanOrEqual(1);
    });

    const addButton = screen.getByTestId('Add');

    expect(addButton).toBeVisible();
    expect(
      addButton.closest('[data-testid="sortable-entries-scroll"]')
    ).not.toBeInTheDocument();
  });

  it('fades the cut-off row only while rows are hidden below', async () => {
    initializePanel({ count: 15, maxVisibleRows: 10 });

    const scrollArea = await getScrollArea();

    await waitFor(() => {
      expect(getMaskImage(scrollArea)).not.toBe('none');
    });
    scrollArea.scrollTo({ top: scrollArea.scrollHeight });
    await waitFor(() => {
      expect(getMaskImage(scrollArea)).toBe('none');
    });
  });

  it('does not cap a list shorter than maxVisibleRows', async () => {
    initializePanel({ count: 5, maxVisibleRows: 10 });

    const scrollArea = await getScrollArea();

    await waitForResizeObservers(scrollArea);

    expect(getComputedStyle(scrollArea).maxHeight).toBe('none');
    expect(scrollArea.scrollHeight).toBe(scrollArea.clientHeight);
  });

  it('focuses and scrolls to a row added at the end of a capped list', async () => {
    initializePanel({ count: 15, maxVisibleRows: 10 });

    const scrollArea = await getScrollArea();

    await waitFor(() => {
      expect(scrollArea.scrollHeight).toBeGreaterThan(scrollArea.clientHeight);
    });
    await userEvent.click(screen.getByTestId('Add'));

    await waitFor(() => {
      expect(getRows()).toHaveLength(16);
    });
    await waitFor(() => {
      const input = getFocused();

      expect(input).toHaveAttribute('data-testid', 'macro-name');

      const scrollAreaRect = getRect(
        input.closest('[data-testid="sortable-entries-scroll"]') as Element
      );
      const inputRect = getRect(input);

      expect(inputRect.top).toBeGreaterThanOrEqual(scrollAreaRect.top);
      expect(inputRect.bottom).toBeLessThanOrEqual(scrollAreaRect.bottom);
    });
  });

  it('scrolls to the focused new row when adding it makes the list reach the cap', async () => {
    initializePanel({ count: 10, maxVisibleRows: 10 });

    const scrollArea = await getScrollArea();

    await waitForResizeObservers(scrollArea);
    expect(getComputedStyle(scrollArea).maxHeight).toBe('none');
    await userEvent.click(screen.getByTestId('Add'));

    await waitFor(() => {
      expect(getRows()).toHaveLength(11);
    });
    await waitFor(() => {
      expect(getComputedStyle(scrollArea).maxHeight).not.toBe('none');
    });
    await waitFor(() => {
      expect(getFocused()).toHaveAttribute('data-testid', 'macro-name');

      const row = getFocused().closest('li') as HTMLElement;
      const scrollAreaRect = getRect(
        row.closest('[data-testid="sortable-entries-scroll"]') as Element
      );

      // 1px tolerance for sub-pixel row heights.
      expect(getRect(row).bottom).toBeLessThanOrEqual(
        scrollAreaRect.bottom + 1
      );
    });
    await waitFor(() => {
      expect(getMaskImage(scrollArea)).toBe('none');
    });
  });

  it('moves a row past the visible area with the keyboard, scrolling the list', async () => {
    const { onChange } = initializePanel({ count: 15, maxVisibleRows: 10 });

    await moveSortableElement({
      direction: 'down',
      handle: (await screen.findAllByTestId('drag-handle'))[0],
      times: 12
    });

    await waitFor(() => {
      expect(getLastNames(onChange).indexOf('MACRO_1')).toBe(12);
    });
    await waitFor(async () => {
      expect((await getScrollArea()).scrollTop).toBeGreaterThan(0);
    });
  });

  it('auto-scrolls while a row is dragged with the pointer near the bottom edge', async () => {
    const { onChange } = initializePanel({ count: 15, maxVisibleRows: 10 });

    const handle = (await screen.findAllByTestId('drag-handle'))[0];
    const scrollArea = await getScrollArea();

    // The list is capped once its rows are measured.
    await waitFor(() => {
      expect(scrollArea.scrollHeight).toBeGreaterThan(scrollArea.clientHeight);
    });

    const { left, top, width, height } = getRect(handle);
    const pointer = {
      button: 0,
      clientX: left + width / 2,
      isPrimary: true,
      pointerId: 1
    };

    // A synthetic pointer drag: dnd-kit listens to the pointer events of the
    // document once the drag started.
    fireEvent.pointerDown(handle, { ...pointer, clientY: top + height / 2 });

    const clientY = getRect(scrollArea).bottom - 5;

    fireEvent.pointerMove(document, { ...pointer, clientY });
    await waitFor(() => {
      expect(scrollArea.scrollTop).toBeGreaterThan(100);
    });
    fireEvent.pointerMove(document, { ...pointer, clientY });
    fireEvent.pointerUp(document, { ...pointer, clientY });

    await waitFor(() => {
      expect(getLastNames(onChange).indexOf('MACRO_1')).toBeGreaterThan(10);
    });
  });
});

describe('SortableEntriesList - container responsive rows', () => {
  const getFieldRects = (): Array<DOMRect> =>
    Array.from(getRows()[0].querySelectorAll('.MuiFormControl-root')).map(
      getRect
    );

  beforeEach(() => {
    expect.hasAssertions();
  });

  it('keeps the fields and the four buttons on one line at 1400, 900 and 720px, resized at runtime', async () => {
    initializePanel({ count: 3 });

    for (const width of [1400, 900, 720, 1400]) {
      await userEvent.click(await screen.findByTestId(`width-${width}`));
      await waitFor(() => {
        expect(getComputedStyle(screen.getByTestId('panel')).width).toBe(
          `${width}px`
        );
      });

      await waitFor(() => {
        const [name, value] = getFieldRects();

        expect(value.top).toBe(name.top);
        expect(name.width).toBeGreaterThanOrEqual(275);
        expect(value.width).toBeGreaterThanOrEqual(275);
      });

      await waitFor(() => {
        const buttons = getRowButtons(0);

        expect(buttons).toHaveLength(4);

        const rects = buttons.map(getRect);
        const panelRight = getRect(screen.getByTestId('panel')).right;

        rects.slice(1).forEach((rect, index) => {
          expect(rect.top).toBe(rects[index].top);
          expect(rect.left - rects[index].left).toBe(32);
        });
        expect(rects[3].right).toBeLessThanOrEqual(panelRight);
      });
    }
  });

  it('stacks the fields when the list gets narrower than 600px, whatever the viewport', async () => {
    initializePanel({ count: 3 });

    await userEvent.click(await screen.findByTestId('width-560'));
    await waitFor(() => {
      const [name, value] = getFieldRects();

      expect(value.top).toBeGreaterThan(name.bottom);
    });

    await userEvent.click(screen.getByTestId('width-900'));
    await waitFor(() => {
      const [name, value] = getFieldRects();

      expect(value.top).toBe(name.top);
    });
  });
});
