import { Box } from '@mui/material';

import {
  NumberField,
  SingleConnectedAutocompleteField,
  TextField
} from '@centreon/ui';

import { or } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { listTokensDecoder } from '../../../api/decoders';
import { getHostsEndpoint, getTokensEndpoint } from '../../../api/endpoints';
import { HostConfiguration as HostConfigurationModel } from '../../../models';
import {
  labelCACommonName,
  labelCaCertificate,
  labelDNSIP,
  labelPort,
  labelSelectExistingCMAToken,
  labelSelectHost
} from '../../../translatedLabels';
import RedirectToTokensPage from '../RedirectToTokensPage';
import { useHostConfigurationsStyle } from './HostConfigurationsStyle';
import { useHostConfiguration } from './useHostConfiguration';

interface Props {
  index: number;
  host: HostConfigurationModel;
}

const HostConfiguration = ({ index, host }: Props): ReactElement => {
  const { classes } = useHostConfigurationsStyle();

  const { t } = useTranslation();
  const {
    selectHost,
    changeAddress,
    hostErrors,
    hostTouched,
    changePort,
    isInsecureMode,
    isSecureMode,
    changeStringInput,
    changeCMAToken,
    token
  } = useHostConfiguration({
    index
  });

  return (
    <Box
      sx={{
        display: 'grid',
        gap: 2,
        gridTemplateColumns: 'repeat(3, 1fr)',
        width: 'calc(100% - 24px)'
      }}
    >
      <SingleConnectedAutocompleteField
        field="name"
        getEndpoint={getHostsEndpoint}
        label={t(labelSelectHost)}
        onChange={selectHost}
        required
        value={{ id: host.id, name: host.name }}
      />
      <TextField
        className={classes.input}
        dataTestId={labelDNSIP}
        error={hostTouched?.address && hostErrors?.address}
        fullWidth
        label={t(labelDNSIP)}
        onChange={changeAddress}
        required
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              'aria-label': labelDNSIP
            }
          }
        }}
        value={host.address}
      />
      <NumberField
        className={classes.input}
        dataTestId={labelPort}
        error={hostTouched?.port && hostErrors?.port}
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
        value={host.port.toString()}
      />

      <Box className="flex flex-col">
        <SingleConnectedAutocompleteField
          dataTestId={labelSelectExistingCMAToken}
          decoder={listTokensDecoder}
          disableClearable={false}
          error={(hostTouched?.token && hostErrors?.token) || undefined}
          field="token_name"
          getEndpoint={getTokensEndpoint}
          label={t(labelSelectExistingCMAToken)}
          onChange={changeCMAToken}
          required
          value={token || null}
        />
        <RedirectToTokensPage />
      </Box>

      {or(isInsecureMode, isSecureMode) && (
        <TextField
          className={classes.input}
          dataTestId={labelCaCertificate}
          error={
            (hostTouched?.pollerCaCertificate &&
              hostErrors?.pollerCaCertificate) ||
            undefined
          }
          fullWidth
          label={t(labelCaCertificate)}
          onChange={changeStringInput('pollerCaCertificate')}
          textFieldSlotsAndSlotProps={{
            slotProps: {
              htmlInput: {
                'aria-label': labelCaCertificate
              }
            }
          }}
          value={host?.pollerCaCertificate || ''}
        />
      )}
      {isInsecureMode && (
        <TextField
          className={classes.input}
          dataTestId={labelCACommonName}
          error={
            (hostTouched?.pollerCaName && hostErrors?.pollerCaName) || undefined
          }
          fullWidth
          label={t(labelCACommonName)}
          onChange={changeStringInput('pollerCaName')}
          textFieldSlotsAndSlotProps={{
            slotProps: {
              htmlInput: {
                'aria-label': labelCACommonName
              }
            }
          }}
          value={host?.pollerCaName || ''}
        />
      )}
    </Box>
  );
};

export default HostConfiguration;
