import {
  NumberField,
  SingleConnectedAutocompleteField,
  TextField,
  buildListingEndpoint
} from '@centreon/ui';
import { Box } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { hostsConfigurationEndpoint } from '../../api/endpoints';
import { HostConfiguration as HostConfigurationModel } from '../../models';
import {
  labelCACommonName,
  labelCaCertificate,
  labelDNSIP,
  labelPort,
  labelSelectHost
} from '../../translatedLabels';
import { useHostConfiguration } from './useHostConfiguration';

interface Props {
  index: number;
  host: HostConfigurationModel;
}

const HostConfiguration = ({ index, host }: Props): JSX.Element => {
  const { t } = useTranslation();
  const {
    selectHost,
    changeAddress,
    hostErrors,
    hostTouched,
    changePort,
    areCertificateFieldsVisible,
    changeStringInput
  } = useHostConfiguration({
    index
  });

  return (
    <Box
      sx={{
        display: 'grid',
        gridTemplateColumns: 'repeat(2, 1fr)',
        gap: 2,
        width: 'calc(100% - 24px)'
      }}
    >
      <SingleConnectedAutocompleteField
        required
        label={t(labelSelectHost)}
        onChange={selectHost}
        getEndpoint={(parameters) =>
          buildListingEndpoint({
            baseEndpoint: hostsConfigurationEndpoint,
            parameters
          })
        }
        value={{ id: host.id, name: host.name }}
      />
      <div />
      <TextField
        required
        value={host.address}
        onChange={changeAddress}
        label={t(labelDNSIP)}
        dataTestId={labelDNSIP}
        fullWidth
        slotProps={{
          htmlInput: {
            'aria-label': labelDNSIP
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
      />
      {areCertificateFieldsVisible && (
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
        />
      )}

      {areCertificateFieldsVisible && (
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
        />
      )}
    </Box>
  );
};

export default HostConfiguration;
