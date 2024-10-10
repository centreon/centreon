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
  labelCertificate,
  labelDNSIP,
  labelPort,
  labelPrivateKey
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
        required
        value={host.certificate}
        onChange={changeStringInput('certificate')}
        label={t(labelCertificate)}
        dataTestId={labelCertificate}
        inputProps={{
          'aria-label': labelCertificate
        }}
        fullWidth
        error={hostTouched?.certificate && hostErrors?.certificate}
      />
      <TextField
        required
        value={host.key}
        onChange={changeStringInput('key')}
        label={t(labelPrivateKey)}
        inputProps={{
          'aria-label': labelPrivateKey
        }}
        dataTestId={labelPrivateKey}
        fullWidth
        error={hostTouched?.key && hostErrors?.key}
      />
    </Box>
  );
};

export default HostConfiguration;
