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
  labelAddHost,
  labelCaCertificate,
  labelCertificate,
  labelDNSIP,
  labelPort
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
        label={t(labelAddHost)}
        value={null}
        onChange={selectHost}
        getEndpoint={(parameters) =>
          buildListingEndpoint({
            baseEndpoint: hostsConfigurationEndpoint,
            parameters
          })
        }
        fieldName="name"
      />
      <div />
      <TextField
        required
        value={host.address}
        onChange={changeAddress}
        label={t(labelDNSIP)}
        dataTestId={labelDNSIP}
        fullWidth
        inputProps={{
          'aria-label': labelDNSIP
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
        inputProps={{
          min: 1,
          max: 65535
        }}
      />
      <TextField
        value={host?.pollerCaCertificate || ''}
        onChange={changeStringInput('pollerCaCertificate')}
        label={t(labelCertificate)}
        dataTestId={labelCertificate}
        inputProps={{
          'aria-label': labelCertificate
        }}
        fullWidth
        error={
          (hostTouched?.pollerCaCertificate &&
            hostErrors?.pollerCaCertificate) ||
          undefined
        }
      />
      <TextField
        value={host?.pollerCaName || ''}
        onChange={changeStringInput('pollerCaName')}
        label={t(labelCaCertificate)}
        inputProps={{
          'aria-label': labelCaCertificate
        }}
        dataTestId={labelCaCertificate}
        fullWidth
        error={
          (hostTouched?.pollerCaName && hostErrors?.pollerCaName) || undefined
        }
      />
    </Box>
  );
};

export default HostConfiguration;
