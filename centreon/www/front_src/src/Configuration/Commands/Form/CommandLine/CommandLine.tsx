import { ChangeEvent, ReactElement } from 'react';

import ArrowIcon from '@mui/icons-material/ArrowForwardSharp';

import {
  IconButton,
  SingleConnectedAutocompleteField,
  TextField
} from '@centreon/ui';
import { useTranslation } from 'react-i18next';

import { useFormikContext } from 'formik';
import { Command } from '../../models';
import {
  labelCommandLine,
  labelInstalledPlugins,
  labelPollerGlobalMacros,
  labelStandardMacros
} from '../../translatedLabels';

const CommandLine = (): ReactElement => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched, touched, errors } =
    useFormikContext<Command>();

  const value = values?.commandLine;

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched('commandLine', true);
    setFieldValue('commandLine', event.target.value);
  };

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

      <TextField
        required
        multiline
        rows={6}
        value={value}
        onChange={change}
        label={t(labelCommandLine)}
        dataTestId={labelCommandLine}
        fullWidth
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              'aria-label': t(labelCommandLine)
            }
          }
        }}
        error={touched?.commandLine && errors?.commandLine}
      />
    </div>
  );
};

export default CommandLine;
