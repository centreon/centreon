import { Typography, TextField, Box } from '@mui/material';

interface Options {
  input: string;
}
interface Props {
  panelOptions?: Options;
  setPanelOptions: (options: Options) => void;
}

const Text = ({ panelOptions, setPanelOptions }: Props): JSX.Element => {
  const changeInput = (event): void => {
    setPanelOptions({ input: event.target.value });
  };

  return (
    <Box>
      <TextField value={panelOptions?.input} onChange={changeInput} />
      <Typography>{panelOptions?.input}</Typography>
    </Box>
  );
};

export default Text;
