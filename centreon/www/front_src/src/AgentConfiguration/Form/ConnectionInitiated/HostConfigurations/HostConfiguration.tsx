import {
  NumberField,
  SingleConnectedAutocompleteField,
  TextField
} from '@centreon/ui';
import { Box } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { getHostsEndpoint, getTokensEndpoint } from '../../../api/endpoints';
import { HostConfiguration as HostConfigurationModel } from '../../../models';

import RedirectToTokensPage from '../RedirectToTokensPage';
import { useHostConfiguration } from './useHostConfiguration';

import { useHostConfigurationsStyle } from './HostConfigurationsStyle';

import { listTokensDecoder } from '../../../api/decoders';
import {
  labelCACommonName,
  labelCaCertificate,
  labelDNSIP,
  labelPort,
  labelSelectExistingCMAToken,
  labelSelectHost
} from '../../../translatedLabels';

interface Props {
  index: number;
  host: HostConfigurationModel;
}

const HostConfiguration = ({ index, host }: Props): JSX.Element => {
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
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: 2,
        width: 'calc(100% - 24px)'
      }}
    >
      <SingleConnectedAutocompleteField
        required
        label={t(labelSelectHost)}
        onChange={selectHost}
        getEndpoint={getHostsEndpoint}
        value={{ id: host.id, name: host.name }}
        field="name"
      />
      <TextField
        required
        className={classes.input}
        value={host.address}
        onChange={changeAddress}
        label={t(labelDNSIP)}
        dataTestId={labelDNSIP}
        fullWidth
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              'aria-label': labelDNSIP
            }
          }
        }}
        error={hostTouched?.address && hostErrors?.address}
      />
      <NumberField
        required
        value={host.port.toString()}
        onChange={changePort}
        label={t(labelPort)}
        dataTestId={labelPort}
        fullWidth
        error={hostTouched?.port && hostErrors?.port}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              'data-testid': 'portInput',
              min: 1,
              max: 65535
            }
          }
        }}
        className={classes.input}
      />

      {(isInsecureMode || isSecureMode) && (
        <>
          <Box className="flex flex-col">
            <SingleConnectedAutocompleteField
              required
              disableClearable={false}
              dataTestId={labelSelectExistingCMAToken}
              field="token_name"
              getEndpoint={getTokensEndpoint}
              label={t(labelSelectExistingCMAToken)}
              value={token || null}
              onChange={changeCMAToken}
              decoder={listTokensDecoder}
              error={(hostTouched?.token && hostErrors?.token) || undefined}
            />
            <RedirectToTokensPage />
          </Box>

          <TextField
            value={host?.pollerCaCertificate || ''}
            onChange={changeStringInput('pollerCaCertificate')}
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
              (hostTouched?.pollerCaCertificate &&
                hostErrors?.pollerCaCertificate) ||
              undefined
            }
            className={classes.input}
          />
        </>
      )}

      {isInsecureMode && (
        <TextField
          value={host?.pollerCaName || ''}
          onChange={changeStringInput('pollerCaName')}
          label={t(labelCACommonName)}
          textFieldSlotsAndSlotProps={{
            slotProps: {
              htmlInput: {
                'aria-label': labelCACommonName
              }
            }
          }}
          dataTestId={labelCACommonName}
          fullWidth
          error={
            (hostTouched?.pollerCaName && hostErrors?.pollerCaName) || undefined
          }
          className={classes.input}
        />
      )}
    </Box>
  );
};

export default HostConfiguration;
