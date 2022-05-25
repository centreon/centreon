import * as React from 'react';

import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Alert, AlertTitle, Container } from '@mui/material';

import { getData, PageSkeleton, useRequest } from '../../..';
import { getModuleLicenseCheckEndpoint } from '../api';

import { licenseDecoder } from './decoder';
import { License } from './models';
import {
  labelContactYourAdministrator,
  labelInvalidLicense,
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
    <Container maxWidth="sm">
      <Alert severity="error">
        <AlertTitle>{t(labelInvalidLicense)}</AlertTitle>
        {t(labelContactYourAdministrator)}
      </Alert>
    </Container>
  );
};

const LicenseCheck = ({
  children,
  moduleName,
}: LicenseCheckProps): JSX.Element => {
  const [isValid, setIsValid] = React.useState<boolean | null>(null);

  const { sendRequest } = useRequest<License>({
    decoder: licenseDecoder,
    request: getData,
  });

  const endpoint = getModuleLicenseCheckEndpoint(moduleName);

  const checkLicense = (): void => {
    sendRequest({
      endpoint,
    }).then(({ success }) => {
      setIsValid(success);
    });
  };

  React.useEffect(() => {
    checkLicense();
  }, []);

  return isNil(isValid) ? (
    <PageSkeleton />
  ) : (
    <Content isValid={isValid as boolean}>{children}</Content>
  );
};

export default LicenseCheck;
