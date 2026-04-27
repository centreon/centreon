import { SingleConnectedAutocompleteField } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { Section } from '../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import { getTokensEndpoint } from '../../../../../AgentConfiguration/api/endpoints';
import { listTokensDecoder } from '../../../../../AuthenticationTokens/api';

import { isGeneratedAtom } from '../atoms';
import type { CloudInstallCommandFormValues } from '../models';

import {
  labelSelectToken,
  labelSelectTokenPlaceholder
} from '../../../translatedLabels';

const TokenSection = (): ReactElement => {
  const { t } = useTranslation();
  const isGenerated = useAtomValue(isGeneratedAtom);
  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<CloudInstallCommandFormValues>();

  return (
    <Section order={3} title={t(labelSelectToken)}>
      <div className="my-2">
        <SingleConnectedAutocompleteField
          decoder={listTokensDecoder}
          disabled={isGenerated}
          field="token_name"
          getEndpoint={getTokensEndpoint}
          label={t(labelSelectTokenPlaceholder)}
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
