import React from 'react';

import LicenseMessage from '.';

export default { title: 'License Message' };

export const normal = (): JSX.Element => <LicenseMessage />;

export const withLabel = (): JSX.Element => (
  <LicenseMessage label="This is a license message" />
);
