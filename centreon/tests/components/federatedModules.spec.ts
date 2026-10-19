import { labelCannotLoadModule } from '../../www/front_src/src/federatedModules/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from components/FederatedComponents and components/FederatedPage.

test('displays the fallback of a federated page', async ({ mount }) => {
  const component = await mount('FederatedFallbacks/PageFallback');

  await expect(component.getByText(labelCannotLoadModule)).toBeVisible();
});

test('displays the fallback of a federated component', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount('FederatedFallbacks/ComponentFallback');

  await expect(component.getByText(labelCannotLoadModule)).toBeVisible();

  await expectThemedScreenshot(component, 'federated-component-fallback');
});

test('does not display a federated page whose route is unknown', async ({
  mount
}) => {
  const component = await mount('FederatedPage/FederatedPage/Default', {
    route: '/another-route'
  });

  await expect(component.getByText(labelCannotLoadModule)).toBeVisible();
});
