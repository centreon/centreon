import useSnackbar from '../Snackbar/useSnackbar';

type CopyFunction = (text: string) => Promise<void>;

interface Result {
  copy: CopyFunction;
}

interface Props {
  errorMessage: string;
  successMessage: string;
}
const useCopyToClipboard = ({
  successMessage,
  errorMessage,
}: Props): Result => {
  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const copy: CopyFunction = async (text) => {
    if (!navigator?.clipboard) {
      showErrorMessage(errorMessage);

      return;
    }

    await navigator.clipboard.writeText(text);
    showSuccessMessage(successMessage);
  };

  return { copy };
};

export default useCopyToClipboard;
