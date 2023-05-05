import { createStore } from 'jotai';

import { Typography, TextField, Box } from '@mui/material';

import { Module } from '@centreon/ui';

interface Options {
  input: string;
}
interface Props {
  panelOptions?: Options;
  setPanelOptions: (options: Options) => void;
  store: ReturnType<typeof createStore>;
}

const Text = ({ panelOptions, setPanelOptions, store }: Props): JSX.Element => {
  const changeInput = (event): void => {
    setPanelOptions({ input: event.target.value });
  };

  return (
    <Module maxSnackbars={1} seedName="text2" store={store}>
      <Box>
        <TextField value={panelOptions?.input} onChange={changeInput} />
        <Typography>{panelOptions?.input}</Typography>
      </Box>
    </Module>
  );
};

export default Text;
