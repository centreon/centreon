import { expect, type Locator, type Page } from '@playwright/test';

import { PasswordSecurityPolicyForm } from '../components/PasswordSecurityPolicyForm';
import { Snackbar } from '../components/Snackbar';
import { BasePage } from './BasePage';

/** Tab labels come from www/front_src/src/Authentication/**\/translatedLabels.ts. */
export type AuthenticationTab =
  | 'OpenID Connect Configuration'
  | 'Password security policy'
  | 'SAML configuration'
  | 'Web SSO configuration';

const labels = {
  passwordSecurityPolicySaved: 'Password security policy saved'
} as const;

export class AuthenticationPage extends BasePage {
  protected readonly path = '/administration/authentication';

  readonly tabList: Locator;
  readonly tabPanel: Locator;
  readonly passwordSecurityPolicy: PasswordSecurityPolicyForm;
  readonly snackbar: Snackbar;

  constructor(page: Page) {
    super(page);

    this.tabList = page.getByRole('tablist');
    this.tabPanel = page.getByRole('tabpanel');
    this.passwordSecurityPolicy = new PasswordSecurityPolicyForm(this.tabPanel);
    this.snackbar = new Snackbar(page);
  }

  tab(name: AuthenticationTab): Locator {
    return this.tabList.getByRole('tab', { name });
  }

  async openTab(name: AuthenticationTab): Promise<void> {
    await this.tab(name).click();
  }

  async expectTabToBeSelected(name: AuthenticationTab): Promise<void> {
    await expect(this.tab(name)).toHaveAttribute('aria-selected', 'true');
  }

  async expectPasswordSecurityPolicySaved(): Promise<void> {
    await this.snackbar.expectMessage(labels.passwordSecurityPolicySaved);
  }
}
