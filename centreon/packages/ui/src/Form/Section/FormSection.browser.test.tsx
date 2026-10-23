import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { object } from 'yup';

import { Form } from '../Form';
import { InputType } from '../Inputs/models';

const sectionNames = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'];

const SectionContent = (): JSX.Element => <div style={{ height: 400 }} />;

const groups = sectionNames.map((name, index) => ({
  name,
  order: index + 1
}));

const inputs = sectionNames.map((name) => ({
  custom: { Component: SectionContent },
  fieldName: name,
  group: name,
  label: name,
  type: InputType.Custom
}));

const FormInScrollArea = ({ testId }: { testId: string }): JSX.Element => (
  <div
    data-testid={testId}
    style={{ height: 300, overflowY: 'auto', position: 'relative' }}
  >
    <Form
      groups={groups}
      initialValues={{}}
      inputs={inputs}
      submit={rs.fn()}
      validationSchema={object()}
    />
  </div>
);

const getScrollArea = (testId = 'scroll-area'): HTMLElement =>
  screen.getByTestId(testId);

const getTab = (name: string, testId = 'scroll-area'): HTMLElement =>
  within(getScrollArea(testId)).getByRole('tab', { name });

const getSelectedTabs = (testId = 'scroll-area'): Array<string> =>
  within(getScrollArea(testId))
    .getAllByRole('tab')
    .filter((tab) => tab.getAttribute('aria-selected') === 'true')
    .map((tab) => tab.getAttribute('aria-label') as string);

const scrollToSection = (name: string, testId = 'scroll-area'): void => {
  const scrollArea = getScrollArea(testId);
  const anchor = within(scrollArea).getByTestId(`${name}-header`);

  scrollArea.scrollTop +=
    anchor.getBoundingClientRect().top -
    scrollArea.getBoundingClientRect().top -
    48;
};

describe('Form section navigation', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('activates the first tab when the form is displayed', async () => {
    render(<FormInScrollArea testId="scroll-area" />);

    expect(await screen.findByRole('tab', { name: 'Alpha' })).toBeVisible();
    expect(getSelectedTabs()).toStrictEqual(['Alpha']);
  });

  it('activates the tab of the section in view when the form is scrolled down then up', async () => {
    render(<FormInScrollArea testId="scroll-area" />);
    await screen.findByRole('tab', { name: 'Alpha' });

    scrollToSection('Charlie');

    await waitFor(() => {
      expect(getSelectedTabs()).toStrictEqual(['Charlie']);
    });

    scrollToSection('Bravo');

    await waitFor(() => {
      expect(getSelectedTabs()).toStrictEqual(['Bravo']);
    });
  });

  it('activates the last tab when the form is scrolled to the bottom', async () => {
    render(<FormInScrollArea testId="scroll-area" />);
    await screen.findByRole('tab', { name: 'Alpha' });

    const scrollArea = getScrollArea();
    scrollArea.scrollTop = scrollArea.scrollHeight;

    await waitFor(() => {
      expect(getSelectedTabs()).toStrictEqual(['Echo']);
    });
  });

  it('scrolls to the section and keeps its tab active all along when a tab is clicked', async () => {
    render(<FormInScrollArea testId="scroll-area" />);
    await screen.findByRole('tab', { name: 'Alpha' });

    const tablist = within(getScrollArea()).getByRole('tablist');
    const selectedWhileScrolling = new Set<string>();
    const observer = new MutationObserver(() => {
      for (const tab of getSelectedTabs()) {
        selectedWhileScrolling.add(tab);
      }
    });
    observer.observe(tablist, {
      attributeFilter: ['aria-selected'],
      attributes: true,
      subtree: true
    });

    await userEvent.click(getTab('Delta'));

    await waitFor(() => {
      expect(getScrollArea().scrollTop).toBeGreaterThan(0);
    });
    await waitFor(() => {
      expect(
        within(getScrollArea())
          .getByTestId('Delta-header')
          .getBoundingClientRect().top -
          getScrollArea().getBoundingClientRect().top
      ).toBeLessThan(100);
    });
    observer.disconnect();

    expect(getSelectedTabs()).toStrictEqual(['Delta']);
    expect([...selectedWhileScrolling]).toStrictEqual(['Delta']);
  });

  it('only scrolls its own form when two forms share the same group names', async () => {
    render(
      <>
        <FormInScrollArea testId="first-form" />
        <FormInScrollArea testId="second-form" />
      </>
    );
    await waitFor(() => {
      expect(screen.getAllByRole('tab', { name: 'Alpha' })).toHaveLength(2);
    });

    await userEvent.click(getTab('Charlie', 'second-form'));

    await waitFor(() => {
      expect(getScrollArea('second-form').scrollTop).toBeGreaterThan(0);
    });

    expect(getScrollArea('first-form').scrollTop).toBe(0);
    expect(getSelectedTabs('first-form')).toStrictEqual(['Alpha']);
  });
});
