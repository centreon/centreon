import { interceptAPIRequest, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import { createStore } from 'jotai';

import { Method } from '..';
import Module from '.';
import LicensedModule from './LicensedModule';

const initializeModule = (): void => {
  render(
    <Module seedName="seed" store={createStore()}>
      <p>Module</p>
    </Module>
  );
};

const initializeModuleWithValidLicense = (
  isFederatedComponent = false
): void => {
  interceptAPIRequest({
    alias: 'getValidLicense',
    method: Method.GET,
    path: './api/internal.php?object=centreon_license_manager&action=licenseValid&productName=valid',
    response: {
      success: true
    }
  });

  render(
    <div style={{ height: '100vh' }}>
      <LicensedModule
        isFederatedComponent={isFederatedComponent}
        moduleName="valid"
        seedName="seed"
        store={createStore()}
      >
        <p>Module</p>
      </LicensedModule>
    </div>
  );
};

const initializeModuleWithInvalidLicense = (
  isFederatedComponent = false
): void => {
  interceptAPIRequest({
    alias: 'getInvalidLicense',
    method: Method.GET,
    path: './api/internal.php?object=centreon_license_manager&action=licenseValid&productName=invalid',
    response: {
      success: false
    }
  });

  render(
    <div style={{ height: '100vh' }}>
      <LicensedModule
        isFederatedComponent={isFederatedComponent}
        moduleName="invalid"
        seedName="seed"
        store={createStore()}
      >
        <p>Module</p>
      </LicensedModule>
    </div>
  );
};

describe('Module', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });
  beforeEach(() => {
    initializeModule();
  });

  it('displays the content of the module', async () => {
    expect(await screen.findByText('Module')).toBeVisible();
  });
});

describe('Valid license module', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the content of the page when the license is valid license', async () => {
    initializeModuleWithValidLicense();
    const { request } = await waitForRequest('@getValidLicense');

    expect(await screen.findByText('Module')).toBeVisible();

    // The license is checked for the module given.
    expect(request.url.searchParams.get('productName')).toBe('valid');
  });

  it('displays the content of the component when the license is valid license', async () => {
    initializeModuleWithValidLicense(true);

    expect(await screen.findByText('Module')).toBeVisible();
  });
});

describe('Invalid license module', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the content of the page when the license is invalid license', async () => {
    initializeModuleWithInvalidLicense();
    await waitForRequest('@getInvalidLicense');

    expect(await screen.findByText('Oops !')).toBeVisible();
    expect(screen.queryByText('Module')).not.toBeInTheDocument();

    expect(screen.getByText('License invalid or expired !')).toBeVisible();
    expect(
      screen.getByText('Please contact your administrator.')
    ).toBeVisible();
    // The images are shown once loaded.
    await waitFor(() => {
      expect(
        document.querySelector('img[alt="License invalid or expired !"]')
      ).toBeVisible();
    });
    await waitFor(() => {
      expect(document.querySelector('img[alt="Centreon Logo"]')).toBeVisible();
    });
  });

  it('displays the content of the module when the license is invalid license', async () => {
    initializeModuleWithInvalidLicense(true);
    // No request to wait for: the previous test left the license of this
    // module in the shared query cache, as it did with Cypress. The checks
    // are made once it is known, when no skeleton is left.
    // The snapshot: an empty page, neither the fallback page nor a skeleton.
    await waitFor(() => {
      expect(
        document.querySelector('.MuiSkeleton-root')
      ).not.toBeInTheDocument();
    });
    expect(screen.queryByText('Module')).not.toBeInTheDocument();
    expect(screen.queryByText('Oops !')).not.toBeInTheDocument();
  });
});
