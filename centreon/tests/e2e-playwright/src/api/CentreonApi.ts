import { type APIRequestContext, expect, request } from '@playwright/test';

import { baseUrl, centreonBasePath } from '../config/environment';
import { type Credentials, users } from '../config/users';

export interface Contact extends Credentials {
  admin?: boolean;
  email?: string;
  name?: string;
}

/** Shape of /administration/authentication/providers/local. */
export interface PasswordSecurityPolicy {
  attempts: number | null;
  blocking_duration: number | null;
  can_reuse_passwords: boolean;
  delay_before_new_password: number | null;
  has_lowercase: boolean;
  has_number: boolean;
  has_special_character: boolean;
  has_uppercase: boolean;
  password_expiration: {
    excluded_users: Array<string>;
    expiration_delay: number | null;
  };
  password_min_length: number;
}

export interface TokenPayload {
  expirationInDays: number;
  name: string;
  type?: 'api' | 'cma';
  userId: number;
}

const localProviderPath =
  'api/latest/administration/authentication/providers/local';

/**
 * Seeds and cleans data through the APIs (v1 CLAPI + v2 REST), so specs only
 * drive the UI for the behaviour under test.
 */
export class CentreonApi {
  private constructor(
    private readonly context: APIRequestContext,
    private readonly clapiToken: string
  ) {}

  static async create(
    credentials: Credentials = users.admin
  ): Promise<CentreonApi> {
    const context = await request.newContext({
      baseURL: `${baseUrl}${centreonBasePath}/`
    });
    const body = { login: credentials.alias, password: credentials.password };

    // API v2 session cookie, kept by the context for the REST endpoints.
    const login = await context.post(
      'authentication/providers/configurations/local',
      { data: body }
    );
    await expect(login, 'API v2 login').toBeOK();

    const v1 = await context.post<{ authToken: string }>(
      'api/index.php?action=authenticate',
      {
        form: { password: credentials.password, username: credentials.alias }
      }
    );
    await expect(v1, 'API v1 login').toBeOK();
    const { authToken } = await v1.json();

    return new CentreonApi(context, authToken);
  }

  async dispose(): Promise<void> {
    await this.context.dispose();
  }

  async clapi<T = unknown>(
    action: string,
    object: string,
    values = ''
  ): Promise<T> {
    const response = await this.context.post<{ result: T }>(
      'api/index.php?action=action&object=centreon_clapi',
      {
        data: { action, object, values },
        headers: { 'centreon-auth-token': this.clapiToken }
      }
    );
    await expect(response, `CLAPI ${action} ${object} ${values}`).toBeOK();

    return (await response.json()).result;
  }

  /** Adds a local contact with GUI access and returns its id. */
  async addContact({
    admin = true,
    alias,
    email,
    name,
    password
  }: Contact): Promise<number> {
    await this.clapi(
      'ADD',
      'CONTACT',
      [
        name ?? alias,
        alias,
        email ?? `${alias}@example.com`,
        password,
        admin ? 1 : 0,
        1,
        'en_US',
        'local'
      ].join(';')
    );

    return this.getContactId(alias);
  }

  async getContactId(alias: string): Promise<number> {
    const contacts = await this.clapi<Array<{ alias: string; id: string }>>(
      'SHOW',
      'CONTACT',
      alias
    );
    const contact = contacts.find((candidate) => candidate.alias === alias);

    if (!contact) {
      throw new Error(`Contact ${alias} not found`);
    }

    return Number(contact.id);
  }

  async deleteContact(alias: string): Promise<void> {
    await this.clapi('DEL', 'CONTACT', alias);
  }

  async getPasswordSecurityPolicy(): Promise<PasswordSecurityPolicy> {
    const response = await this.context.get<{
      password_security_policy: PasswordSecurityPolicy;
    }>(localProviderPath);
    await expect(response).toBeOK();

    return (await response.json()).password_security_policy;
  }

  async setPasswordSecurityPolicy(
    policy: PasswordSecurityPolicy
  ): Promise<void> {
    const response = await this.context.put(localProviderPath, {
      data: { password_security_policy: policy }
    });
    await expect(response, 'update the password security policy').toBeOK();
  }

  async createToken({
    expirationInDays,
    name,
    type = 'api',
    userId
  }: TokenPayload): Promise<void> {
    const expiration = new Date();
    expiration.setDate(expiration.getDate() + expirationInDays);

    const response = await this.context.post(
      'api/latest/administration/tokens',
      {
        data: {
          // The API rejects milliseconds.
          expiration_date: `${expiration.toISOString().split('.')[0]}Z`,
          name,
          type,
          user_id: userId
        }
      }
    );
    await expect(response, `create token ${name}`).toBeOK();
  }

  /** Cleanup helper: a token already deleted by the test is not an error. */
  async deleteToken(name: string, userId: number): Promise<void> {
    await this.context.delete(
      `api/latest/administration/tokens/${encodeURIComponent(name)}/users/${userId}`
    );
  }
}
