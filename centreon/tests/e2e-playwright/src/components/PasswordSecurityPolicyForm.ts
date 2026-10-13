import { expect, type Locator } from '@playwright/test';

/** Labels come from www/front_src/src/Authentication/Local/translatedLabels.ts. */
const labels = {
  blockingDuration: 'Time that must pass before new connection is allowed',
  canReuseLastPasswords: 'Last 3 passwords can be reused',
  excludedUsers: 'Excluded users',
  minimumLength: 'Minimum password length',
  minimumTimeBetweenChanges: 'Minimum time between password changes',
  numberOfAttempts: 'Number of attempts before user is blocked',
  passwordExpiresAfter: 'Password expires after',
  passwordMustContain: 'Password must contain',
  reset: 'Reset',
  // aria-label of the save button, its text being labelSave.
  save: 'save button'
} as const;

export type PasswordCase =
  | 'lower case'
  | 'numbers'
  | 'special characters'
  | 'upper case';

export interface PasswordSecurityPolicyValues {
  blockingDuration: { days: string; hours: string; minutes: string };
  excludedUsers: Array<string>;
  expiration: { days: string; months: string };
  minimumLength: string;
  minimumTimeBetweenChanges: { days: string; hours: string };
  numberOfAttempts: string;
  requiredCases: Array<PasswordCase>;
}

/**
 * Component object for the "Password security policy" form. Duration inputs
 * are comboboxes named after their field plus their unit.
 */
export class PasswordSecurityPolicyForm {
  readonly minimumLength: Locator;
  readonly expirationMonths: Locator;
  readonly expirationDays: Locator;
  readonly excludedUsers: Locator;
  readonly minimumTimeBetweenChangesDays: Locator;
  readonly minimumTimeBetweenChangesHours: Locator;
  readonly canReuseLastPasswords: Locator;
  readonly numberOfAttempts: Locator;
  readonly blockingDurationDays: Locator;
  readonly blockingDurationHours: Locator;
  readonly blockingDurationMinutes: Locator;
  readonly resetButton: Locator;
  readonly saveButton: Locator;

  constructor(private readonly root: Locator) {
    const combobox = (name: string): Locator =>
      root.getByRole('combobox', { exact: true, name });

    this.minimumLength = root.getByRole('spinbutton', {
      name: labels.minimumLength
    });
    this.expirationMonths = combobox(`${labels.passwordExpiresAfter} Months`);
    this.expirationDays = combobox(`${labels.passwordExpiresAfter} Days`);
    this.excludedUsers = combobox(labels.excludedUsers);
    this.minimumTimeBetweenChangesDays = combobox(
      `${labels.minimumTimeBetweenChanges} Day`
    );
    this.minimumTimeBetweenChangesHours = combobox(
      `${labels.minimumTimeBetweenChanges} Hour`
    );
    this.canReuseLastPasswords = root.getByRole('checkbox', {
      name: labels.canReuseLastPasswords
    });
    this.numberOfAttempts = root.getByRole('spinbutton', {
      name: labels.numberOfAttempts
    });
    this.blockingDurationDays = combobox(`${labels.blockingDuration} Day`);
    this.blockingDurationHours = combobox(`${labels.blockingDuration} Hour`);
    this.blockingDurationMinutes = combobox(
      `${labels.blockingDuration} Minutes`
    );
    this.resetButton = root.getByRole('button', { name: labels.reset });
    this.saveButton = root.getByRole('button', { name: labels.save });
  }

  caseToggle(passwordCase: PasswordCase): Locator {
    return this.root.getByRole('button', {
      name: `${labels.passwordMustContain} ${passwordCase}`
    });
  }

  /** Chip of an excluded user, rendered as a button named after its alias. */
  excludedUser(alias: string): Locator {
    return this.root.getByRole('button', { exact: true, name: alias });
  }

  async setNumberOfAttempts(attempts: number): Promise<void> {
    await this.numberOfAttempts.fill(String(attempts));
  }

  async save(): Promise<void> {
    await this.saveButton.click();
  }

  async expectValues(values: PasswordSecurityPolicyValues): Promise<void> {
    await expect(this.minimumLength).toHaveValue(values.minimumLength);
    for (const passwordCase of values.requiredCases) {
      // Enabled cases are rendered as "contained" MUI buttons.
      await expect(this.caseToggle(passwordCase)).toHaveClass(
        /MuiButton-contained/
      );
    }
    await expect(this.expirationMonths).toHaveText(values.expiration.months);
    await expect(this.expirationDays).toHaveText(values.expiration.days);
    for (const alias of values.excludedUsers) {
      await expect(this.excludedUser(alias)).toBeVisible();
    }
    await expect(this.minimumTimeBetweenChangesDays).toHaveText(
      values.minimumTimeBetweenChanges.days
    );
    await expect(this.minimumTimeBetweenChangesHours).toHaveText(
      values.minimumTimeBetweenChanges.hours
    );
    await expect(this.numberOfAttempts).toHaveValue(values.numberOfAttempts);
    await expect(this.blockingDurationDays).toHaveText(
      values.blockingDuration.days
    );
    await expect(this.blockingDurationHours).toHaveText(
      values.blockingDuration.hours
    );
    await expect(this.blockingDurationMinutes).toHaveText(
      values.blockingDuration.minutes
    );
  }
}
