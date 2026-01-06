import { equals, propEq, reject } from 'ramda';
import { ChangeEvent, useCallback, useMemo } from 'react';

import {
  MultiConnectedAutocompleteField,
  NumberField,
  TextField
} from '@centreon/ui';
import { Box } from '@mui/material';
import { useTranslation } from 'react-i18next';

import { useFormikContext } from 'formik';
import { listTokensDecoder } from '../../api/decoders';
import { getTokensEndpoint } from '../../api/endpoints';
import { AgentConfigurationForm, ConnectionMode } from '../../models';

import { useAgentInitiatedStyles } from './ConnectionInitiated.styles';
import RedirectToTokensPage from './RedirectToTokensPage';

import Title from './Title';

import {
  labelCMAauthenticationToken,
  labelCaCertificate,
  labelOTLPReceiver,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate,
  labelSelectExistingCMATokens
} from '../../translatedLabels';

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

  const changePort = useCallback((newValue: number) => {
    setFieldTouched('configuration.port', true, false);
    setFieldValue('configuration.port', newValue);
  }, []);

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
      <Box>
        <Title label={labelOTLPReceiver} />

        <Box className="grid grid-cols-2 gap-4">
          <NumberField
            className={classes.input}
            required
            value={values.configuration.port}
            onChange={changePort}
            label={t(labelPort)}
            dataTestId={labelPort}
            fullWidth
            textFieldSlotsAndSlotProps={{
              slotProps: {
                htmlInput: {
                  'data-testid': 'portInput',
                  min: 1,
                  max: 65535
                }
              }
            }}
            error={
              (touched?.configuration?.port && errors?.configuration?.port) ||
              undefined
            }
          />
          {isTLSModes && (
            <>
              <TextField
                className={classes.input}
                value={values.configuration.otelPublicCertificate || ''}
                onChange={change(publicCertificateProperty)}
                label={t(labelPublicCertificate)}
                dataTestId={labelPublicCertificate}
                fullWidth
                textFieldSlotsAndSlotProps={{
                  slotProps: {
                    htmlInput: {
                      'aria-label': labelPublicCertificate
                    }
                  }
                }}
                error={
                  (touched?.configuration?.otelPublicCertificate &&
                    errors?.configuration?.otelPublicCertificate) ||
                  undefined
                }
              />

              <TextField
                value={values.configuration.otelCaCertificate || ''}
                onChange={change(caCertificateProperty)}
                label={t(labelCaCertificate)}
                dataTestId={labelCaCertificate}
                textFieldSlotsAndSlotProps={{
                  slotProps: {
                    htmlInput: {
                      'aria-label': labelCaCertificate
                    }
                  }
                }}
                fullWidth
                error={
                  (touched?.configuration?.otelCaCertificate &&
                    errors?.configuration?.otelCaCertificate) ||
                  undefined
                }
                className={classes.input}
              />

              <TextField
                value={values.configuration.otelPrivateKey || ''}
                onChange={change(privateKeyProperty)}
                label={t(labelPrivateKey)}
                textFieldSlotsAndSlotProps={{
                  slotProps: {
                    htmlInput: {
                      'aria-label': labelPrivateKey
                    }
                  }
                }}
                dataTestId={labelPrivateKey}
                fullWidth
                error={
                  (touched?.configuration?.otelPrivateKey &&
                    errors?.configuration?.otelPrivateKey) ||
                  undefined
                }
                className={classes.input}
              />
            </>
          )}
        </Box>
      </Box>
      <Box>
        <Title label={labelCMAauthenticationToken} />
        <MultiConnectedAutocompleteField
          required
          disableClearable={false}
          dataTestId={labelSelectExistingCMATokens}
          field="token_name"
          getEndpoint={getTokensEndpoint}
          label={t(labelSelectExistingCMATokens)}
          value={values.configuration.tokens || null}
          onChange={changeCMATokens}
          decoder={listTokensDecoder}
          limitTags={15}
          chipProps={{
            color: 'primary',
            onDelete: deleteToken
          }}
          error={
            (touched?.configuration?.tokens && errors?.configuration?.tokens) ||
            undefined
          }
        />
        <RedirectToTokensPage />
      </Box>
    </Box>
  );
};

export default AgentInitiated;
