import { expect, test } from '@playwright/test';

import {
  labelAuthenticationDenied,
  labelYouAreNotAbleToLogIn
} from '../../www/front_src/src/FallbackPages/AuthenticationDenied/translatedLabels';

test('displays the authentication denied page', async ({ mount }) => {
  const component = await mount('AuthenticationDenied/Default');

  await expect(component.getByText(labelYouAreNotAbleToLogIn)).toBeVisible();
  await expect(component.getByText(labelAuthenticationDenied)).toBeVisible();

  await expect(component).toHaveScreenshot('authentication-denied.png');
});
