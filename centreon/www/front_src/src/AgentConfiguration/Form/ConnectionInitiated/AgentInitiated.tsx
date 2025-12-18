import { Box } from '@mui/material';

import { MultiConnectedAutocompleteField, TextField } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { equals, propEq, reject } from 'ramda';
import { ChangeEvent, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { listTokensDecoder } from '../../api/decoders';
import { getTokensEndpoint } from '../../api/endpoints';
import { AgentConfigurationForm, ConnectionMode } from '../../models';
import {
  labelCaCertificate,
  labelCMAauthenticationToken,
  labelOTLPReceiver,
  labelPrivateKey,
  labelPublicCertificate,
  labelSelectExistingCMATokens
} from '../../translatedLabels';
import { useAgentInitiatedStyles } from './ConnectionInitiated.styles';
import RedirectToTokensPage from './RedirectToTokensPage';
import Title from './Title';

const publicCertificateProperty = 'configuration.otelPublicCertificate';
const caCertificateProperty = 'configuration.otelCaCertificate';
const privateKeyProperty = 'configuration.otelPrivateKey';
const tokensProperty = 'configuration.tokens';

const AgentInitiated = (): React.ReactElement => {
  const { t } = useTranslation();
  const { classes } = useAgentInitiatedStyles();

  const { setFieldValue, setFieldTouched, errors, touched, values } =
    useFormikContext<AgentConfigurationForm>();

  const change = (property) => (event: ChangeEvent<HTMLInputElement>) => {
    setFieldTouched(property, true, false);
    setFieldValue(property, event.target.value);
  };

  const changeCMATokens = (_, tokens) => {
    setFieldTouched(tokensProperty, true, false);
    setFieldValue(tokensProperty, tokens);
  };

  const deleteToken = (_, option): void => {
    const newTokens = reject(
      propEq(option.id, 'id'),
      values.configuration.tokens
    );

    setFieldValue(tokensProperty, newTokens);
  };

  const isTLSModes = useMemo(
    () =>
      equals(values.connectionMode?.id, ConnectionMode.secure) ||
      equals(values.connectionMode?.id, ConnectionMode.insecure),
    [values.connectionMode?.id]
  );

  return (
    <Box className={classes.container}>
      {isTLSModes && (
        <Box>
          <Title label={labelOTLPReceiver} />
          <Box className={classes.inputs}>
            <TextField
              className={classes.input}
              dataTestId={labelPublicCertificate}
              error={
                (touched?.configuration?.otelPublicCertificate &&
                  errors?.configuration?.otelPublicCertificate) ||
                undefined
              }
              fullWidth
              label={t(labelPublicCertificate)}
              onChange={change(publicCertificateProperty)}
              textFieldSlotsAndSlotProps={{
                slotProps: {
                  htmlInput: {
                    'aria-label': labelPublicCertificate
                  }
                }
              }}
              value={values.configuration.otelPublicCertificate || ''}
            />

            <TextField
              className={classes.input}
              dataTestId={labelCaCertificate}
              error={
                (touched?.configuration?.otelCaCertificate &&
                  errors?.configuration?.otelCaCertificate) ||
                undefined
              }
              fullWidth
              label={t(labelCaCertificate)}
              onChange={change(caCertificateProperty)}
              textFieldSlotsAndSlotProps={{
                slotProps: {
                  htmlInput: {
                    'aria-label': labelCaCertificate
                  }
                }
              }}
              value={values.configuration.otelCaCertificate || ''}
            />

            <TextField
              className={classes.input}
              dataTestId={labelPrivateKey}
              error={
                (touched?.configuration?.otelPrivateKey &&
                  errors?.configuration?.otelPrivateKey) ||
                undefined
              }
              fullWidth
              label={t(labelPrivateKey)}
              onChange={change(privateKeyProperty)}
              textFieldSlotsAndSlotProps={{
                slotProps: {
                  htmlInput: {
                    'aria-label': labelPrivateKey
                  }
                }
              }}
              value={values.configuration.otelPrivateKey || ''}
            />
          </Box>
        </Box>
      )}
      <Box>
        <Title label={labelCMAauthenticationToken} />
        <MultiConnectedAutocompleteField
          chipProps={{
            color: 'primary',
            onDelete: deleteToken
          }}
          dataTestId={labelSelectExistingCMATokens}
          decoder={listTokensDecoder}
          disableClearable={false}
          error={
            (touched?.configuration?.tokens && errors?.configuration?.tokens) ||
            undefined
          }
          field="token_name"
          getEndpoint={getTokensEndpoint}
          label={t(labelSelectExistingCMATokens)}
          limitTags={15}
          onChange={changeCMATokens}
          required
          value={values.configuration.tokens || null}
        />
        <RedirectToTokensPage />
      </Box>
    </Box>
  );
};

export default AgentInitiated;
