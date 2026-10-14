import { expect, test } from './fixtures';

const storyId = 'About/About/Default';

// Same frozen date as the Cypress spec, for the copyright year.
test.beforeEach(async ({ page }) => {
  await page.clock.setFixedTime(new Date(2021, 1, 1));
});

// Same viewport as cy.viewport('ipad-mini', 'portrait').
test.use({ viewport: { height: 1024, width: 768 } });

test('displays the platform version and the community links', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);

  await expect(component.getByText('23.04.0')).toBeVisible();
  await expect(
    component.getByLabel('Star centreon/centreon on GitHub')
  ).toHaveAttribute('href', 'https://github.com/centreon/centreon');
  await expect(component.getByText('Project leaders')).toHaveCount(0);
  await expect(
    component.getByRole('link', { name: 'See the full list on GitHub' })
  ).toHaveAttribute('href', /graphs\/contributors/);
  await expect(
    component.getByRole('link', { name: 'Report a vulnerability' })
  ).toHaveAttribute('href', /security\/policy/);

  for (const action of [
    'Browse the docs',
    'Join The Watch',
    'Open the repository',
    'Compare Edition licenses'
  ]) {
    await expect(component.getByText(action)).toBeVisible();
  }
  await expect(component.getByText('Open source edition')).toHaveCount(0);
  await expect(component.getByText('Start free trial')).toHaveCount(0);
  await expect(
    component.getByText('Copyright © 2005 - 2021 Centreon')
  ).toBeVisible();

  await expectThemedScreenshot(component, 'about');
});

test.describe('in dark mode', () => {
  test.use({ colorScheme: 'dark' });

  test('renders the section titles in white', async ({ mount }) => {
    const component = await mount(storyId);

    await expect(component.getByText('Project & contributors')).toHaveCSS(
      'color',
      'rgb(255, 255, 255)'
    );
  });
});
