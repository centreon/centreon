import { useCopyToClipboard } from '@centreon/ui';
import { useTranslation } from 'react-i18next';
import { useGetToken } from '../../../api';
import { getTokenEndpoint } from '../../../api/endpoints';

import {
  labelTokenCopiedToTheClipboard,
  labelTokenCouldNotBeCopied
} from '../../../translatedLabels';

const useCopyToken = ({ tokenName, userId }) => {
  const { t } = useTranslation();

  const { copy } = useCopyToClipboard({
    errorMessage: t(labelTokenCouldNotBeCopied),
    successMessage: t(labelTokenCopiedToTheClipboard)
  });

  const { isLoading, getDetails } = useGetToken({
    endpoint: getTokenEndpoint({ tokenName, userId }),
    queryKey: ['getToken', tokenName]
  });

  const copyToken = async () => {
    const data = await getDetails();

    copy(data?.token);
  };

  return { copyToken, isLoading };
};

export default useCopyToken;
