import { describe, expect, it } from '@rstest/core';

import AuthenticationDenied from '../../www/front_src/src/FallbackPages/AuthenticationDenied';
import {
  labelAuthenticationDenied,
  labelYouAreNotAbleToLogIn
} from '../../www/front_src/src/FallbackPages/AuthenticationDenied/translatedLabels';
import { renderApp, screen } from './render';

/** Phase 0b port: a render-only fallback page (no API, no state). */
describe('AuthenticationDenied (Rstest app POC)', () => {
  it('displays the authentication denied page', () => {
    renderApp(<AuthenticationDenied />);

    expect(screen.getByText(labelYouAreNotAbleToLogIn)).toBeVisible();
    expect(screen.getByText(labelAuthenticationDenied)).toBeVisible();
  });
});
