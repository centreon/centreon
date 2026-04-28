import { MultiConnectedAutocompleteField } from '@centreon/ui';

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
  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<CloudInstallCommandFormValues>();

  return (
    <Section order={3} title={t(labelSelectToken)}>
      <div className="my-2">
        <MultiConnectedAutocompleteField
          chipProps={{
            color: 'primary',
            onDelete: (
              _: React.SyntheticEvent,
              option: { id: string; name: string }
            ): void => {
              const updated = values.token.filter((t) => t.id !== option.id);
              setFieldValue('token', updated);
            }
          }}
          decoder={listTokensDecoder}
          disabled={isGenerated}
          field="token_name"
          getEndpoint={getTokensEndpoint}
          label={t(labelSelectTokenPlaceholder)}
          onChange={(
            _: React.SyntheticEvent,
            updatedValues: Array<{ id: string; name: string }>
          ) => {
            setFieldTouched('token', true, false);
            setFieldValue(
              'token',
              updatedValues.map((v) => ({ id: v.id, name: v.name }))
            );
          }}
          value={values.token}
        />
      </div>
    </Section>
  );
};

export default TokenSection;
