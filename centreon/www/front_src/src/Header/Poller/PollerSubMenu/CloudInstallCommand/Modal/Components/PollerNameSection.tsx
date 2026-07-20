import { TextField } from '@mui/material';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { Section } from '../../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import {
  labelEnterPollerNameAndAddress,
  labelPollerAddress,
  labelPollerName
} from '../../../../translatedLabels';
import { isGeneratedAtom } from '../../atoms';
import type { CloudInstallCommandFormValues } from '../../models';

const PollerNameSection = (): ReactElement => {
  const { t } = useTranslation();
  const isGenerated = useAtomValue(isGeneratedAtom);
  const { values, setFieldValue, setFieldTouched, errors, touched } =
    useFormikContext<CloudInstallCommandFormValues>();

  return (
    <Section order={1} title={t(labelEnterPollerNameAndAddress)}>
      <div className="my-2">
        <TextField
          data-testid="cloud-poller-name"
          disabled={isGenerated}
          error={touched.pollerName && Boolean(errors.pollerName)}
          fullWidth
          helperText={touched.pollerName && errors.pollerName}
          label={t(labelPollerName)}
          onChange={(e) => {
            setFieldTouched('pollerName', true, false);
            setFieldValue('pollerName', e.target.value);
          }}
          required
          size="small"
          value={values.pollerName}
        />
      </div>
      <div className="my-2">
        <TextField
          data-testid="cloud-poller-address"
          disabled={isGenerated}
          error={touched.pollerAddress && Boolean(errors.pollerAddress)}
          fullWidth
          helperText={touched.pollerAddress && errors.pollerAddress}
          label={t(labelPollerAddress)}
          onChange={(e) => {
            setFieldTouched('pollerAddress', true, false);
            setFieldValue('pollerAddress', e.target.value);
          }}
          required
          size="small"
          value={values.pollerAddress}
        />
      </div>
    </Section>
  );
};

export default PollerNameSection;
