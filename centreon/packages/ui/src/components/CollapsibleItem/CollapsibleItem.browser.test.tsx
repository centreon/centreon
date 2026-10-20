import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { CollapsibleItem, type Props } from './CollapsibleItem';

const title = 'Title';

const customizedTitle = <div>Customized title</div>;

const initialize = ({ title, ...props }: Omit<Props, 'children'>): void => {
  // The component also takes the accordion summary props, whose `title` is
  // a string: its own `title` accepts an element as well.
  render(
    <CollapsibleItem {...props} title={title as string}>
      Content
    </CollapsibleItem>
  );
};

const getExpandButton = (expanded: boolean): HTMLElement =>
  document.querySelector(`button[aria-expanded="${expanded}"]`) as HTMLElement;

// What the snapshots showed: the expand icon on the right of the summary.
const expectExpandIconVisible = (): void => {
  expect(screen.getByTestId('ExpandMoreIcon')).toBeVisible();
};

// A string title is rendered as an h6 heading, a customized one as given.
const expectStringTitleHeading = (): void => {
  expect(screen.getByRole('heading', { level: 6, name: title })).toBeVisible();
};

// The details padding: none when compact, 16px aside otherwise.
const getDetailsPaddingLeft = (): string =>
  getComputedStyle(
    screen.getByText('Content').closest('.MuiAccordionDetails-root') as Element
  ).paddingLeft;

describe('CollapsibleItem', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the component collapsed by default', async () => {
    initialize({ title });

    expect(await screen.findByText(title)).toBeVisible();
    expect(screen.getByText('Content')).not.toBeVisible();
    expect(getExpandButton(false)).toBeInTheDocument();

    expectStringTitleHeading();
    expectExpandIconVisible();
  });

  it('displays the component expanded when the corresponding prop is set to true', async () => {
    initialize({ defaultExpanded: true, title });

    expect(await screen.findByText(title)).toBeVisible();
    expect(screen.getByText('Content')).toBeVisible();
    expect(getExpandButton(true)).toBeInTheDocument();

    expectStringTitleHeading();
    expectExpandIconVisible();
    expect(getDetailsPaddingLeft()).toBe('16px');
  });

  it('displays a customized title', async () => {
    initialize({ title: customizedTitle });

    expect(await screen.findByText('Customized title')).toBeVisible();
    expect(getExpandButton(false)).toBeInTheDocument();

    expect(screen.queryByRole('heading', { level: 6 })).not.toBeInTheDocument();
    expectExpandIconVisible();
  });

  it('displays the component as compact', async () => {
    initialize({ compact: true, title });

    expect(await screen.findByText(title)).toBeVisible();
    expect(getExpandButton(false)).toBeInTheDocument();
    expect(
      document.querySelector('div[data-compact="true"]')
    ).toBeInTheDocument();

    expectStringTitleHeading();
    expectExpandIconVisible();
  });

  it('displays the component as compact and expanded when the icon is clicked', async () => {
    initialize({ compact: true, title });

    expect(await screen.findByText(title)).toBeVisible();
    expect(getExpandButton(false)).toBeInTheDocument();

    await userEvent.click(getExpandButton(false));

    await waitFor(() => {
      expect(getExpandButton(true)).toBeInTheDocument();
    });
    await waitFor(() => {
      expect(screen.getByText('Content')).toBeVisible();
    });

    expect(getDetailsPaddingLeft()).toBe('0px');
  });

  it('displays the component as compact and a customized title', async () => {
    initialize({ compact: true, title: customizedTitle });

    expect(await screen.findByText('Customized title')).toBeVisible();
    expect(getExpandButton(false)).toBeInTheDocument();
    expect(
      document.querySelector('div[data-compact="true"]')
    ).toBeInTheDocument();

    expect(screen.queryByRole('heading', { level: 6 })).not.toBeInTheDocument();
    expectExpandIconVisible();
  });
});
