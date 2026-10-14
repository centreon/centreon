import {
  labelAuthenticationDenied,
  labelYouAreNotAbleToLogIn
} from '../../www/front_src/src/FallbackPages/AuthenticationDenied/translatedLabels';
import { expect, test } from './fixtures';

test('displays the authentication denied page', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount('AuthenticationDenied/Default');

  await expect(component.getByText(labelYouAreNotAbleToLogIn)).toBeVisible();
  await expect(component.getByText(labelAuthenticationDenied)).toBeVisible();

  await expectThemedScreenshot(component, 'authentication-denied');
});
