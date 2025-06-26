import { SingleConnectedAutocompleteField } from '@centreon/ui';
import { useTranslation } from 'react-i18next';

import { listTokensDecoder } from '../../api/decoders';
import { getTokensEndpoint } from '../../api/endpoints';

import { labelSelectExistingCMAToken } from '../../translatedLabels';

const CMATokens = ({ value, changeCMAToken }): JSX.Element => {
  const { t } = useTranslation();

  return (
    <SingleConnectedAutocompleteField
      disableClearable={false}
      dataTestId={labelSelectExistingCMAToken}
      field="token_name"
      getEndpoint={getTokensEndpoint}
      label={t(labelSelectExistingCMAToken)}
      value={value || null}
      onChange={changeCMAToken}
      decoder={listTokensDecoder}
      required
    />
  );
};

export default CMATokens;
