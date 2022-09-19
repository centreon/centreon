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
      try {
        const textArea = document.createElement('textarea');
        document.body.appendChild(textArea);
        textArea.value = text;
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
      } catch (e) {
        showErrorMessage(errorMessage);
      }

      return;
    }

    await navigator.clipboard.writeText(text);
    showSuccessMessage(successMessage);
  };

  return { copy };
};

export default useCopyToClipboard;
