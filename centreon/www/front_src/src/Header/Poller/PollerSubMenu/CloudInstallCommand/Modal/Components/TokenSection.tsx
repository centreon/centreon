import { SingleConnectedAutocompleteField } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { Section } from '../../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import { listTokensDecoder } from '../../../../../../AuthenticationTokens/api';
import { getTokensEndpoint } from '../../../../../api/endpoints';
import {
  labelSelectToken,
  labelSelectTokenPlaceholder
} from '../../../../translatedLabels';
import { isGeneratedAtom } from '../../atoms';
import type { CloudInstallCommandFormValues } from '../../models';

const TokenSection = (): ReactElement => {
  const { t } = useTranslation();
  const isGenerated = useAtomValue(isGeneratedAtom);
  const { setFieldValue, setFieldTouched } =
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
            const token = value as { id: string; name: string } | null;
            setFieldTouched('token', true, false);
            setFieldValue(
              'token',
              token ? { id: token.id, name: token.name } : null
            );
          }}
        />
      </div>
    </Section>
  );
};

export default TokenSection;
