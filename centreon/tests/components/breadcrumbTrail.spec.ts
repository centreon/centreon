import {
  labelBreadcrumbCopied,
  labelCopyBreadcrumb
} from '../../www/front_src/src/BreadcrumbTrail/translatedLabels';
import { expect, test } from './fixtures';

test('displays the trail of the current page', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount('BreadcrumbTrail/Default');
  const links = component.getByRole('link');

  await expect(links).toHaveText(['Monitoring', 'Resources Status']);
  await expect(links.first()).toHaveAttribute('href', '/monitoring/resources');
  await expect(links.last()).toHaveAttribute('href', '/monitoring/resources');

  await expectThemedScreenshot(component, 'breadcrumb-trail');
});

test('displays the additional label of a page', async ({ mount }) => {
  const component = await mount('BreadcrumbTrail/WithAdditionalLabel');

  await expect(component.getByText('BETA')).toBeVisible();
});

test('copies the trail from the icon shown on hover', async ({
  context,
  mount,
  page
}) => {
  // Real clipboard instead of the Cypress writeText stub.
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  const component = await mount('BreadcrumbTrail/Default');
  const copyButton = component.getByLabel(labelCopyBreadcrumb);

  await component.getByText('Monitoring').hover();
  await expect(copyButton).toHaveCSS('opacity', '1');
  await copyButton.click();

  // The snackbar is portalled: query it from the page.
  await expect(page.getByText(labelBreadcrumbCopied)).toBeVisible();
  expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(
    'Monitoring > Resources Status'
  );
});
