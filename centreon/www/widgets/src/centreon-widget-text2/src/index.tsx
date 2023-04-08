import { Typography, TextField, Box } from '@mui/material';

interface Options {
  input: string;
}
interface Props {
  setWidgetOptions: (options: Options) => void;
  widgetOptions?: Options;
}

const Text = ({ widgetOptions, setWidgetOptions }: Props): JSX.Element => {
  const changeInput = (event): void => {
    setWidgetOptions({ input: event.target.value });
  };

  return (
    <Box>
      <TextField value={widgetOptions?.input} onChange={changeInput} />
      <Typography>{widgetOptions?.input}</Typography>
    </Box>
  );
};

export default Text;
