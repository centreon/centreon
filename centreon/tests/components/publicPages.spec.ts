import { expect, test } from './fixtures';

// Migrated from publicPages/PublicPagesManager.cypress.spec.tsx.
const storyId = 'PublicPagesManager/Default';

test('displays a not found page when the route does not exist', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId, {
    pathname: '/public/page/not/found'
  });

  await expect(component.getByText('404')).toBeVisible();
  await expect(
    component.getByText('This page could not be found')
  ).toBeVisible();

  await expectThemedScreenshot(component, 'public-page-not-found');
});

test('does not display a not found page for a known public route', async ({
  mount
}) => {
  const component = await mount(storyId, {
    pathname: '/public/dashboards/playlists/hash'
  });

  await expect(component.getByText('404')).toHaveCount(0);
});
