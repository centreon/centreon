import { ReactElement } from 'react';

import ArrowIcon from '@mui/icons-material/ArrowForwardSharp';

import {
  IconButton,
  SingleConnectedAutocompleteField,
  TextField
} from '@centreon/ui';
import { useTranslation } from 'react-i18next';

import {
  labelInstalledPlugins,
  labelPollerGlobalMacros,
  labelStandardMacros
} from '../../translatedLabels';

const CommandLine = (): ReactElement => {
  const { t } = useTranslation();

  return (
    <div
      style={{
        display: 'grid',
        gap: '8px',
        gridTemplateColumns: '2fr 40px 5fr',
        paddingTop: '8px'
      }}
    >
      <div
        style={{
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'space-between'
        }}
      >
        <SingleConnectedAutocompleteField
          label={t(labelPollerGlobalMacros)}
          onChange={() => undefined}
          getEndpoint={(parameters) => ''}
          value={{ id: 'host', name: 'name' }}
        />

        <SingleConnectedAutocompleteField
          label={t(labelInstalledPlugins)}
          onChange={() => undefined}
          getEndpoint={(parameters) => ''}
          value={{ id: 'host', name: 'name' }}
        />

        <SingleConnectedAutocompleteField
          label={t(labelStandardMacros)}
          onChange={() => undefined}
          getEndpoint={(parameters) => ''}
          value={{ id: 'host', name: 'name' }}
        />
      </div>
      <div
        style={{
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'space-between'
        }}
      >
        <div
          style={{
            display: 'flex',
            justifyContent: 'end',
            alignItems: 'center'
          }}
        >
          <IconButton
            ariaLabel="Insert"
            dataTestid="Insert"
            title="Insert"
            onClick={() => undefined}
            disabled={false}
            style={{ color: '#0004' }}
          >
            <ArrowIcon />
          </IconButton>
        </div>
        <div
          style={{
            display: 'flex',
            justifyContent: 'end',
            alignItems: 'center'
          }}
        >
          <IconButton
            ariaLabel="Insert"
            dataTestid="Insert"
            title="Insert"
            onClick={() => undefined}
            disabled={false}
            style={{ color: '#0004' }}
          >
            <ArrowIcon />
          </IconButton>
        </div>
        <div
          style={{
            display: 'flex',
            justifyContent: 'end',
            alignItems: 'center'
          }}
        >
          <IconButton
            ariaLabel="Insert"
            dataTestid="Insert"
            title="Insert"
            onClick={() => undefined}
            disabled={false}
            style={{ color: '#0004' }}
          >
            <ArrowIcon />
          </IconButton>
        </div>
      </div>
      <div
        style={{
          background: '#0001'
        }}
      >
        <TextField
          required
          multiline
          rows={6}
          // value={'host'}
          // onChange={() => undefined}
          label={'t(labelDNSIP)'}
          dataTestId={'labelDNSIP'}
          fullWidth
          textFieldSlotsAndSlotProps={{
            slotProps: {
              htmlInput: {
                'aria-label': 'labelDNSIP'
              }
            }
          }}
          // error={hostTouched?.address && hostErrors?.address}
        />
      </div>
    </div>
  );
};

export default CommandLine;
