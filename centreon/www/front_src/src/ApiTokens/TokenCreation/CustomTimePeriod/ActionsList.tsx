import { PickersActionBarProps } from '@mui/x-date-pickers/PickersActionBar';

const ActionList = (props: PickersActionBarProps): JSX.Element => {
  const { onAccept, onCancel, className, onClear } = props;

  const accept = (): void => {
    // validation()
    props.accept();
    // onAccept();
  };

  const cancel = (): void => {
    props.cancel();
    // onCancel();
  };

  return (
    <div className={className}>
      <button disabled={props.isInvalidDate} type="button" onClick={accept}>
        Ok
      </button>
      <button type="button" onClick={cancel}>
        Cancel
      </button>
    </div>
  );
};

export default ActionList;
