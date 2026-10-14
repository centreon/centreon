import { expect, test } from './fixtures';

// Migrated from the Cypress specs of AgentConfiguration/Form/ConnectionInitiated.

test.describe('Add host button', () => {
  const storyId = 'HostConfigurations/AddButton/Default';

  test('adds a host when the button is clicked', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId);
    const button = component.getByRole('button', { name: 'Add a host' });

    await expect(button).toBeVisible();
    await expect(button).toHaveText('Add a host');
    await expect(button.getByTestId('AddCircleIcon')).toBeVisible();
    await expectThemedScreenshot(button, 'add-host-button');

    await button.click();

    await expect(component.getByTestId('add-count')).toHaveValue('1');
  });

  test('does not add a host when disabled', async ({ mount }) => {
    const component = await mount(storyId, { addButtonDisabled: true });
    const button = component.getByRole('button', { name: 'Add a host' });

    await expect(button).toBeDisabled();
    await button.click({ force: true });

    await expect(component.getByTestId('add-count')).toHaveValue('0');
  });
});

test.describe('Tabs', () => {
  const storyId = 'ConnectionInitiated/Tabs/Default';

  test('renders the tabs with their labels and contents', async ({ mount }) => {
    const component = await mount(storyId);
    const tabs = component.getByRole('tab');

    await expect(tabs).toHaveText(['Tab 1', 'Tab 2', 'Tab 3']);
    for (const name of ['Tab 1', 'Tab 2', 'Tab 3']) {
      await expect(
        component.getByRole('tab', { exact: true, name })
      ).toHaveAttribute('aria-label', name);
    }
    for (const value of ['tab1', 'tab2', 'tab3']) {
      await expect(component.getByTestId(`${value}-content`)).toBeAttached();
    }
  });

  test('selects the default tab, then the clicked ones', async ({ mount }) => {
    const component = await mount(storyId);
    const tab = (name: string) =>
      component.getByRole('tab', { exact: true, name });

    await expect(tab('Tab 1')).toHaveAttribute('aria-selected', 'true');
    await expect(tab('Tab 2')).toHaveAttribute('aria-selected', 'false');

    await tab('Tab 3').click();
    await expect(tab('Tab 3')).toHaveAttribute('aria-selected', 'true');

    await tab('Tab 1').click();
    await expect(tab('Tab 1')).toHaveAttribute('aria-selected', 'true');
    await expect(tab('Tab 3')).toHaveAttribute('aria-selected', 'false');
  });

  test('honours another default tab', async ({ mount }) => {
    const component = await mount(storyId, { defaultTab: 'tab2' });

    await expect(
      component.getByRole('tab', { exact: true, name: 'Tab 2' })
    ).toHaveAttribute('aria-selected', 'true');
  });

  test('passes props to the tab list', async ({ mount }) => {
    const component = await mount(storyId, { tabListTestId: 'custom-tabs' });

    await expect(component.getByTestId('custom-tabs')).toBeAttached();
  });

  test('renders element labels', async ({ mount }) => {
    const component = await mount('ConnectionInitiated/Tabs/WithElementLabels');

    await expect(component.getByRole('tab')).toHaveText([
      'Custom Tab 1',
      'Custom Tab 2'
    ]);
  });
});

test.describe('Host configuration', () => {
  const storyId = 'HostConfigurations/HostConfiguration/Default';

  test('displays the host values and the secure fields', async ({ mount }) => {
    const component = await mount(storyId);

    await expect(component.locator('#DNSIP-label')).toBeVisible();
    await expect(component.locator('#Port-label')).toBeVisible();
    // Replaces findByDisplayValue: current values of every input.
    await expect
      .poll(() =>
        component
          .locator('input')
          .evaluateAll((inputs) =>
            inputs.map((input) => (input as HTMLInputElement).value)
          )
      )
      .toEqual(expect.arrayContaining(['192.168.1.1', '8080', 'test-host']));
    await expect(
      component.getByRole('textbox', { name: 'CA (.crt, .cert, .cer)' })
    ).toBeVisible();
    await expect(
      component.getByTestId('Select existing CMA token')
    ).toBeVisible();
  });

  test('hides the certificate fields out of the secure modes', async ({
    mount
  }) => {
    const component = await mount(storyId, {
      connectionMode: { id: 'other', name: 'Other' }
    });

    await expect(component.getByTestId('CA (.crt, .cert, .cer)')).toHaveCount(
      0
    );
    await expect(component.getByTestId('CA Common Name (CN)')).toHaveCount(0);
    await expect(
      component.getByTestId('Select existing CMA token')
    ).toBeVisible();
  });
});
