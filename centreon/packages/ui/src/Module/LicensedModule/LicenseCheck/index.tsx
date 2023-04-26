import * as React from 'react';

import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { FallbackPage } from '../../../FallbackPage/FallbackPage';
import { PageSkeleton, useFetchQuery } from '../../..';
import { getModuleLicenseCheckEndpoint } from '../api';

import { licenseDecoder } from './decoder';
import { License } from './models';
import {
  labelContactYourAdministrator,
  labelInvalidLicense,
  labelOops
} from './translatedLabels';

export interface LicenseCheckProps {
  children: React.ReactElement;
  moduleName: string;
}

interface ContentProps {
  children: React.ReactElement;
  isValid: boolean;
}

const Content = ({ children, isValid }: ContentProps): JSX.Element => {
  const { t } = useTranslation();

  return isValid ? (
    children
  ) : (
    <FallbackPage
      contactAdmin={t(labelContactYourAdministrator) || ''}
      message={t(labelInvalidLicense) || ''}
      title={t(labelOops) || ''}
    />
  );
};

const LicenseCheck = ({
  children,
  moduleName
}: LicenseCheckProps): JSX.Element | null => {
  const { isError, data } = useFetchQuery<License>({
    decoder: licenseDecoder,
    getEndpoint: () => getModuleLicenseCheckEndpoint(moduleName),
    getQueryKey: () => ['license', moduleName]
  });

  if (isError) {
    return null;
  }

  const isValid = data?.success;

  return isNil(isValid) ? (
    <PageSkeleton />
  ) : (
    <Content isValid={isValid as boolean}>{children}</Content>
  );
};

export default LicenseCheck;
