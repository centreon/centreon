import { Box, Checkbox, FormControlLabel } from '@mui/material';

import {
  MultiConnectedAutocompleteField,
  NumberField,
  SelectEntry,
  TextField
} from '@centreon/ui';

import { FormikErrors, FormikTouched, useFormikContext } from 'formik';
import { equals, propEq, reject } from 'ramda';
import { ChangeEvent, SyntheticEvent, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { listTokensDecoder } from '../../api/decoders';
import { getTokensEndpoint } from '../../api/endpoints';
import {
  AgentConfigurationForm,
  CMAConfiguration,
  ConnectionMode,
  Token
} from '../../models';
import {
  labelCaCertificate,
  labelCMAauthenticationToken,
  labelCreateHostAutomatically,
  labelOTLPReceiver,
  labelPort,
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

  const configuration = values.configuration as CMAConfiguration;

  const change =
    (property: string) => (event: ChangeEvent<HTMLInputElement>) => {
      setFieldTouched(property, true, false);
      setFieldValue(property, event.target.value);
    };

  const changePort = useCallback((newValue: number) => {
    setFieldTouched('configuration.port', true, false);
    setFieldValue('configuration.port', newValue);
  }, []);

  const changeCMATokens = (
    _: React.SyntheticEvent,
    tokens: Array<SelectEntry>
  ): void => {
    setFieldTouched(tokensProperty, true, false);
    setFieldValue(tokensProperty, tokens);
  };

  const deleteToken = (_: SyntheticEvent, option: Token): void => {
    const tokens = configuration?.tokens || ([] as Array<Token>);

    const newTokens = reject(propEq(option.id, 'id'), tokens);

    setFieldValue(tokensProperty, newTokens);
  };

  const changeCreateHost = (
    _: React.SyntheticEvent,
    checked: boolean
  ): void => {
    setFieldTouched('configuration.createHostAuto', true, false);
    setFieldValue('configuration.createHostAuto', checked);
  };

  const isTLSModes = useMemo(
    () =>
      equals(values.connectionMode?.id, ConnectionMode.secure) ||
      equals(values.connectionMode?.id, ConnectionMode.insecure),
    [values.connectionMode?.id]
  );

  const configurationTouched = touched?.configuration as
    | FormikTouched<CMAConfiguration>
    | undefined;
  const configurationErrors = errors?.configuration as
    | FormikErrors<CMAConfiguration>
    | undefined;

  return (
    <Box className="flex flex-col">
      <Box className="mb-2">
        <FormControlLabel
          control={
            <Checkbox
              checked={configuration.createHostAuto}
              data-testid={labelCreateHostAutomatically}
              onChange={
                changeCreateHost as unknown as React.ChangeEventHandler<HTMLInputElement>
              }
            />
          }
          label={t(labelCreateHostAutomatically)}
        />
      </Box>
      <Box className="mb-4">
        <Title label={labelOTLPReceiver} />

        <Box className="grid grid-cols-2 gap-4">
          <NumberField
            className={classes.input}
            dataTestId={labelPort}
            error={
              (configurationTouched?.port && configurationErrors?.port) ||
              undefined
            }
            fullWidth
            label={t(labelPort)}
            onChange={changePort}
            required
            textFieldSlotsAndSlotProps={{
              slotProps: {
                htmlInput: {
                  'data-testid': 'portInput',
                  max: 65535,
                  min: 1
                }
              }
            }}
            value={
              configuration.port !== null && configuration.port !== undefined
                ? String(configuration.port)
                : undefined
            }
          />
          {isTLSModes && (
            <>
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
            </>
          )}
        </Box>
      </Box>
      <Box>
        <Title label={labelCMAauthenticationToken} />
        <MultiConnectedAutocompleteField
          ChipProps={{
            color: 'primary',
            onDelete: deleteToken as React.EventHandler<React.SyntheticEvent>
          }}
          dataTestId={labelSelectExistingCMATokens}
          decoder={listTokensDecoder}
          disableClearable={false}
          error={
            configurationTouched?.tokens &&
            typeof configurationErrors?.tokens === 'string'
              ? configurationErrors.tokens
              : undefined
          }
          field="token_name"
          getEndpoint={
            getTokensEndpoint as unknown as (params: unknown) => string
          }
          label={t(labelSelectExistingCMATokens)}
          limitTags={15}
          onChange={
            changeCMATokens as unknown as Parameters<
              typeof MultiConnectedAutocompleteField
            >[0]['onChange']
          }
          required
          value={
            (configuration.tokens as unknown as Array<SelectEntry> | null) ||
            null
          }
        />
        <RedirectToTokensPage />
      </Box>
    </Box>
  );
};

export default AgentInitiated;
