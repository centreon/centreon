import { expect, test } from '@playwright/test';

import { commentEndpoint } from '../../www/front_src/src/Resources/Actions/api/endpoint';
import {
  labelAdd,
  labelCancel,
  labelComment
} from '../../www/front_src/src/Resources/translatedLabels';

const storyId = 'AddCommentForm/Default';

const expectedRequestBody = {
  resources: [
    {
      comment: 'My Comment',
      date: '2020-11-26T15:49:39Z',
      id: 0,
      parent: { id: 1 },
      type: 'service'
    }
  ]
};

test('sends a comment request with the given date and the typed comment', async ({
  mount,
  page
}) => {
  const sentBodies: Array<unknown> = [];

  // Routes must be registered before mount(), which navigates. Centreon
  // endpoints are relative ("./api/latest/..."), so the glob matches the
  // resolved path, without the leading dot.
  const endpointGlob = `**/${commentEndpoint.replace(/^\.\//, '')}`;

  await page.route(endpointGlob, async (route) => {
    sentBodies.push(route.request().postDataJSON());

    await route.fulfill({ body: '{}', status: 200 });
  });

  const component = await mount(storyId);

  // The dialog is rendered through a MUI portal, so it lives outside the
  // gallery root: it is queried from the page, not from the component.
  const dialog = page.getByRole('dialog');

  await expect(dialog.getByLabel(labelAdd)).toBeDisabled();

  await dialog.getByLabel(labelComment).fill('My Comment');
  await dialog.getByLabel(labelAdd).click();

  await expect(component.getByTestId('success-payload')).toHaveValue(
    JSON.stringify({ comment: 'My Comment', date: '2020-11-26T15:49:39Z' })
  );

  expect(sentBodies).toEqual([expectedRequestBody]);

  await expect(dialog).toHaveScreenshot('add-comment-form.png');
});

test('calls onClose when the form is cancelled', async ({ mount, page }) => {
  const component = await mount(storyId);

  await expect(component.getByTestId('close-count')).toHaveValue('0');

  // The cancel button carries no aria-label, only its text.
  await page
    .getByRole('dialog')
    .getByRole('button', { name: labelCancel })
    .click();

  await expect(component.getByTestId('close-count')).toHaveValue('1');
});
