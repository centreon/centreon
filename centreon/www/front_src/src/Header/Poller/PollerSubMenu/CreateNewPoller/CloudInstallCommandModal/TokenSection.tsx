import { SingleConnectedAutocompleteField } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { getTokensEndpoint } from '../../../../../AgentConfiguration/api/endpoints';
import { Section } from '../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import { listTokensDecoder } from '../../../../../AuthenticationTokens/api';
import {
  labelSelectPollerToken,
  labelSelectToken
} from '../../../translatedLabels';
import { isGeneratedAtom } from './atoms';
import type { CloudInstallCommandFormValues } from './models';

const TokenSection = (): ReactElement => {
  const { t } = useTranslation();
  const isGenerated = useAtomValue(isGeneratedAtom);
  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<CloudInstallCommandFormValues>();

  return (
    <Section order={3} title={t(labelSelectPollerToken)}>
      <div className="my-2">
        <SingleConnectedAutocompleteField
          decoder={listTokensDecoder}
          disabled={isGenerated}
          field="token_name"
          getEndpoint={getTokensEndpoint}
          label={t(labelSelectToken)}
          onChange={(_, value) => {
            setFieldTouched('token', true, false);
            setFieldValue(
              'token',
              value ? { id: value.id, name: value.name } : null
            );
          }}
          required
          value={values.token}
        />
      </div>
    </Section>
  );
};

export default TokenSection;
