import { useTranslation } from 'react-i18next';

import { MenuSkeleton, PageSkeleton, useFetchQuery } from '../../..';
import { FallbackPage } from '../../../FallbackPage/FallbackPage';
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
  isFederatedComponent?: boolean;
  moduleName: string;
}

interface ContentProps {
  children: React.ReactElement;
  isFederatedComponent?: boolean;
  isValid: boolean;
}

const Content = ({
  children,
  isValid,
  isFederatedComponent
}: ContentProps): JSX.Element | null => {
  const { t } = useTranslation();

  if (isFederatedComponent && !isValid) {
    return null;
  }

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
  isFederatedComponent,
  children,
  moduleName
}: LicenseCheckProps): JSX.Element | null => {
  const { isError, data, isLoading } = useFetchQuery<License>({
    decoder: licenseDecoder,
    getEndpoint: () => getModuleLicenseCheckEndpoint(moduleName),
    getQueryKey: () => ['license', moduleName]
  });

  if (isError) {
    return null;
  }

  const isValid = data?.success;

  const skeleton = isFederatedComponent ? <MenuSkeleton /> : <PageSkeleton />;

  return isLoading ? (
    skeleton
  ) : (
    <Content
      isFederatedComponent={isFederatedComponent}
      isValid={isValid as boolean}
    >
      {children}
    </Content>
  );
};

export default LicenseCheck;
